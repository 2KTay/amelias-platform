<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;

/**
 * Session-backed shopping cart (Task 2.1).
 *
 * The canonical cart shape stored in $_SESSION['cart'] is:
 *   [
 *     'items' => [
 *        '<lineKey>' => [
 *           'product_id'   => int,
 *           'variant_id'   => int|null,
 *           'quantity'     => int,
 *           'modifiers'    => list<int>,   // modifier ids
 *           'instructions' => string,
 *        ], ...
 *     ],
 *     'tip_cents'      => int,
 *     'tip_mode'      => string,           // 'preset:18' | 'custom' | 'none'
 *     'promo_code'     => string|null,
 *     'gift_card_code' => string|null,
 *     'pickup_slot_id' => int|null,
 *     'pickup_mode'    => string,          // 'asap' | 'scheduled'
 *   ]
 *
 * normalize() tolerates the legacy simple [product_id => qty] map that the
 * account "reorder" flow writes, upgrading it in place on first read.
 *
 * Money is integer cents. lineItems() resolves LIVE product + modifier prices
 * and names for DISPLAY only, the authoritative snapshot is taken at order
 * creation by HoldManager. Never trust the session for money owed.
 */
final class Cart
{
    /** Build a Cart bound to the current session (creating the bucket if absent). */
    public static function fromSession(): self
    {
        if (session_status() === PHP_SESSION_ACTIVE && !isset($_SESSION['cart'])) {
            $_SESSION['cart'] = self::blank();
        }
        return new self();
    }

    /** @return array<string,mixed> */
    private static function blank(): array
    {
        return [
            'items'          => [],
            'tip_cents'      => 0,
            'tip_mode'       => 'none',
            'promo_code'     => null,
            'gift_card_code' => null,
            'pickup_slot_id' => null,
            'pickup_mode'    => 'asap',
        ];
    }

    /** Read-and-normalize the raw session cart into the canonical shape. */
    public function state(): array
    {
        return $this->normalize($_SESSION['cart'] ?? []);
    }

    private function save(array $state): void
    {
        $_SESSION['cart'] = $state;
    }

    /**
     * Coerce any historical shape into the canonical structure.
     *
     * Accepts:
     *   - the canonical array (passthrough),
     *   - the legacy simple map [product_id => qty] from reorder.
     *
     * @param mixed $raw
     * @return array<string,mixed>
     */
    public function normalize(mixed $raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return self::blank();
        }

        // Canonical shape already.
        if (isset($raw['items']) && is_array($raw['items'])) {
            $state = self::blank();
            foreach ($raw['items'] as $key => $line) {
                if (!is_array($line) || empty($line['product_id'])) {
                    continue;
                }
                $pid = (int) $line['product_id'];
                $state['items'][(string) $key] = [
                    'product_id'   => $pid,
                    'variant_id'   => isset($line['variant_id']) && $line['variant_id'] !== null ? (int) $line['variant_id'] : null,
                    'quantity'     => max(1, (int) ($line['quantity'] ?? 1)),
                    'modifiers'    => array_values(array_map('intval', (array) ($line['modifiers'] ?? []))),
                    'instructions' => trim((string) ($line['instructions'] ?? '')),
                ];
            }
            $state['tip_cents']      = max(0, (int) ($raw['tip_cents'] ?? 0));
            $state['tip_mode']       = (string) ($raw['tip_mode'] ?? 'none');
            $state['promo_code']     = isset($raw['promo_code']) && $raw['promo_code'] !== '' ? (string) $raw['promo_code'] : null;
            $state['gift_card_code'] = isset($raw['gift_card_code']) && $raw['gift_card_code'] !== '' ? (string) $raw['gift_card_code'] : null;
            $state['pickup_slot_id'] = isset($raw['pickup_slot_id']) && $raw['pickup_slot_id'] !== null ? (int) $raw['pickup_slot_id'] : null;
            $state['pickup_mode']    = (string) ($raw['pickup_mode'] ?? 'asap');
            return $state;
        }

        // Legacy simple map [product_id => qty].
        $state = self::blank();
        foreach ($raw as $pid => $qty) {
            if (!is_numeric($pid)) {
                continue;
            }
            $pid = (int) $pid;
            $qty = max(1, (int) $qty);
            if ($pid <= 0) {
                continue;
            }
            $key = self::lineKey($pid, null, []);
            $state['items'][$key] = [
                'product_id'   => $pid,
                'variant_id'   => null,
                'quantity'     => $qty,
                'modifiers'    => [],
                'instructions' => '',
            ];
        }
        return $state;
    }

    /**
     * Deterministic key for a configurable line (same product + variant +
     * modifiers + instructions collapses into one line and stacks quantity).
     *
     * @param list<int> $modifiers
     */
    public static function lineKey(int $productId, ?int $variantId, array $modifiers, string $instructions = ''): string
    {
        sort($modifiers);
        return substr(hash('sha256', $productId . '|' . ($variantId ?? 0) . '|' . implode(',', $modifiers) . '|' . $instructions), 0, 16);
    }

    /**
     * Add (or stack) a configured line.
     *
     * @param list<int> $modifiers
     */
    public function add(int $productId, int $quantity = 1, ?int $variantId = null, array $modifiers = [], string $instructions = ''): void
    {
        $state = $this->state();
        $modifiers = array_values(array_unique(array_map('intval', $modifiers)));
        $instructions = trim($instructions);
        $key = self::lineKey($productId, $variantId, $modifiers, $instructions);
        $quantity = max(1, $quantity);

        if (isset($state['items'][$key])) {
            $state['items'][$key]['quantity'] += $quantity;
        } else {
            $state['items'][$key] = [
                'product_id'   => $productId,
                'variant_id'   => $variantId,
                'quantity'     => $quantity,
                'modifiers'    => $modifiers,
                'instructions' => $instructions,
            ];
        }
        $this->save($state);
    }

    /** Set an exact quantity for a line (0 removes it). */
    public function update(string $lineKey, int $quantity): void
    {
        $state = $this->state();
        if (!isset($state['items'][$lineKey])) {
            return;
        }
        if ($quantity <= 0) {
            unset($state['items'][$lineKey]);
        } else {
            $state['items'][$lineKey]['quantity'] = $quantity;
        }
        $this->save($state);
    }

    public function remove(string $lineKey): void
    {
        $state = $this->state();
        unset($state['items'][$lineKey]);
        $this->save($state);
    }

    public function setTip(int $cents, string $mode = 'custom'): void
    {
        $state = $this->state();
        $state['tip_cents'] = max(0, $cents);
        $state['tip_mode']  = $mode;
        $this->save($state);
    }

    public function setPromoCode(?string $code): void
    {
        $state = $this->state();
        $state['promo_code'] = ($code !== null && trim($code) !== '') ? strtoupper(trim($code)) : null;
        $this->save($state);
    }

    public function setGiftCardCode(?string $code): void
    {
        $state = $this->state();
        $state['gift_card_code'] = ($code !== null && trim($code) !== '') ? strtoupper(trim($code)) : null;
        $this->save($state);
    }

    public function setPickup(string $mode, ?int $slotId): void
    {
        $state = $this->state();
        $state['pickup_mode']    = $mode === 'scheduled' ? 'scheduled' : 'asap';
        $state['pickup_slot_id'] = ($mode === 'scheduled' && $slotId) ? $slotId : null;
        $this->save($state);
    }

    public function clear(): void
    {
        $this->save(self::blank());
    }

    public function isEmpty(): bool
    {
        return $this->state()['items'] === [];
    }

    public function count(): int
    {
        $n = 0;
        foreach ($this->state()['items'] as $line) {
            $n += (int) $line['quantity'];
        }
        return $n;
    }

    /**
     * Resolve every line against LIVE catalog data for display + checkout math.
     *
     * Each returned line:
     *   key, product_id, variant_id, quantity, instructions,
     *   name, image_path, tax_category, day_part, available_from/to,
     *   tracks_inventory, is_86, is_active,
     *   unit_price_cents (variant or base + modifier deltas),
     *   modifiers => [{id,name,price_delta_cents}],
     *   line_total_cents.
     *
     * Lines whose product no longer exists are dropped from the resolved view
     * (the raw session line is left for the caller to prune if desired).
     *
     * @return list<array<string,mixed>>
     */
    public function lineItems(): array
    {
        $state = $this->state();
        if ($state['items'] === []) {
            return [];
        }

        $out = [];
        foreach ($state['items'] as $key => $line) {
            $product = Database::fetch(
                'SELECT id, name, image_path, tax_category, price_cents, day_part,
                        available_from, available_to, tracks_inventory, is_86, is_active
                   FROM products WHERE id = ?',
                [(int) $line['product_id']]
            );
            if ($product === null) {
                continue;
            }

            // Base price: variant overrides the product price when present + active.
            $unit = (int) $product['price_cents'];
            $variant = null;
            if (!empty($line['variant_id'])) {
                $variant = Database::fetch(
                    'SELECT id, name, price_cents FROM product_variants WHERE id = ? AND product_id = ? AND is_active = 1',
                    [(int) $line['variant_id'], (int) $product['id']]
                );
                if ($variant !== null) {
                    $unit = (int) $variant['price_cents'];
                }
            }

            // Resolve selected modifiers (only active ones mapped to this product).
            $mods = [];
            foreach ((array) $line['modifiers'] as $modId) {
                $mod = Database::fetch(
                    'SELECT m.id, m.name, m.price_delta_cents
                       FROM modifiers m
                       JOIN modifier_groups g ON g.id = m.group_id
                       JOIN product_modifier_map pmm ON pmm.group_id = g.id
                      WHERE m.id = ? AND pmm.product_id = ? AND m.is_active = 1',
                    [(int) $modId, (int) $product['id']]
                );
                if ($mod !== null) {
                    $mods[] = [
                        'id'                => (int) $mod['id'],
                        'name'              => (string) $mod['name'],
                        'price_delta_cents' => (int) $mod['price_delta_cents'],
                    ];
                    $unit += (int) $mod['price_delta_cents'];
                }
            }

            $qty = max(1, (int) $line['quantity']);
            $variantName = $variant !== null ? (string) $variant['name'] : null;
            $displayName = $variantName !== null ? $product['name'] . '–' . $variantName : (string) $product['name'];

            $out[] = [
                'key'              => (string) $key,
                'product_id'       => (int) $product['id'],
                'variant_id'       => $variant !== null ? (int) $variant['id'] : null,
                'quantity'         => $qty,
                'instructions'     => (string) $line['instructions'],
                'name'             => $displayName,
                'image_path'       => $product['image_path'],
                'tax_category'     => (string) $product['tax_category'],
                'day_part'         => (string) $product['day_part'],
                'available_from'   => $product['available_from'],
                'available_to'     => $product['available_to'],
                'tracks_inventory' => (bool) $product['tracks_inventory'],
                'is_86'            => (bool) $product['is_86'],
                'is_active'        => (bool) $product['is_active'],
                'unit_price_cents' => $unit,
                'modifiers'        => $mods,
                'line_total_cents' => $unit * $qty,
            ];
        }
        return $out;
    }

    /** Subtotal across resolved line items (integer cents). */
    public function subtotalCents(): int
    {
        $sum = 0;
        foreach ($this->lineItems() as $line) {
            $sum += (int) $line['line_total_cents'];
        }
        return $sum;
    }
}
