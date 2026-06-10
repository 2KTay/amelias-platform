<?php

declare(strict_types=1);

/**
 * Front controller.
 *
 * Every non-file request is rewritten here by public/.htaccess (production) or
 * routed here by the PHP built-in server (local dev). Boots the app, starts a
 * hardened session, loads the route table, and dispatches the request. Error
 * and not-found responses render the public error templates.
 */

use Amelias\Http\Request;
use Amelias\Http\Response;
use Amelias\Http\Router;

// Under the PHP built-in server, let real files (CSS/JS/images) be served
// directly instead of routing them through the app.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

/** @var array<string,mixed> $config */
$config = require dirname(__DIR__) . '/includes/bootstrap.php';

// Mount base path (empty at a domain root; a subdir like "/cs/amelias" otherwise).
if (!defined('BASE_PATH')) {
    define('BASE_PATH', rtrim((string) env('BASE_PATH', ''), '/'));
}

// ---- Hardened session ----
$https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => BASE_PATH === '' ? '/' : BASE_PATH,
    'httponly' => true,
    'secure'   => $https,
    'samesite' => 'Lax',
]);
session_name('amelias_session');
session_start();

$response = new Response();
$router   = new Router();

// Not-found / method-not-allowed render the matching public error page.
$router->setNotFound(static function (Request $request, int $status) use ($response): void {
    $template = $status === 405 ? 'errors/405' : 'errors/404';
    $response->view($template, ['title' => $status === 405 ? 'Not allowed' : 'Not found', 'path' => $request->path], 'layouts/public', $status);
});

// Uncaught handler errors log server-side and render a generic 500 (never leak).
$router->setErrorHandler(static function (\Throwable $e, Request $request) use ($response, $config): void {
    error_log('[app] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!empty($config['debug'])) {
        $response->html('<pre>' . e((string) $e) . '</pre>', 500);
        return;
    }
    $response->view('errors/500', ['title' => 'Something went wrong'], 'layouts/public', 500);
});

// Register the route table, then dispatch.
(require dirname(__DIR__) . '/config/routes.php')($router);
$router->dispatch(Request::fromGlobals());
