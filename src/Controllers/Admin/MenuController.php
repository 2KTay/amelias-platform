<?php

declare(strict_types=1);

namespace Amelias\Controllers\Admin;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\Media;

/**
 * Menu & Pricing (admin), Owner + Manager.
 *
 * index() lists the catalog as a single data-table (category dropdown filter,
 * inline price edit + per-row Save, an 86 toggle, and a row-delete). Clicking a
 * row opens the full product editor.
 *
 * edit() is the product detail/editor (new or existing): name, category,
 * price, day-part, description, dietary tags, image upload, 86, and active
 * state. New rows insert a product; existing rows update in place. Deletes are
 * soft (is_active = 0) so order history that snapshots names/prices is kept.
 *
 * All mutations are CSRF-guarded POSTs with PRG + a session flash and an
 * audit_log entry. The admin reads the same catalog tables the public
 * MenuController renders from (products, categories, product_category_map,
 * product_variants, dietary_tags, product_dietary_map) so the table mirrors
 * the live menu.
 */
final class MenuController extends AdminController
{
    /** Day-part code => human label (mirrors products.day_part enum). */
    private const DAY_PARTS = [
        'all_day'    => 'All day',
        'day'        => 'Day',
        'pm'         => 'Dinner',
        'happy_hour' => 'Happy Hour',
        'catering'   => 'Catering',
    ];

    public function index(Request $request): void
    {
        $user = $this->requireRole('owner', 'manager');

        if ($request->isPost()) {
            if (!csrf_verify((string) $request->input('_csrf'))) {
                $this->redirect(url('/admin/menu'));
                return;
            }
            $action = (string) $request->input('action');
            match ($action) {
                'price'    => $this->updatePrice((int) $request->input('product_id'), (string) $request->input('price', ''), (int) $user['id']),
                'toggle86' => $this->toggle86((int) $request->input('product_id'), (int) $user['id']),
                'delete'   => $this->removeItem((int) $request->input('product_id'), (int) $user['id']),
                default    => null,
            };
            $this->redirect(url('/admin/menu') . $this->tabQuery($request));
            return;
        }

        $categories = $this->categories();
        $items      = $this->items();

        // Group items by category id (a product may map to several categories).
        $byCategory = [];
        foreach ($items as $it) {
            foreach ($it['category_ids'] as $cid) {
                $byCategory[$cid][] = $it;
            }
        }

        // Filter: ?cat=<id> shows one category; absent/0 = "All".
        $activeCat = (int) ($request->query('cat') ?? 0);
        if ($activeCat !== 0 && !isset($byCategory[$activeCat])) {
            $activeCat = 0;
        }

        // Visible rows: a single flat list (All) or one category's items.
        if ($activeCat === 0) {
            $visible = $items;
        } else {
            $visible = $byCategory[$activeCat] ?? [];
        }

        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        $this->viewAdmin('admin/menu', [
            'title'      => 'Menu & Pricing',
            'styles'     => ['pages/admin-menu.css'],
            'categories' => $categories,
            'byCategory' => $byCategory,
            'visible'    => $visible,
            'activeCat'  => $activeCat,
            'dayParts'   => self::DAY_PARTS,
            'flash'      => $flash,
        ]);
    }

    /**
     * Product editor, GET renders the form (blank for /new, populated for
     * /{id}); POST validates and saves (or deletes), then PRG redirects.
     */
    public function edit(Request $request): void
    {
        $user = $this->requireRole('owner', 'manager');

        // Route id: null on /admin/menu/new, numeric on /admin/menu/{id}.
        $idParam   = $request->param('id');
        $productId = $idParam !== null ? (int) $idParam : 0;

        if ($request->isPost()) {
            if (!csrf_verify((string) $request->input('_csrf'))) {
                $this->redirect(url('/admin/menu'));
                return;
            }
            $action = (string) $request->input('action', 'save');
            if ($action === 'delete') {
                $this->removeItem($productId, (int) $user['id']);
                $this->redirect(url('/admin/menu'));
                return;
            }
            $this->saveItem($request, $productId, (int) $user['id']);
            return;
        }

        $product = null;
        if ($productId > 0) {
            $product = Database::fetch(
                'SELECT id, name, slug, description, price_cents, day_part, image_path,
                        is_86, is_active
                   FROM products WHERE id = ?',
                [$productId]
            );
            if ($product === null) {
                $this->flash('error', 'That item no longer exists.');
                $this->redirect(url('/admin/menu'));
                return;
            }
        }

        $selectedCat  = $productId > 0 ? $this->productCategoryId($productId) : 0;
        $selectedTags = $productId > 0 ? $this->productTagIds($productId) : [];

        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        $this->viewAdmin('admin/menu-item', [
            'title'        => $product !== null ? 'Edit item' : 'Add item',
            'styles'       => ['pages/admin-menu.css'],
            'product'      => $product,
            'categories'   => $this->categories(),
            'dietaryTags'  => $this->dietaryTags(),
            'dayParts'     => self::DAY_PARTS,
            'selectedCat'  => $selectedCat,
            'selectedTags' => $selectedTags,
            'flash'        => $flash,
        ]);
    }

    /**
     * Active categories that hold orderable items, in display order.
     *
     * @return list<array<string,mixed>>
     */
    private function categories(): array
    {
        return Database::fetchAll(
            "SELECT id, name FROM categories
              WHERE is_active = 1
              ORDER BY sort ASC, name ASC"
        );
    }

    /** All dietary tags for the editor's tag picker. @return list<array<string,mixed>> */
    private function dietaryTags(): array
    {
        return Database::fetchAll('SELECT id, slug, label FROM dietary_tags ORDER BY slug ASC');
    }

    /** First mapped category id for a product (the editor edits one primary category). */
    private function productCategoryId(int $productId): int
    {
        $val = Database::fetchValue(
            'SELECT category_id FROM product_category_map WHERE product_id = ? ORDER BY category_id ASC LIMIT 1',
            [$productId]
        );
        return $val !== null ? (int) $val : 0;
    }

    /** Tag ids currently mapped to a product. @return list<int> */
    private function productTagIds(int $productId): array
    {
        $rows = Database::fetchAll(
            'SELECT tag_id FROM product_dietary_map WHERE product_id = ?',
            [$productId]
        );
        return array_map(static fn ($r) => (int) $r['tag_id'], $rows);
    }

    /**
     * Catalog rows for the admin table: price, day-part, 86 state, the category
     * ids each product maps to, and its dietary tags. Mirrors the public menu's
     * source tables but includes inactive-day-part items so managers see all.
     *
     * @return list<array<string,mixed>>
     */
    private function items(): array
    {
        $rows = Database::fetchAll(
            "SELECT p.id, p.name, p.price_cents, p.day_part, p.is_86,
                    c.id AS category_id, c.name AS category_name
               FROM products p
          LEFT JOIN product_category_map pcm ON pcm.product_id = p.id
          LEFT JOIN categories c ON c.id = pcm.category_id
              WHERE p.is_active = 1 AND p.type IN ('food','catering','market')
              ORDER BY p.sort ASC, p.name ASC"
        );

        // Dietary tags per product (V / GF …), same source as the public menu.
        $tags = Database::fetchAll(
            "SELECT pdm.product_id, dt.slug, dt.label FROM product_dietary_map pdm
               JOIN dietary_tags dt ON dt.id = pdm.tag_id ORDER BY dt.slug"
        );
        $tagsByProduct = [];
        foreach ($tags as $t) {
            $tagsByProduct[(int) $t['product_id']][] = [
                'slug'  => (string) $t['slug'],
                'label' => (string) $t['label'],
            ];
        }

        // Collapse the category join into one row per product.
        $items = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if (!isset($items[$id])) {
                $items[$id] = [
                    'id'              => $id,
                    'name'            => (string) $r['name'],
                    'price_cents'     => (int) $r['price_cents'],
                    'day_part'        => (string) $r['day_part'],
                    'is_86'           => (bool) $r['is_86'],
                    'category_ids'    => [],
                    'category_names'  => [],
                    'dietary_tags'    => $tagsByProduct[$id] ?? [],
                ];
            }
            if ($r['category_id'] !== null) {
                $items[$id]['category_ids'][]   = (int) $r['category_id'];
                $items[$id]['category_names'][] = (string) $r['category_name'];
            }
        }

        return array_values($items);
    }

    /** Update a single product price from a dollar string. */
    private function updatePrice(int $productId, string $price, int $userId): void
    {
        if ($productId <= 0 || trim($price) === '') {
            return;
        }
        $priceCents = cents($price);
        if ($priceCents < 0) {
            $this->flash('error', 'Price cannot be negative.');
            return;
        }
        Database::run('UPDATE products SET price_cents = ? WHERE id = ?', [$priceCents, $productId]);
        $this->audit($userId, 'menu.price', $productId, ['price_cents' => $priceCents]);
        $this->flash('success', 'Price updated to ' . fmt_money($priceCents) . '.');
    }

    /** Flip the 86 (sold-out) flag, propagates to the public menu. */
    private function toggle86(int $productId, int $userId): void
    {
        if ($productId <= 0) {
            return;
        }
        Database::run('UPDATE products SET is_86 = 1 - is_86 WHERE id = ?', [$productId]);
        $now = (int) Database::fetchValue('SELECT is_86 FROM products WHERE id = ?', [$productId]);
        $this->audit($userId, 'menu.toggle86', $productId, ['is_86' => $now]);
        $this->flash('success', $now === 1 ? "Item 86'd, hidden from the menu." : 'Item back on the menu.');
    }

    /**
     * Create or update a product from the editor form, then PRG-redirect.
     * Insert path mints a product; update path edits in place. Category and
     * dietary-tag maps are rewritten to match the submitted selection. An
     * optional image upload routes through the Media service.
     */
    private function saveItem(Request $request, int $productId, int $userId): void
    {
        $name        = trim((string) $request->input('name', ''));
        $priceInput  = trim((string) $request->input('price', ''));
        $categoryId  = (int) $request->input('category_id');
        $description = trim((string) $request->input('description', ''));
        $dayPart     = (string) $request->input('day_part', 'all_day');
        $is86        = $request->input('is_86') !== null ? 1 : 0;
        $isActive    = $request->input('is_active') !== null ? 1 : 0;

        /** @var list<int> $tagIds */
        $tagIds = [];
        foreach ((array) $request->input('tags', []) as $tid) {
            $tid = (int) $tid;
            if ($tid > 0) {
                $tagIds[] = $tid;
            }
        }

        if (!array_key_exists($dayPart, self::DAY_PARTS)) {
            $dayPart = 'all_day';
        }

        $editUrl = $productId > 0 ? url('/admin/menu/' . $productId) : url('/admin/menu/new');

        if ($name === '') {
            $this->flash('error', 'A name is required.');
            $this->redirect($editUrl);
            return;
        }

        $priceCents = $priceInput !== '' ? cents($priceInput) : 0;
        if ($priceCents < 0) {
            $this->flash('error', 'Price cannot be negative.');
            $this->redirect($editUrl);
            return;
        }

        // Optional image upload, handled through the same Media service the
        // storefront uses. Stores the returned filename to products.image_path.
        $imageName = null;
        $file      = $_FILES['image'] ?? null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $media     = Media::handleUpload($file, $userId, $name);
                $imageName = (string) ($media['filename'] ?? '');
                if ($imageName === '') {
                    $imageName = null;
                }
            } catch (\Throwable $e) {
                $this->flash('error', 'Image upload failed: ' . $e->getMessage());
                $this->redirect($editUrl);
                return;
            }
        }

        if ($productId > 0) {
            // ---- Update existing ----
            if (Database::fetchValue('SELECT 1 FROM products WHERE id = ?', [$productId]) === null) {
                $this->flash('error', 'That item no longer exists.');
                $this->redirect(url('/admin/menu'));
                return;
            }
            if ($imageName !== null) {
                Database::run(
                    'UPDATE products
                        SET name = ?, description = ?, price_cents = ?, day_part = ?,
                            is_86 = ?, is_active = ?, image_path = ?
                      WHERE id = ?',
                    [$name, $description !== '' ? $description : null, $priceCents, $dayPart, $is86, $isActive, $imageName, $productId]
                );
            } else {
                Database::run(
                    'UPDATE products
                        SET name = ?, description = ?, price_cents = ?, day_part = ?,
                            is_86 = ?, is_active = ?
                      WHERE id = ?',
                    [$name, $description !== '' ? $description : null, $priceCents, $dayPart, $is86, $isActive, $productId]
                );
            }
            $this->audit($userId, 'menu.update', $productId, [
                'name' => $name, 'price_cents' => $priceCents, 'day_part' => $dayPart,
                'is_86' => $is86, 'is_active' => $isActive,
            ]);
            $this->flash('success', 'Saved "' . $name . '".');
        } else {
            // ---- Insert new ----
            $base = $this->slugify($name);
            $slug = $base;
            $n    = 1;
            while (Database::fetchValue('SELECT 1 FROM products WHERE slug = ?', [$slug]) !== null) {
                $slug = $base . '-' . (++$n);
            }
            $productId = Database::insert(
                "INSERT INTO products (slug, name, description, type, price_cents, day_part, image_path, is_86, is_active)
                 VALUES (?, ?, ?, 'food', ?, ?, ?, ?, ?)",
                [$slug, $name, $description !== '' ? $description : null, $priceCents, $dayPart, $imageName, $is86, $isActive]
            );
            $this->audit($userId, 'menu.add', $productId, ['name' => $name, 'price_cents' => $priceCents]);
            $this->flash('success', 'Added "' . $name . '" to the menu.');
        }

        // Rewrite the single category mapping to match the selection.
        Database::run('DELETE FROM product_category_map WHERE product_id = ?', [$productId]);
        if ($categoryId > 0) {
            Database::run(
                'INSERT IGNORE INTO product_category_map (product_id, category_id) VALUES (?, ?)',
                [$productId, $categoryId]
            );
        }

        // Rewrite dietary-tag mappings (bound params per row; no IN-list interpolation).
        Database::run('DELETE FROM product_dietary_map WHERE product_id = ?', [$productId]);
        foreach ($tagIds as $tid) {
            Database::run(
                'INSERT IGNORE INTO product_dietary_map (product_id, tag_id) VALUES (?, ?)',
                [$productId, $tid]
            );
        }

        $this->redirect(url('/admin/menu/' . $productId));
    }

    /** Soft-delete: deactivate so snapshotted order history stays intact. */
    private function removeItem(int $productId, int $userId): void
    {
        if ($productId <= 0) {
            return;
        }
        Database::run('UPDATE products SET is_active = 0 WHERE id = ?', [$productId]);
        $this->audit($userId, 'menu.delete', $productId, ['is_active' => 0]);
        $this->flash('success', 'Item removed from the menu.');
    }

    /** Lowercase, hyphenated, ascii-ish slug. */
    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        return $value === '' ? 'item' : substr($value, 0, 150);
    }

    /** Preserve the active category filter across the PRG redirect. */
    private function tabQuery(Request $request): string
    {
        $cat = (int) $request->input('category_id', (int) ($request->query('cat') ?? 0));
        return $cat > 0 ? '?cat=' . $cat : '';
    }

    private function audit(int $userId, string $action, int $productId, array $after): void
    {
        Database::run(
            'INSERT INTO audit_log (actor_type, actor_id, action, entity_type, entity_id, after_json)
             VALUES (?, ?, ?, ?, ?, ?)',
            ['user', $userId, $action, 'product', (string) $productId, json_encode($after, JSON_THROW_ON_ERROR)]
        );
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }
}
