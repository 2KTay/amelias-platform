<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;

/**
 * Self-service settings with the env-fallback contract:
 *   - While unset in the DB, get() falls back to the env var.
 *   - Once set in the DB, the DB value wins.
 * Secret values (is_secret=1) are AES-GCM encrypted at rest via Crypto and are
 * never returned to public pages, only the masked form is shown in admin.
 */
final class Settings
{
    /** @var array<string,?string>|null in-request cache */
    private static ?array $cache = null;

    /** Map of setting_key => env var name for the fallback. */
    private const ENV_MAP = [
        'stripe.secret_key'      => 'STRIPE_SECRET_KEY',
        'stripe.publishable_key' => 'STRIPE_PUBLISHABLE_KEY',
        'stripe.webhook_secret'  => 'STRIPE_WEBHOOK_SECRET',
        'mail.sendgrid_key'      => 'SENDGRID_API_KEY',
        'mail.from'              => 'MAIL_FROM',
        'twilio.sid'             => 'TWILIO_SID',
        'twilio.token'           => 'TWILIO_TOKEN',
        'twilio.from'            => 'TWILIO_FROM',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $row = self::row($key);
        if ($row !== null) {
            if ((int) $row['is_secret'] === 1) {
                return $row['value_text'] !== null ? Crypto::decrypt((string) $row['value_text']) : null;
            }
            return $row['value_text'];
        }
        // env fallback
        if (isset(self::ENV_MAP[$key])) {
            $env = env(self::ENV_MAP[$key]);
            if ($env !== null && $env !== '') {
                return (string) $env;
            }
        }
        return $default;
    }

    /** True if a secret is configured (DB or env) without revealing it. */
    public static function isConfigured(string $key): bool
    {
        $v = self::get($key);
        return $v !== null && $v !== '';
    }

    /** Masked display value for a (possibly secret) setting. */
    public static function masked(string $key): string
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return '';
        }
        $row = self::row($key);
        return ($row && (int) $row['is_secret'] === 1) ? Crypto::mask($v) : $v;
    }

    public static function set(string $key, ?string $value, bool $isSecret, ?int $userId = null): void
    {
        $stored = ($isSecret && $value !== null && $value !== '') ? Crypto::encrypt($value) : $value;
        Database::run(
            'INSERT INTO settings (setting_key, value_text, is_secret, updated_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value_text = VALUES(value_text), is_secret = VALUES(is_secret), updated_by = VALUES(updated_by)',
            [$key, $stored, $isSecret ? 1 : 0, $userId]
        );
        self::$cache = null;
    }

    /** @return array<string,?string>|null */
    private static function row(string $key): ?array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::fetchAll('SELECT setting_key, value_text, is_secret FROM settings') as $r) {
                self::$cache[$r['setting_key']] = $r;
            }
        }
        return self::$cache[$key] ?? null;
    }
}
