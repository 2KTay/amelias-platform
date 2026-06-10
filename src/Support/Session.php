<?php

declare(strict_types=1);

namespace Amelias\Support;

/**
 * Session hardening + helpers.
 *
 * The cookie flags are set in the front controller; this class adds the
 * security behaviors: regenerate the ID on privilege change (login), bind the
 * session to a UA/IP fingerprint to blunt fixation/hijack, and expose the
 * current actor + demo-host detection.
 */
final class Session
{
    /** Regenerate the session ID (call on login / role change). */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Bind the session to a coarse fingerprint; returns false if it changed. */
    public static function guardFingerprint(string $ip, string $userAgent): bool
    {
        $fp = hash('sha256', $userAgent . '|' . self::ipNetwork($ip));
        if (!isset($_SESSION['_fp'])) {
            $_SESSION['_fp'] = $fp;
            return true;
        }
        return hash_equals($_SESSION['_fp'], $fp);
    }

    /** /24 (or /48 for v6) network so a roaming mobile IP doesn't log users out. */
    private static function ipNetwork(string $ip): string
    {
        if (str_contains($ip, ':')) {
            return implode(':', array_slice(explode(':', $ip), 0, 3));
        }
        $parts = explode('.', $ip);
        return implode('.', array_slice($parts, 0, 3));
    }

    public static function loginUser(array $user): void
    {
        self::regenerate();
        $_SESSION['user'] = [
            'id'   => (int) $user['id'],
            'name' => $user['name'] ?? '',
            'role' => $user['role'] ?? 'staff',
        ];
        unset($_SESSION['customer']); // never both at once
    }

    public static function loginCustomer(array $customer): void
    {
        self::regenerate();
        $_SESSION['customer'] = [
            'id'    => (int) $customer['id'],
            'email' => $customer['email'] ?? '',
            'name'  => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function customer(): ?array
    {
        return $_SESSION['customer'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user']['role'] ?? null;
    }

    /** Role gate: owner > manager > staff. */
    public static function hasRole(string ...$allowed): bool
    {
        $role = self::role();
        return $role !== null && in_array($role, $allowed, true);
    }

    /**
     * Demo-host detection (env AND hostname), so demo credentials only render on
     * demo/staging hosts, never on the production domain (security-demo-credentials).
     */
    public static function isDemoHost(): bool
    {
        $env  = (string) (config('env') ?? 'production');
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($env === 'production' && !str_contains($host, 'parityrfp.com') && !str_contains($host, 'localhost') && !str_contains($host, '127.0.0.1')) {
            return false;
        }
        return $env !== 'production'
            || str_contains($host, 'parityrfp.com')
            || str_contains($host, 'localhost')
            || str_contains($host, '127.0.0.1');
    }
}
