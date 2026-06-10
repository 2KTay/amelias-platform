<?php

declare(strict_types=1);

namespace Amelias\Services;

/**
 * Authenticated symmetric encryption for secrets at rest (AES-256-GCM).
 *
 * The key lives ONLY in env (APP_KEY, base64-encoded 32 bytes), never in the
 * DB. Each ciphertext carries its own random IV and the GCM auth tag, so a
 * tampered value fails to decrypt. Stored as base64(iv | tag | ciphertext).
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    /** Resolve the 32-byte key from APP_KEY ("base64:..." or raw base64). */
    private static function key(): string
    {
        $raw = (string) env('APP_KEY', '');
        if (str_starts_with($raw, 'base64:')) {
            $raw = substr($raw, 7);
        }
        $key = base64_decode($raw, true);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('APP_KEY must be a base64-encoded 32-byte key. Generate: php -r "echo \'base64:\'.base64_encode(random_bytes(32));"');
        }
        return $key;
    }

    public static function encrypt(string $plaintext): string
    {
        $iv = random_bytes(self::IV_LEN);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < self::IV_LEN + self::TAG_LEN) {
            return null;
        }
        $iv = substr($raw, 0, self::IV_LEN);
        $tag = substr($raw, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($raw, self::IV_LEN + self::TAG_LEN);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plaintext === false ? null : $plaintext;
    }

    /** Mask a secret for display: keep the last 4 chars. */
    public static function mask(string $secret): string
    {
        $len = strlen($secret);
        if ($len <= 4) {
            return str_repeat('•', max(3, $len));
        }
        return str_repeat('•', 6) . substr($secret, -4);
    }
}
