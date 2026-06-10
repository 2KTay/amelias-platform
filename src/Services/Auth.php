<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;
use Amelias\Support\Session;

/**
 * Authentication + abuse prevention.
 *
 * Staff authenticate against `users`, customers against `customers`. Failed
 * logins are recorded in `login_attempts` and an IP+username locks out after
 * 5 failures in 15 minutes. A generic sliding-window rate limiter (`rate_limits`)
 * backs checkout / password-reset / QR-feedback throttling.
 */
final class Auth
{
    private const MAX_FAILURES = 5;
    private const LOCKOUT_WINDOW_MIN = 15;

    /** Attempt staff login. Returns the user row or null. */
    public static function attemptStaff(string $email, string $password, string $ip): ?array
    {
        $user = Database::fetch('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);
        return self::verify($user, $password, $email, $ip);
    }

    /** Attempt customer login. Returns the customer row or null. */
    public static function attemptCustomer(string $email, string $password, string $ip): ?array
    {
        $customer = Database::fetch('SELECT * FROM customers WHERE email = ?', [$email]);
        if ($customer !== null && ($customer['password_hash'] ?? null) === null) {
            return null; // guest record, no account
        }
        return self::verify($customer, $password, $email, $ip);
    }

    private static function verify(?array $row, string $password, string $email, string $ip): ?array
    {
        if ($row === null || empty($row['password_hash']) || !password_verify($password, $row['password_hash'])) {
            self::record($ip, $email, false);
            return null;
        }
        self::record($ip, $email, true);
        return $row;
    }

    public static function isLockedOut(string $ip, string $email): bool
    {
        $since = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('-' . self::LOCKOUT_WINDOW_MIN . ' minutes')->format('Y-m-d H:i:s');
        $fails = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND username = ? AND successful = 0 AND created_at >= ?',
            [$ip, $email, $since]
        );
        return $fails >= self::MAX_FAILURES;
    }

    private static function record(string $ip, string $email, bool $ok): void
    {
        Database::run(
            'INSERT INTO login_attempts (ip, username, successful) VALUES (?, ?, ?)',
            [$ip, $email, $ok ? 1 : 0]
        );
        if ($ok) {
            // Clear the failure streak on success.
            Database::run('DELETE FROM login_attempts WHERE ip = ? AND username = ? AND successful = 0', [$ip, $email]);
        }
    }

    /**
     * Generic sliding-window rate limit. Returns true if the action is allowed
     * (and increments), false if the limit is exceeded. Used by checkout,
     * password reset, and the QR feedback POST.
     */
    public static function rateLimit(string $key, int $max, int $windowSeconds): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $bucket = $now->setTime((int) $now->format('H'), (int) $now->format('i') - ((int) $now->format('i') % max(1, intdiv($windowSeconds, 60))), 0)
            ->format('Y-m-d H:i:s');

        Database::run(
            'INSERT INTO rate_limits (rl_key, window_start, counter) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE counter = counter + 1',
            [$key, $bucket]
        );
        $count = (int) Database::fetchValue(
            'SELECT counter FROM rate_limits WHERE rl_key = ? AND window_start = ?',
            [$key, $bucket]
        );
        return $count <= $max;
    }

    /** Create a customer (optional account) or return the existing one by email. */
    public static function findOrCreateCustomer(string $email, array $fields = []): array
    {
        $existing = Database::fetch('SELECT * FROM customers WHERE email = ?', [$email]);
        if ($existing !== null) {
            return $existing;
        }
        $id = Database::insert(
            'INSERT INTO customers (email, first_name, last_name, phone) VALUES (?, ?, ?, ?)',
            [$email, $fields['first_name'] ?? null, $fields['last_name'] ?? null, $fields['phone'] ?? null]
        );
        return Database::fetch('SELECT * FROM customers WHERE id = ?', [$id]) ?? ['id' => $id, 'email' => $email];
    }
}
