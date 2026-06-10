<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;

/**
 * CMS read service, content blocks, structured pages, and blog posts.
 *
 * Stored HTML is never trusted blindly: get() runs the value through a tag
 * allowlist before it reaches a template, so even a compromised editor account
 * cannot inject script/iframe/style into a public page. Plain strings (no tags)
 * pass through unchanged and are still escaped at the call site where needed.
 */
final class Content
{
    /** Tags allowed to survive sanitization of rich-text blocks/pages. */
    private const ALLOWED_TAGS = ['a', 'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h2', 'h3', 'blockquote'];

    /**
     * Read a content block by key, returning sanitized HTML (or $default if the
     * block is missing/empty). Safe to echo directly into a template.
     */
    public static function get(string $blockKey, string $default = ''): string
    {
        $row = Database::fetch(
            'SELECT value_html FROM content_blocks WHERE block_key = ? LIMIT 1',
            [$blockKey]
        );
        $value = $row['value_html'] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        return self::sanitize((string) $value);
    }

    /** Raw (unsanitized) block value for admin editing forms. Null if unset. */
    public static function raw(string $blockKey): ?string
    {
        $row = Database::fetch(
            'SELECT value_html FROM content_blocks WHERE block_key = ? LIMIT 1',
            [$blockKey]
        );
        return $row['value_html'] ?? null;
    }

    /** A single published page (type='page' or 'post') by slug, or null. */
    public static function getPage(string $slug): ?array
    {
        return Database::fetch(
            "SELECT * FROM pages WHERE slug = ? AND status = 'published' LIMIT 1",
            [$slug]
        );
    }

    /** Published blog posts, newest first. */
    public static function listPosts(): array
    {
        return Database::fetchAll(
            "SELECT id, slug, title, excerpt, hero_image, published_at
             FROM pages
             WHERE type = 'post' AND status = 'published'
             ORDER BY published_at DESC, id DESC"
        );
    }

    /**
     * Strip every tag and attribute outside the allowlist. Anchors keep only a
     * safe href (http/https/mailto/tel or site-relative); everything else is
     * dropped. This is a conservative, dependency-free sanitizer.
     */
    public static function sanitize(string $html): string
    {
        // Fast path: no markup at all.
        if (strip_tags($html) === $html) {
            return $html;
        }

        $allowed = '<' . implode('><', self::ALLOWED_TAGS) . '>';
        $clean = strip_tags($html, $allowed);

        // Re-filter attributes: only <a href="..."> survives, all else stripped.
        $clean = preg_replace_callback('/<([a-z0-9]+)([^>]*)>/i', static function (array $m): string {
            $tag = strtolower($m[1]);
            if ($tag !== 'a') {
                return '<' . $tag . '>';
            }
            if (preg_match('/\bhref\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $m[2], $hm)) {
                $href = $hm[2] !== '' ? $hm[2] : ($hm[3] ?? '');
                if (self::safeHref($href)) {
                    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" rel="noopener">';
                }
            }
            return '<a>';
        }, $clean);

        return $clean ?? '';
    }

    /** Allow only non-javascript schemes (or relative URLs) in anchor hrefs. */
    private static function safeHref(string $href): bool
    {
        $href = trim($href);
        if ($href === '') {
            return false;
        }
        if (preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $href)) {
            return true;
        }
        // Reject anything with a scheme we did not allow (e.g. javascript:).
        return !preg_match('/^[a-z][a-z0-9+.\-]*:/i', $href);
    }
}
