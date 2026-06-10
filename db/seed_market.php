<?php

declare(strict_types=1);

/**
 * Market + Sunday Supper sample seed (CLI):  php db/seed_market.php
 *
 * Phase 4 helper, separate from db/seed.php (do not edit that file). Idempotent
 * — keyed on slugs. Adds:
 *   - three market categories (Wine, Grab & Go, Home & Gifting)
 *   - a handful of sample market retail products (type='market'), including a
 *     couple of alcohol bottles (is_alcohol=1) so the pickup-only DTC gate is
 *     visible on the Market page; product_shippable_states is intentionally
 *     left EMPTY (the wine DTC gate stays closed — Q#1/#2)
 *   - inventory rows for the finite goods (reservable-stock indicators)
 *   - a future Sunday Supper booking_slot tied to the seeded event product, so
 *     /sunday-supper shows a bookable date with seats-left
 *
 * Money is integer cents. Re-running refreshes the rows without duplicating.
 */

use Amelias\Database\Database;

if (!defined('ROOT_PATH')) {
    require dirname(__DIR__) . '/includes/bootstrap.php';
}

/** Upsert helper keyed on a unique column (mirrors db/seed.php). */
function market_upsert(string $table, array $row, string $uniqueCol): void
{
    $cols = array_keys($row);
    $place = implode(',', array_map(static fn ($c) => ":$c", $cols));
    $updates = implode(',', array_map(
        static fn ($c) => "`$c`=VALUES(`$c`)",
        array_filter($cols, static fn ($c) => $c !== $uniqueCol)
    ));
    $sql = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES ($place)"
        . ($updates ? " ON DUPLICATE KEY UPDATE $updates" : '');
    Database::run($sql, $row);
}

// ---------------------------------------------------------------------------
// 1. Market categories.
// ---------------------------------------------------------------------------
$categories = [
    ['slug' => 'market-wine',   'name' => 'Wine',           'kind' => 'wine',   'sort' => 10],
    ['slug' => 'market-grabgo', 'name' => 'Grab & Go',      'kind' => 'market', 'sort' => 20],
    ['slug' => 'market-home',   'name' => 'Home & Gifting', 'kind' => 'market', 'sort' => 30],
];
foreach ($categories as $c) {
    market_upsert('categories', $c + ['is_active' => 1], 'slug');
}
$catId = static fn (string $slug): int => (int) Database::fetchValue('SELECT id FROM categories WHERE slug = ?', [$slug]);

// ---------------------------------------------------------------------------
// 2. Sample market products. Alcohol items carry is_alcohol=1 and ships_dtc=0
//    (pickup-only). No product_shippable_states rows are added — the DTC gate
//    stays closed (Q#1/#2), so AlcoholCompliance reports pickup-only.
// ---------------------------------------------------------------------------
$products = [
    // slug, name, desc, price_cents, category, is_alcohol, tracks_inventory, stock
    ['organic-natural-rose', 'Natural Rosé, Provence', 'A pale, dry, mineral natural rosé from the wine wall — wild strawberry and citrus pith.', 2800, 'market-wine', 1, 1, 18],
    ['biodynamic-red-blend', 'Biodynamic Red Blend', 'Low-intervention, biodynamic red chosen by our in-house somms — bright, savory, easy.', 3200, 'market-wine', 1, 1, 12],
    ['house-granola', "Amelia's House Granola", 'Our scratch-made granola — the same blend we serve on the parfaits, bagged for home.', 1200, 'market-grabgo', 0, 1, 40],
    ['bone-broth-quart', 'Organic Bone Broth, 1 qt', 'Seasonal, organic, scratch-made bone broth — pulled straight from the freezer.', 1600, 'market-grabgo', 0, 1, 24],
    ['specialty-finishing-salt', 'Specialty Finishing Salt', 'A jar of the finishing salt we cook and season with daily.', 1400, 'market-home', 0, 1, 30],
    ['amelias-candle', "Amelia's Custom Candle", 'Custom-scented candle, poured for the shop — warm, herbal, a little citrus.', 2600, 'market-home', 0, 1, 20],
];

foreach ($products as [$slug, $name, $desc, $price, $cat, $isAlcohol, $tracks, $stock]) {
    market_upsert('products', [
        'slug'            => $slug,
        'name'            => $name,
        'description'     => $desc,
        'type'            => 'market',
        'tax_category'    => $isAlcohol ? 'retail_goods' : 'retail_goods',
        'price_cents'     => $price,
        'tracks_inventory' => $tracks,
        'is_alcohol'      => $isAlcohol,
        'ships_dtc'       => 0,            // gate closed (Q#1/#2)
        'day_part'        => 'all_day',
        'is_active'       => 1,
    ], 'slug');

    $pid = (int) Database::fetchValue('SELECT id FROM products WHERE slug = ?', [$slug]);
    Database::run(
        'INSERT INTO product_category_map (product_id, category_id) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE product_id = product_id',
        [$pid, $catId($cat)]
    );
    if ($tracks) {
        market_upsert('inventory', [
            'product_id'          => $pid,
            'stock'               => $stock,
            'low_stock_threshold' => 5,
        ], 'product_id');
    }
}

// ---------------------------------------------------------------------------
// 3. A future Sunday Supper booking_slot tied to the seeded event product, so
//    /sunday-supper has a bookable date with seats-left.
// ---------------------------------------------------------------------------
$supperPid = Database::fetchValue("SELECT id FROM products WHERE slug = 'sunday-supper'", []);
$eventResource = Database::fetchValue("SELECT id FROM booking_resources WHERE type = 'event' ORDER BY id LIMIT 1", []);

if ($supperPid && $eventResource) {
    $supperPid = (int) $supperPid;
    $eventResource = (int) $eventResource;

    // Next-month slot at 6:30pm Phoenix, stored UTC. Phoenix is UTC-7 (no DST),
    // so 18:30 local = 01:30 UTC the next day.
    $tz = new DateTimeZone('America/Phoenix');
    $local = (new DateTimeImmutable('first day of next month 18:30', $tz));
    // Roll to the next Sunday on/after that date.
    while ((int) $local->format('w') !== 0) {
        $local = $local->modify('+1 day');
    }
    $utc = $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

    $exists = Database::fetchValue(
        'SELECT id FROM booking_slots WHERE resource_id = ? AND slot_start = ?',
        [$eventResource, $utc]
    );
    if (!$exists) {
        Database::run(
            'INSERT INTO booking_slots (resource_id, slot_start, capacity, booked_count, is_blackout, event_product_id)
             VALUES (?, ?, ?, 0, 0, ?)',
            [$eventResource, $utc, 20, $supperPid]
        );
    }
}

$marketCount = (int) Database::fetchValue("SELECT COUNT(*) FROM products WHERE type = 'market'", []);
$supperSlots = (int) Database::fetchValue('SELECT COUNT(*) FROM booking_slots WHERE event_product_id IS NOT NULL', []);
fwrite(STDOUT, "Market seed complete: {$marketCount} market products, {$supperSlots} supper slot(s).\n");
