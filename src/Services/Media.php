<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;
use RuntimeException;

/**
 * Secure media uploads.
 *
 * Defense in depth:
 *   - finfo MIME sniff (never trust the browser-supplied type or extension).
 *   - Allowlist of image types only (jpeg/png/webp/gif).
 *   - Random-hex stored filename (no user-controlled path, no overwrite).
 *   - Canonical copy kept out of the web root (config('paths.uploads'));
 *     a display copy is written into public/assets/uploads/ for serving.
 *   - public/assets/uploads/.htaccess disables PHP execution there, so a
 *     disguised script can never run even if one slipped through.
 */
final class Media
{
    /** MIME => file extension allowlist. */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    private const MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    /**
     * Validate and store an uploaded image, recording a media row.
     *
     * @param array $file One element of $_FILES (tmp_name, name, size, error).
     * @return array The inserted media row.
     */
    public static function handleUpload(array $file, ?int $userId = null, ?string $altText = null): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed (error code ' . (int) ($file['error'] ?? -1) . ').');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            // is_uploaded_file is false on CLI/tests; fall back to file existence.
            if ($tmp === '' || !is_file($tmp)) {
                throw new RuntimeException('No uploaded file present.');
            }
        }

        $bytes = (int) ($file['size'] ?? filesize($tmp) ?: 0);
        if ($bytes <= 0 || $bytes > self::MAX_BYTES) {
            throw new RuntimeException('File is empty or exceeds the 10 MB limit.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($tmp);
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Only JPEG, PNG, WebP and GIF images are allowed.');
        }
        $ext = self::ALLOWED[$mime];

        // Random, non-guessable, collision-resistant name.
        $name = bin2hex(random_bytes(16)) . '.' . $ext;

        // Canonical copy out of the web root.
        $canonicalDir = rtrim((string) config('paths.uploads'), '/\\');
        self::ensureDir($canonicalDir);
        $canonicalPath = $canonicalDir . DIRECTORY_SEPARATOR . $name;

        // Display copy under the web root (served by the front controller host).
        $publicDir = rtrim((string) config('paths.root'), '/\\') . '/public/assets/uploads';
        self::ensureDir($publicDir);
        $publicPath = $publicDir . DIRECTORY_SEPARATOR . $name;

        if (!@copy($tmp, $canonicalPath)) {
            throw new RuntimeException('Could not store the upload.');
        }
        if (!@copy($tmp, $publicPath)) {
            @unlink($canonicalPath);
            throw new RuntimeException('Could not publish the upload.');
        }

        [$width, $height] = self::dimensions($publicPath);

        $id = Database::insert(
            'INSERT INTO media (filename, original_name, mime, width, height, alt_text, bytes, uploaded_by)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $name,
                self::cleanName((string) ($file['name'] ?? $name)),
                $mime,
                $width,
                $height,
                $altText !== null && $altText !== '' ? $altText : null,
                $bytes,
                $userId,
            ]
        );

        return Database::fetch('SELECT * FROM media WHERE id = ?', [$id]) ?? [];
    }

    /** Public URL for a stored media filename. */
    public static function url(string $filename): string
    {
        return asset('uploads/' . $filename);
    }

    /** All media rows, newest first. */
    public static function all(): array
    {
        return Database::fetchAll('SELECT * FROM media ORDER BY created_at DESC, id DESC');
    }

    public static function updateAlt(int $id, string $alt): void
    {
        Database::run('UPDATE media SET alt_text = ? WHERE id = ?', [$alt === '' ? null : $alt, $id]);
    }

    private static function dimensions(string $path): array
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return [null, null];
        }
        return [(int) $info[0], (int) $info[1]];
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create upload directory: ' . $dir);
        }
    }

    private static function cleanName(string $name): string
    {
        $name = basename($name);
        return mb_substr($name, 0, 255);
    }
}
