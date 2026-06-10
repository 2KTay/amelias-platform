<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\AlcoholCompliance;
use Amelias\Services\Availability;

/**
 * The Market (screen 9, Task 4.2).
 *
 * A retail storefront for market goods (products.type='market'), the wine
 * wall, grab-and-go cooler, and home-and-gifting shelves. Market items share
 * the ordering cart: each card posts to /cart/add with the same form shape the
 * menu uses (_csrf, product_id, quantity). Finite goods show a reservable-stock
 * indicator from Availability; sold-out items can't be added.
 *
 * Alcohol items (is_alcohol) are PICKUP-ONLY while the DTC gate is closed
 * (Q#1/#2): AlcoholCompliance::isPickupOnly() is true for every bottle today,
 * so no ship option is ever rendered and a 21+ note is shown.
 *
 * Money is integer cents; escape on output; CSRF on the add form.
 */
final class MarketController extends Controller
{
    public function index(Request $request): void
    {
        // Market products grouped by their first mapped category, in sort order.
        $rows = Database::fetchAll(
            "SELECT p.id, p.slug, p.name, p.description, p.price_cents, p.image_path,
                    p.is_86, p.tracks_inventory, p.is_alcohol, p.ships_dtc,
                    c.id AS category_id, c.name AS category, c.sort AS cat_sort
               FROM products p
               LEFT JOIN product_category_map pcm ON pcm.product_id = p.id
               LEFT JOIN categories c ON c.id = pcm.category_id AND c.is_active = 1
              WHERE p.is_active = 1 AND p.type = 'market'
              ORDER BY c.sort, c.name, p.sort, p.name"
        );

        // Group into category => [items], decorating each with stock + compliance.
        $grouped = [];
        foreach ($rows as $r) {
            $pid = (int) $r['id'];
            $category = $r['category'] ?? 'The Market';

            $tracks = (int) $r['tracks_inventory'] === 1;
            $stock = $tracks ? Availability::reservableStock($pid) : null;

            $r['stock']        = $stock;                 // null = not tracked (unlimited)
            $r['out_of_stock'] = $tracks && $stock !== null && $stock <= 0;
            $r['low_stock']    = $tracks && $stock !== null && $stock > 0 && $stock <= 5;
            $r['pickup_only']  = (int) $r['is_alcohol'] === 1 && AlcoholCompliance::isPickupOnly($pid);
            $r['age_gate']     = (int) $r['is_alcohol'] === 1;

            $grouped[$category][] = $r;
        }

        $this->viewPublic('public/market', [
            'title'      => 'The Market',
            'bodyClass'  => 'has-grain',
            'styles'     => ['pages/market.css'],
            'grouped'    => $grouped,
            'dtcEnabled' => AlcoholCompliance::dtcShippingEnabled(),
            'flash'      => $this->flash(),
        ]);
    }

    /** Product detail, reuses the menu item add-to-cart pattern. */
    public function item(Request $request): void
    {
        $slug = (string) $request->param('slug');
        $product = Database::fetch(
            "SELECT * FROM products WHERE slug = ? AND is_active = 1 AND type = 'market'",
            [$slug]
        );
        if ($product === null) {
            $this->viewPublic('errors/404', ['path' => $request->path], 404);
            return;
        }
        $pid = (int) $product['id'];

        $variants = Database::fetchAll(
            'SELECT id, name, price_cents FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY sort',
            [$pid]
        );

        $tracks = (int) $product['tracks_inventory'] === 1;
        $stock = $tracks ? Availability::reservableStock($pid) : null;

        $this->viewPublic('public/market-item', [
            'title'       => (string) $product['name'],
            'bodyClass'   => 'has-grain',
            'styles'      => ['pages/market.css'],
            'product'     => $product,
            'variants'    => $variants,
            'stock'       => $stock,
            'outOfStock'  => $tracks && $stock !== null && $stock <= 0,
            'lowStock'    => $tracks && $stock !== null && $stock > 0 && $stock <= 5,
            'pickupOnly'  => (int) $product['is_alcohol'] === 1 && AlcoholCompliance::isPickupOnly($pid),
            'ageGate'     => (int) $product['is_alcohol'] === 1,
            'allowedStates' => AlcoholCompliance::allowedStates($pid),
        ]);
    }

    /** Read + clear a one-shot flash payload. */
    private function flash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return is_array($flash) ? $flash : null;
    }
}
