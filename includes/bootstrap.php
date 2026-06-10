<?php

declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Loaded by the front controller (public/index.php) and by CLI scripts
 * (db/migrate.php, cron/*). Establishes paths, env, autoloading, error
 * handling, then returns the loaded config array.
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

// 1. Helpers (defines env(), e(), cents(), fmt_money(), csrf_token(), ...).
require_once ROOT_PATH . '/includes/helpers.php';

// 2. Load .env (key=value lines) into the environment if present. Real secrets
//    on cPanel come from the host env / SetEnv; .env is the local-dev fallback.
load_env(ROOT_PATH . '/.env');

// 3. Autoloading. Prefer Composer's autoloader (Stripe, PHPMailer, PSR-4 src/).
//    Fall back to a built-in PSR-4 loader for the first-party Amelias\ namespace
//    so the app boots for first-party code even before `composer install` runs.
$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Amelias\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = ROOT_PATH . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

// 4. Load config.
$config = require ROOT_PATH . '/config/app.php';

// 5. Error handling by environment. Never leak errors to guests in production.
date_default_timezone_set('UTC'); // storage/compute in UTC; display via fmt_* (America/Phoenix)
if (($config['env'] ?? 'production') === 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', ROOT_PATH . '/logs/php-error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

return $config;
