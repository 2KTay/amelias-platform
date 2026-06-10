<?php

declare(strict_types=1);

/**
 * Global helper functions.
 *
 * Money is ALWAYS integer cents. Tax rates are ALWAYS integer basis points
 * (1 bp = 0.01%). Timestamps are stored/computed in UTC and displayed in
 * America/Phoenix (no DST). These helpers are the single place those rules live.
 */

/** Display timezone — Arizona does not observe DST, so this is stable year-round. */
if (!defined('FMT_TZ')) {
    define('FMT_TZ', 'America/Phoenix');
}

if (!function_exists('env')) {
    /**
     * Read an environment variable with an optional default.
     * Casts the common literals true/false/null/empty.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('load_env')) {
    /** Minimal .env loader (KEY=VALUE per line, # comments). Does not overwrite real env. */
    function load_env(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value);
            // Strip surrounding quotes.
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
                $value = substr($value, 1, -1);
            }
            if ($key !== '' && getenv($key) === false && !isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

if (!function_exists('e')) {
    /** HTML-escape for output. Use on EVERY dynamic value rendered into HTML. */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('cents')) {
    /** Convert a dollar amount (string/float) to integer cents, half-up. */
    function cents(int|float|string $dollars): int
    {
        return (int) round(((float) $dollars) * 100);
    }
}

if (!function_exists('fmt_money')) {
    /** Format integer cents as a USD display string, e.g. 1299 -> "$12.99". */
    function fmt_money(int $cents, bool $symbol = true): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs  = abs($cents);
        $out  = number_format($abs / 100, 2);
        return $sign . ($symbol ? '$' : '') . $out;
    }
}

if (!function_exists('bps')) {
    /**
     * Apply a basis-point rate to an integer-cents amount, returning integer cents.
     * 8.6% tax = 860 bps. tax_cents = round(amount_cents * bps / 10000).
     */
    function bps(int $amountCents, int $basisPoints): int
    {
        return (int) round($amountCents * $basisPoints / 10000);
    }
}

if (!function_exists('fmt_date')) {
    /** Format a UTC timestamp string for display in America/Phoenix. */
    function fmt_date(?string $utc, string $format = 'M j, Y g:i A'): string
    {
        if ($utc === null || $utc === '') {
            return '';
        }
        try {
            $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
            return $dt->setTimezone(new DateTimeZone(FMT_TZ))->format($format);
        } catch (Exception) {
            return '';
        }
    }
}

if (!function_exists('csrf_token')) {
    /** Return (creating if needed) the per-session CSRF token. */
    function csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_field')) {
    /** Hidden input carrying the CSRF token for inclusion in forms. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    /** Constant-time CSRF check against the session token. */
    function csrf_verify(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['_csrf']) || $token === null) {
            return false;
        }
        return hash_equals($_SESSION['_csrf'], $token);
    }
}

if (!function_exists('old')) {
    /** Repopulate a form field after a validation redirect (flash old input). */
    function old(string $key, string $default = ''): string
    {
        return e((string) ($_SESSION['_old'][$key] ?? $default));
    }
}

if (!function_exists('base_path')) {
    /**
     * Web base path the app is mounted under (e.g. "" at a domain root, or
     * "/cs/amelias" on the parityrfp.com staging subdirectory). Auto-detected
     * in the front controller from SCRIPT_NAME and exposed via the BASE_PATH
     * constant; empty on CLI.
     */
    function base_path(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : '';
    }
}

if (!function_exists('url')) {
    /** Build an internal URL, prefixed with the mount base path. */
    function url(string $path = '/'): string
    {
        $base = base_path();
        if ($path === '' || $path === '/') {
            return $base === '' ? '/' : $base;
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /** Build a URL to a file under /assets, prefixed with the mount base path. */
    function asset(string $path): string
    {
        return base_path() . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('config')) {
    /** Dot-path read of the loaded config array (set by bootstrap). */
    function config(string $key, mixed $default = null): mixed
    {
        static $config = null;
        if ($config === null) {
            $config = require ROOT_PATH . '/config/app.php';
        }
        $value = $config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
