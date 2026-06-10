<?php

declare(strict_types=1);

/**
 * Incumbent-URL 301 redirect map (Task 7.1, C-V7-10/11).
 *
 * Maps every likely-indexed URL from the OLD stack — WordPress/WooCommerce
 * (ameliasaz.com), Square online ordering (amelias-105290.square.site), and Yelp
 * reservations — to its NEW equivalent path on the unified platform. The goal is
 * to preserve local-search position through cutover: every indexed old URL must
 * 301 to the right new page, NOT blanket-redirect to the homepage (a blanket
 * 301-to-home tells search engines the old pages are gone, tanking rankings).
 *
 * SHAPE
 *   Keys   = old path (root-relative, lowercase, no host, no trailing slash
 *            except '/'). Square/Yelp full URLs are included as absolute keys so
 *            the team can also configure host-level redirects at the registrar/CDN
 *            for the off-domain incumbents.
 *   Values = new root-relative path on this platform (matches config/routes.php).
 *
 * USAGE
 *   This is a DATA file. The front controller (public/index.php) is owned by
 *   another track and is not edited here. Two ways to wire it:
 *     1. Apache (preferred for SEO — server-level, fast): translate this map into
 *        `Redirect 301` / `RewriteRule` lines in public/.htaccess. The cutover
 *        runbook (docs/runbooks/cutover.md) documents the exact translation and
 *        ships a generated snippet.
 *     2. PHP (fallback): have index.php `require` this file early and, before
 *        routing, 301 if the current path is a key. (Not wired by this track —
 *        documented for the team.)
 *
 * NOTES
 *   - Blog posts: the live WP blog post slugs are carried as CMS `posts` (type=post)
 *     and resolve at /blog/{slug}, so /YYYY/MM/DD/{slug} and /blog/{slug} both map.
 *   - Q#15 (Amelia's Kiln + EAT by Stacey Weber): DEFAULT = omit / not-linked until
 *     the client decides link-out vs in-platform page. Those old paths are listed
 *     below but COMMENTED OUT so no entry point is silently orphaned to a wrong
 *     page; uncomment + point them once the decision lands (see the runbook).
 *   - Trailing-slash and case normalization is handled in .htaccess canonicalization
 *     (already present); keys here are the normalized form.
 */

return [
    // ---------------------------------------------------------------------
    // WordPress core / brochure pages (ameliasaz.com)
    // ---------------------------------------------------------------------
    '/home'                  => '/',
    '/index.php'             => '/',
    '/index.html'            => '/',
    '/about'                 => '/story',
    '/about-us'              => '/story',
    '/our-story'             => '/story',
    '/story'                 => '/story',
    '/contact'               => '/location',
    '/contact-us'            => '/location',
    '/visit'                 => '/location',
    '/visit-us'              => '/location',
    '/hours'                 => '/location',
    '/location'              => '/location',
    '/directions'            => '/location',
    '/purveyors'             => '/purveyors',
    '/our-purveyors'         => '/purveyors',
    '/sourcing'              => '/purveyors',
    '/farm-to-table'         => '/purveyors',
    '/careers'               => '/careers',
    '/jobs'                  => '/careers',
    '/now-hiring'            => '/careers',
    '/employment'            => '/careers',
    '/privacy'               => '/privacy',
    '/privacy-policy'        => '/privacy',
    '/terms'                 => '/terms',
    '/terms-of-service'      => '/terms',
    '/terms-and-conditions'  => '/terms',
    '/refund-policy'         => '/terms',
    '/cancellation-policy'   => '/terms',

    // ---------------------------------------------------------------------
    // Menu / food
    // ---------------------------------------------------------------------
    '/menu'                  => '/menu',
    '/menus'                 => '/menu',
    '/food-menu'             => '/menu',
    '/dinner-menu'           => '/menu',
    '/dinner'                => '/menu',
    '/lunch-menu'            => '/menu',
    '/lunch'                 => '/menu',
    '/happy-hour'            => '/menu',
    '/breakfast'             => '/menu',
    '/brunch'                => '/menu',
    '/order'                 => '/menu',
    '/order-online'          => '/menu',
    '/online-ordering'       => '/menu',
    '/takeout'               => '/menu',
    '/pickup'                => '/menu',

    // ---------------------------------------------------------------------
    // WooCommerce shop / Market (retail)
    // ---------------------------------------------------------------------
    '/shop'                  => '/market',
    '/store'                 => '/market',
    '/market'                => '/market',
    '/product-category/market'  => '/market',
    '/product-category/retail'  => '/market',
    '/product-category/pantry'  => '/market',
    '/product-category/wine'    => '/market',
    '/cart'                  => '/cart',
    '/checkout'              => '/checkout',
    '/my-account'            => '/account',
    '/my-account/orders'     => '/account',
    // NOTE: individual Woo /product/{slug} pages are mapped by the per-product
    // crosswalk produced during the Woo export (Q#6). Until that export exists,
    // /shop catches the category index; do NOT blanket /product/* to /market
    // (that would be a soft-blanket). The runbook documents generating the
    // /product/{old-slug} => /market/{new-slug} lines from the export.

    // ---------------------------------------------------------------------
    // Gift cards (Square + Woo)
    // ---------------------------------------------------------------------
    '/gift-cards'            => '/gift-cards',
    '/gift-card'             => '/gift-cards',
    '/giftcards'             => '/gift-cards',
    '/gift-certificates'     => '/gift-cards',
    '/product/gift-card'     => '/gift-cards',

    // ---------------------------------------------------------------------
    // Reservations (was Yelp / OpenTable link-outs + any WP page)
    // ---------------------------------------------------------------------
    '/reservations'          => '/reserve',
    '/reservation'           => '/reserve',
    '/reserve'               => '/reserve',
    '/book'                  => '/reserve',
    '/book-a-table'          => '/reserve',
    '/booking'               => '/reserve',

    // ---------------------------------------------------------------------
    // Programs: Catering, Wine Club, Sunday Supper
    // ---------------------------------------------------------------------
    '/catering'              => '/catering',
    '/private-events'        => '/catering',
    '/events/catering'       => '/catering',
    '/party-platters'        => '/catering',
    '/wine-club'             => '/wine-club',
    '/wineclub'              => '/wine-club',
    '/club'                  => '/wine-club',
    '/wine'                  => '/wine-club',
    '/sunday-supper'         => '/sunday-supper',
    '/sunday-suppers'        => '/sunday-supper',
    '/supper'                => '/sunday-supper',
    '/supper-club'           => '/sunday-supper',
    '/events'                => '/sunday-supper',

    // ---------------------------------------------------------------------
    // Blog (WP) — index + common feeds + dated permalinks collapse to /blog;
    // individual posts carry their slug to /blog/{slug} (CMS posts). The
    // per-post lines below are EXAMPLES of the dated-permalink shape; replace
    // with the real exported slugs at cutover (the runbook documents pulling
    // the live slug list and generating these).
    // ---------------------------------------------------------------------
    '/blog'                  => '/blog',
    '/news'                  => '/blog',
    '/journal'               => '/blog',
    '/feed'                  => '/blog',
    '/blog/feed'             => '/blog',
    // Example dated-permalink -> CMS post slug (replace at cutover):
    // '/2024/11/12/fall-harvest-dinner' => '/blog/fall-harvest-dinner',
    // '/2025/02/01/new-spring-menu'     => '/blog/new-spring-menu',

    // ---------------------------------------------------------------------
    // Off-domain incumbents (configure at the registrar/CDN or via a catch host)
    // Square online store + Yelp reservations. Listed as absolute keys so the
    // team has the full inventory in one place; these cannot be served from this
    // app's .htaccess (different host) — see the runbook "off-domain redirects".
    // ---------------------------------------------------------------------
    'https://amelias-105290.square.site/'              => 'https://ameliasaz.com/menu',
    'https://amelias-105290.square.site/order-online'  => 'https://ameliasaz.com/menu',
    'https://amelias-105290.square.site/s/shop'        => 'https://ameliasaz.com/market',
    'https://amelias-105290.square.site/gift-cards'    => 'https://ameliasaz.com/gift-cards',
    'https://www.yelp.com/reservations/amelias-by-eat' => 'https://ameliasaz.com/reserve',

    // ---------------------------------------------------------------------
    // Q#15 — Amelia's Kiln + EAT by Stacey Weber (BLOCKED: link-out vs omit).
    // DEFAULT = omit/not-linked. Keep these COMMENTED until the client decides,
    // so we never 301 to a page that does not yet exist. Once decided:
    //   - link-out:  point to the external URL the client provides.
    //   - in-platform: create the CMS page + map here.
    // ---------------------------------------------------------------------
    // '/kiln'                 => '/???',   // Q#15 — pending decision
    // '/amelias-kiln'         => '/???',   // Q#15 — pending decision
    // '/eat-by-stacey-weber'  => '/???',   // Q#15 — pending decision
    // '/eat'                  => '/???',   // Q#15 — pending decision
    // '/stacey-weber'         => '/story', // candidate if folded into Our Story
];
