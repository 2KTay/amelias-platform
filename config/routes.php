<?php

declare(strict_types=1);

use Amelias\Http\Router;

/**
 * Route table. Returns a closure that registers every route on the Router.
 *
 * Handlers are "Controller@method" (resolved under Amelias\Controllers\, or a
 * fully-qualified Amelias\Controllers\Admin\… class). Public routes first,
 * then ordering, reservations, programs, feedback, account, admin, webhooks.
 */
return static function (Router $r): void {
    // ---- Public marketing / CMS (Task 1.6) ----
    $r->get('/', 'PublicController@home', 'home');
    $r->get('/story', 'PublicController@story', 'story');
    $r->get('/location', 'PublicController@location', 'location');
    $r->get('/purveyors', 'PublicController@purveyors', 'purveyors');
    $r->get('/careers', 'PublicController@careers', 'careers');
    $r->get('/privacy', 'PublicController@privacy', 'privacy');
    $r->get('/terms', 'PublicController@terms', 'terms');
    $r->get('/blog', 'PublicController@blog', 'blog');
    $r->get('/blog/{slug}', 'PublicController@post', 'post');

    // ---- Menu + ordering (Phase 2) ----
    $r->get('/menu', 'MenuController@index', 'menu');
    $r->get('/menu/{slug}', 'MenuController@item', 'menu.item');
    $r->map(['GET', 'POST'], '/cart', 'CartController@index', 'cart');
    $r->post('/cart/add', 'CartController@add', 'cart.add');
    $r->post('/cart/update', 'CartController@update', 'cart.update');
    $r->post('/cart/remove', 'CartController@remove', 'cart.remove');
    $r->get('/checkout', 'CheckoutController@show', 'checkout');
    $r->post('/checkout', 'CheckoutController@place', 'checkout.place');
    $r->get('/order/{token}', 'OrderController@show', 'order.show');
    $r->post('/order/{token}/cancel', 'OrderController@cancel', 'order.cancel');

    // ---- Gift cards / Market (Phase 2 / 4) ----
    $r->map(['GET', 'POST'], '/gift-cards', 'GiftCardController@index', 'giftcards');
    $r->get('/market', 'MarketController@index', 'market');
    $r->get('/market/{slug}', 'MarketController@item', 'market.item');

    // ---- Reservations (Phase 3) ----
    $r->map(['GET', 'POST'], '/reserve', 'ReservationController@index', 'reserve');
    $r->get('/reserve/availability', 'ReservationController@availability', 'reserve.availability');
    $r->map(['GET', 'POST'], '/reservation/{token}', 'ReservationController@manage', 'reservation.manage');

    // ---- Programs (Phase 4) ----
    $r->map(['GET', 'POST'], '/catering', 'CateringController@index', 'catering');
    $r->get('/wine-club', 'WineClubController@index', 'wineclub');
    $r->post('/wine-club/join', 'WineClubController@join', 'wineclub.join');
    $r->map(['GET', 'POST'], '/wine-club/portal', 'WineClubController@portal', 'wineclub.portal');
    $r->get('/sunday-supper', 'SupperController@index', 'supper');
    $r->post('/sunday-supper/{id}/buy', 'SupperController@buy', 'supper.buy');

    // ---- QR feedback (Phase 5) ----
    $r->map(['GET', 'POST'], '/feedback', 'FeedbackController@index', 'feedback');

    // ---- Customer account (Phase 6.6) ----
    $r->map(['GET', 'POST'], '/login', 'AuthController@login', 'login');
    $r->post('/logout', 'AuthController@logout', 'logout');
    $r->map(['GET', 'POST'], '/register', 'AuthController@register', 'register');
    $r->map(['GET', 'POST'], '/reset-password', 'AuthController@reset', 'reset');
    $r->get('/account', 'AccountController@index', 'account');
    $r->post('/account/reorder/{id}', 'AccountController@reorder', 'account.reorder');

    // ---- Admin (Phases 1.3, 1.6, 2.5, 3.3, 5, 6) ----
    $r->map(['GET', 'POST'], '/admin/login', 'Admin\\AuthController@login', 'admin.login');
    $r->post('/admin/logout', 'Admin\\AuthController@logout', 'admin.logout');
    $r->get('/admin', 'Admin\\DashboardController@index', 'admin.dashboard');
    $r->map(['GET', 'POST'], '/admin/settings', 'Admin\\SettingsController@index', 'admin.settings');
    $r->post('/admin/settings/test', 'Admin\\SettingsController@test', 'admin.settings.test');
    $r->map(['GET', 'POST'], '/admin/content', 'Admin\\ContentController@index', 'admin.content');
    $r->map(['GET', 'POST'], '/admin/menu', 'Admin\\MenuController@index', 'admin.menu');
    $r->map(['GET', 'POST'], '/admin/menu/new', 'Admin\\MenuController@edit', 'admin.menu.new');
    $r->map(['GET', 'POST'], '/admin/menu/{id}', 'Admin\\MenuController@edit', 'admin.menu.edit');
    $r->get('/admin/orders', 'Admin\\OrderQueueController@index', 'admin.orders');
    $r->post('/admin/orders/{id}/status', 'Admin\\OrderQueueController@updateStatus', 'admin.orders.status');
    $r->map(['GET', 'POST'], '/admin/reservations', 'Admin\\ReservationsController@index', 'admin.reservations');
    $r->map(['GET', 'POST'], '/admin/inventory', 'Admin\\InventoryController@index', 'admin.inventory');
    $r->get('/admin/customers', 'Admin\\CustomersController@index', 'admin.customers');
    $r->get('/admin/reports', 'Admin\\ReportsController@index', 'admin.reports');
    $r->map(['GET', 'POST'], '/admin/users', 'Admin\\UsersController@index', 'admin.users');
    $r->get('/admin/feedback', 'Admin\\FeedbackController@index', 'admin.feedback');

    // ---- Stripe webhook (Task 1.5) — raw body, CSRF-exempt, signature-verified ----
    $r->post('/webhooks/stripe', 'StripeWebhookController@handle', 'webhook.stripe');
};
