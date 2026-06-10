<?php

declare(strict_types=1);

namespace Amelias\Controllers\Admin;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\Content;
use Amelias\Services\Media;
use Amelias\Services\Settings;

/**
 * No-code content editing (owner/manager), content blocks + CMS pages with SEO.
 *
 * Block values and page bodies are sanitized on render by Content::sanitize();
 * here we store what the editor typed and write an audit_log entry on each save.
 */
final class ContentController extends AdminController
{
    /** Editable keyed blocks surfaced in the UI (label per key). */
    private const BLOCKS = [
        'home.hero.kicker'  => 'Home · hero kicker',
        'home.hero.heading' => 'Home · hero heading',
        'home.hero.lede'    => 'Home · hero lede',
        'home.philosophy'   => 'Home · philosophy line',
        'story.body'        => 'Story · opening body',
    ];

    public function index(Request $request): void
    {
        $user = $this->requireRole('owner', 'manager');

        $saved = false;
        $error = null;

        if ($request->isPost()) {
            if (!csrf_verify((string) $request->input('_csrf'))) {
                $error = 'Your session expired. Please try again.';
            } else {
                $action = (string) $request->input('action', 'blocks');
                if ($action === 'page') {
                    try {
                        $this->savePage($request, (int) $user['id']);
                        $entity = 'page';
                    } catch (\RuntimeException $e) {
                        $error = $e->getMessage();
                    }
                } elseif ($action === 'featured') {
                    $this->saveFeatured($request, (int) $user['id']);
                    $entity = 'setting';
                } else {
                    $this->saveBlocks($request, (int) $user['id']);
                    $entity = 'content_block';
                }
                if ($error === null) {
                    Database::run(
                        'INSERT INTO audit_log (actor_type, actor_id, action, entity_type) VALUES (?,?,?,?)',
                        ['user', $user['id'], 'content.update', $entity]
                    );
                    $saved = true;
                }
            }
        }

        // Surface per-block value + updated_at so the template can render a
        // status table (label / status / last edited). content_blocks has no
        // per-block status column, so "published" is implied by having a value
        // and the editor exposes a single Save (no draft/publish split here).
        $blockMeta = [];
        foreach (
            Database::fetchAll('SELECT block_key, value_html, updated_at FROM content_blocks') as $row
        ) {
            $blockMeta[(string) $row['block_key']] = $row;
        }
        $blocks = [];
        foreach (self::BLOCKS as $key => $label) {
            $meta = $blockMeta[$key] ?? null;
            $value = (string) ($meta['value_html'] ?? '');
            $blocks[$key] = [
                'label'      => $label,
                'value'      => $value,
                'updated_at' => $meta['updated_at'] ?? null,
                // No status column on content_blocks: a block is "published"
                // (live on the public site) whenever it has a value.
                'published'  => $value !== '',
            ];
        }

        $pages = Database::fetchAll(
            "SELECT id, slug, type, title, status, published_at, updated_at
             FROM pages ORDER BY type, title"
        );

        $editPage = null;
        $editId = $request->query('page');
        if ($editId !== null && ctype_digit($editId)) {
            $editPage = Database::fetch('SELECT * FROM pages WHERE id = ?', [(int) $editId]);
        }

        // "From the kitchen" featured-product picker: active food products for
        // the dropdowns + the currently selected ordered IDs (up to 3).
        $foodProducts = Database::fetchAll(
            "SELECT id, name FROM products WHERE is_active = 1 AND type = 'food' ORDER BY name"
        );
        $featuredIds = [];
        foreach (explode(',', (string) Settings::get('home.featured_products', '')) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $featuredIds[] = $id;
            }
        }

        $this->viewAdmin('admin/content', [
            'title'        => 'Content',
            'styles'       => ['pages/admin-content.css'],
            'saved'        => $saved,
            'error'        => $error,
            'blocks'       => $blocks,
            'pages'        => $pages,
            'editPage'     => $editPage,
            'foodProducts' => $foodProducts,
            'featuredIds'  => $featuredIds,
        ]);
    }

    private function saveBlocks(Request $request, int $userId): void
    {
        foreach (self::BLOCKS as $key => $_label) {
            $val = $request->input($key);
            if ($val === null) {
                continue;
            }
            Database::run(
                'INSERT INTO content_blocks (block_key, value_html, updated_by) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE value_html = VALUES(value_html), updated_by = VALUES(updated_by)',
                [$key, trim((string) $val), $userId]
            );
        }
    }

    /**
     * Save the up-to-3 home "From the kitchen" featured products as an ordered,
     * comma-separated list of product IDs in the `home.featured_products`
     * setting. Blanks are dropped and duplicates de-duped (first wins).
     */
    private function saveFeatured(Request $request, int $userId): void
    {
        $ids = [];
        foreach (['product_id_1', 'product_id_2', 'product_id_3'] as $field) {
            $id = (int) $request->input($field, 0);
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        Settings::set('home.featured_products', implode(',', $ids), false, $userId);
    }

    private function savePage(Request $request, int $userId): void
    {
        $id     = (int) $request->input('id', 0);
        $slug   = $this->slugify((string) $request->input('slug', ''));
        $type   = $request->input('type') === 'post' ? 'post' : 'page';
        $title  = trim((string) $request->input('title', ''));
        $body   = (string) $request->input('body_html', '');
        $excerpt = trim((string) $request->input('excerpt', ''));
        $heroImage = $this->resolveHeroImage($request, $userId);
        $seoTitle  = trim((string) $request->input('seo_title', ''));
        $seoDesc   = trim((string) $request->input('seo_description', ''));
        $status    = $request->input('status') === 'published' ? 'published' : 'draft';

        if ($slug === '' || $title === '') {
            return;
        }

        // Set published_at the first time a page goes live.
        $publishedAt = $status === 'published' ? gmdate('Y-m-d H:i:s') : null;

        if ($id > 0) {
            // Preserve the original published_at if it was already live.
            $existing = Database::fetch('SELECT published_at, status FROM pages WHERE id = ?', [$id]);
            if ($existing !== null && $existing['status'] === 'published' && !empty($existing['published_at'])) {
                $publishedAt = $existing['published_at'];
            }
            Database::run(
                'UPDATE pages SET slug=?, type=?, title=?, body_html=?, excerpt=?, hero_image=?, seo_title=?, seo_description=?, status=?, published_at=? WHERE id=?',
                [
                    $slug, $type, $title, $body,
                    $excerpt !== '' ? $excerpt : null,
                    $heroImage !== '' ? $heroImage : null,
                    $seoTitle !== '' ? $seoTitle : null,
                    $seoDesc !== '' ? $seoDesc : null,
                    $status, $publishedAt, $id,
                ]
            );
        } else {
            Database::run(
                'INSERT INTO pages (slug, type, title, body_html, excerpt, hero_image, seo_title, seo_description, status, published_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                [
                    $slug, $type, $title, $body,
                    $excerpt !== '' ? $excerpt : null,
                    $heroImage !== '' ? $heroImage : null,
                    $seoTitle !== '' ? $seoTitle : null,
                    $seoDesc !== '' ? $seoDesc : null,
                    $status, $publishedAt,
                ]
            );
        }
    }

    /**
     * Resolve the page hero image filename. A newly uploaded file (multipart
     * field "hero_image") is stored via the Media service and its random
     * filename returned. With no new upload we keep the current filename
     * (hidden field "hero_image_current") unless "Remove current image" was
     * checked. Returns the stored filename, or '' for none.
     */
    private function resolveHeroImage(Request $request, int $userId): string
    {
        $current = trim((string) $request->input('hero_image_current', ''));
        $remove  = (string) $request->input('hero_image_remove', '') !== '';

        $file = $_FILES['hero_image'] ?? null;
        $hasUpload = is_array($file)
            && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasUpload) {
            $media = Media::handleUpload($file, $userId);
            return (string) ($media['filename'] ?? '');
        }

        if ($remove) {
            return '';
        }

        return $current;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
