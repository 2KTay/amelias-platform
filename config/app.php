<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * Values come from the environment (cPanel host env / .env locally). Secrets are
 * NEVER hardcoded here. The Settings service (Task 1.3) overrides many of these
 * from the DB at runtime: env is the fallback, a set DB value wins.
 */

return [
    'name'  => env('APP_NAME', "Amelia's by EAT"),
    'env'   => env('APP_ENV', 'production'),       // production | staging | local
    'debug' => (bool) env('APP_DEBUG', false),
    'url'   => env('APP_URL', 'https://ameliasaz.com'),
    'key'   => env('APP_KEY', ''),                  // base64 32-byte key for AES-256-GCM (Crypto)

    // Display timezone (storage is always UTC). Arizona = no DST.
    'timezone' => env('APP_TIMEZONE', 'America/Phoenix'),

    'db' => [
        'host'    => env('DB_HOST', '127.0.0.1'),
        'port'    => (int) env('DB_PORT', 3306),
        'name'    => env('DB_NAME', 'amelias'),
        'user'    => env('DB_USER', 'root'),
        'pass'    => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],

    // Feature gates — flipped on once the matching human question is answered.
    // See docs/superpowers/HUMAN-QUESTIONS.md.
    'features' => [
        'sms_enabled'        => (bool) env('FEATURE_SMS', false),         // Q#9 A2P 10DLC
        'wine_dtc_shipping'  => (bool) env('FEATURE_WINE_DTC', false),    // Q#1/#2 wine shipping legality
        'giftcard_import'    => (bool) env('FEATURE_GIFTCARD_IMPORT', false), // Q#4 Square balances
        'stripe_tax'         => (bool) env('FEATURE_STRIPE_TAX', false),  // Q#8 tax approach
    ],

    'paths' => [
        'root'      => ROOT_PATH,
        'templates' => ROOT_PATH . '/templates',
        'uploads'   => env('UPLOADS_PATH', ROOT_PATH . '/uploads'),
        'logs'      => ROOT_PATH . '/logs',
    ],
];
