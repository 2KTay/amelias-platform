<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Support\Seo;

/**
 * Public menu browse + item detail (screens 4–5).
 *
 * Renders the seeded catalog as day-part tabs (Day / Evening / Happy Hour /
 * Catering), each with its categories and items. Ordering/add-to-cart wiring
 * and 86/availability gating land with the cart (Task 2.2/2.4).
 */
final class MenuController extends Controller
{
    /** Day-part tabs in display order. */
    private const DAY_PARTS = [
        'day'        => 'Day',
        'pm'         => 'Evening',
        'happy_hour' => 'Happy Hour',
        'catering'   => 'Catering',
    ];

    public function index(Request $request): void
    {
        $rows = Database::fetchAll(
            "SELECT p.id, p.slug, p.name, p.description, p.price_cents, p.day_part, p.is_86, p.image_path,
                    c.name AS category, c.sort AS cat_sort
             FROM products p
             LEFT JOIN product_category_map pcm ON pcm.product_id = p.id
             LEFT JOIN categories c ON c.id = pcm.category_id
             WHERE p.is_active = 1 AND p.type IN ('food','catering')
             ORDER BY p.day_part, c.sort, p.sort, p.name"
        );

        // Group: day_part => category => [items]; collect variant prices for catering.
        $variants = Database::fetchAll(
            "SELECT pv.product_id, pv.name, pv.price_cents FROM product_variants pv
             JOIN products p ON p.id = pv.product_id WHERE pv.is_active = 1 ORDER BY pv.sort"
        );
        $variantsByProduct = [];
        foreach ($variants as $v) {
            $variantsByProduct[(int) $v['product_id']][] = $v;
        }

        // Dietary tags (V / GF …) per product, rendered as menu-item__tags.
        $tags = Database::fetchAll(
            "SELECT pdm.product_id, dt.slug, dt.label FROM product_dietary_map pdm
             JOIN dietary_tags dt ON dt.id = pdm.tag_id ORDER BY dt.slug"
        );
        $tagsByProduct = [];
        foreach ($tags as $t) {
            $tagsByProduct[(int) $t['product_id']][] = $t;
        }

        $grouped = [];
        foreach ($rows as $r) {
            $dp = $r['day_part'];
            $cat = $r['category'] ?? 'Menu';
            $r['variants'] = $variantsByProduct[(int) $r['id']] ?? [];
            $r['dietary_tags'] = $tagsByProduct[(int) $r['id']] ?? [];
            $grouped[$dp][$cat][] = $r;
        }

        // Build the menu JSON-LD from the Day menu sections.
        $ldSections = [];
        foreach ($grouped['day'] ?? [] as $cat => $items) {
            $ldSections[$cat] = array_map(static fn ($i) => [
                'name' => $i['name'], 'description' => $i['description'], 'price_cents' => (int) $i['price_cents'],
            ], $items);
        }

        $this->viewPublic('public/menu', [
            'title'     => 'Menu',
            'styles'    => ['pages/menu.css'],
            'dayParts'  => self::DAY_PARTS,
            'grouped'   => $grouped,
            'jsonLd'    => $ldSections ? Seo::menu($ldSections) : null,
        ]);
    }

    public function item(Request $request, array $params): void
    {
        $slug = (string) $request->param('slug');
        $product = Database::fetch('SELECT * FROM products WHERE slug = ? AND is_active = 1', [$slug]);
        if ($product === null) {
            $this->viewPublic('errors/404', ['path' => $request->path], 404);
            return;
        }
        $variants = Database::fetchAll(
            'SELECT id, name, price_cents FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY sort',
            [(int) $product['id']]
        );
        // Modifier groups + their modifiers for this product.
        $groups = Database::fetchAll(
            'SELECT mg.id, mg.name, mg.min_select, mg.max_select
             FROM modifier_groups mg
             JOIN product_modifier_map pmm ON pmm.group_id = mg.id
             WHERE pmm.product_id = ? ORDER BY pmm.sort, mg.sort',
            [(int) $product['id']]
        );
        foreach ($groups as &$g) {
            $g['modifiers'] = Database::fetchAll(
                'SELECT id, name, price_delta_cents FROM modifiers WHERE group_id = ? AND is_active = 1 ORDER BY sort',
                [(int) $g['id']]
            );
        }
        unset($g);

        // Dietary tags (V / GF …) for this product, rendered as item__tags.
        $dietaryTags = Database::fetchAll(
            'SELECT dt.slug, dt.label FROM product_dietary_map pdm
             JOIN dietary_tags dt ON dt.id = pdm.tag_id
             WHERE pdm.product_id = ? ORDER BY dt.slug',
            [(int) $product['id']]
        );

        $this->viewPublic('public/item', [
            'title'    => $product['name'],
            'styles'   => ['pages/menu.css'],
            'product'  => $product,
            'variants' => $variants,
            'groups'   => $groups,
            'dietaryTags' => $dietaryTags,
        ]);
    }
}
