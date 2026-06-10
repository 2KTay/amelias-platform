<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;

/**
 * Alcohol DTC compliance gate (Task 4.1).
 *
 * Arizona wine DTC shipping is legally fraught and stays CLOSED by default
 * until the client confirms per-state legality (open questions Q#1 / Q#2). The
 * gate has two independent locks, both of which must be open for a bottle to
 * ship:
 *
 *   1. config('features.wine_dtc_shipping'), the global master switch (off).
 *   2. product_shippable_states, a per-product allow-list of destination
 *      states. Empty list => nowhere is allowed.
 *
 * With the gate closed, every alcohol product is PICKUP-ONLY: canShipTo()
 * returns false regardless of state, so the public UI never offers a ship
 * option. is_alcohol additionally drives a 21+ age confirmation note.
 *
 * Pure reads, no mutation. Stateless static helpers.
 */
final class AlcoholCompliance
{
    /**
     * The global DTC master switch. When false (the default until Q#1/#2),
     * NOTHING ships regardless of per-product allow-lists.
     */
    public static function dtcShippingEnabled(): bool
    {
        return (bool) config('features.wine_dtc_shipping', false);
    }

    /** Is this product an alcohol product (drives 21+ gating + pickup-only)? */
    public static function isAlcohol(int $productId): bool
    {
        $row = Database::fetchValue(
            'SELECT is_alcohol FROM products WHERE id = ?',
            [$productId]
        );
        return $row !== false && (int) $row === 1;
    }

    /** Does this product require an age (21+) confirmation before purchase? */
    public static function ageConfirmationRequired(int $productId): bool
    {
        return self::isAlcohol($productId);
    }

    /**
     * Is this product flagged to ship DTC at all (ships_dtc), independent of
     * the global gate? Used to label "pickup only" vs "shippable (pending)".
     */
    public static function productShipsDtc(int $productId): bool
    {
        $row = Database::fetchValue(
            'SELECT ships_dtc FROM products WHERE id = ?',
            [$productId]
        );
        return $row !== false && (int) $row === 1;
    }

    /**
     * Can this product be shipped to the given US state right now?
     *
     * Returns false unless ALL of the following hold:
     *   - the global wine_dtc_shipping feature is enabled, AND
     *   - the product is flagged ships_dtc, AND
     *   - the destination state is in the product's shippable-states allow-list.
     *
     * Until Q#1/#2 resolve, the feature flag is off, so this is always false
     * and every product is effectively pickup-only.
     */
    public static function canShipTo(int $productId, string $state): bool
    {
        if (!self::dtcShippingEnabled()) {
            return false;
        }
        if (!self::productShipsDtc($productId)) {
            return false;
        }
        $state = strtoupper(trim($state));
        if (strlen($state) !== 2) {
            return false;
        }
        $allowed = Database::fetchValue(
            'SELECT COUNT(*) FROM product_shippable_states WHERE product_id = ? AND state = ?',
            [$productId, $state]
        );
        return (int) $allowed > 0;
    }

    /**
     * Is this product pickup-only at the moment? True when it is alcohol that
     * cannot ship anywhere under the current gate (the common case today).
     *
     * A product is pickup-only when it is NOT shippable-to-anywhere: either the
     * global gate is closed, the product isn't flagged ships_dtc, or it has no
     * allowed states.
     */
    public static function isPickupOnly(int $productId): bool
    {
        if (!self::dtcShippingEnabled() || !self::productShipsDtc($productId)) {
            return true;
        }
        $count = Database::fetchValue(
            'SELECT COUNT(*) FROM product_shippable_states WHERE product_id = ?',
            [$productId]
        );
        return (int) $count === 0;
    }

    /**
     * The list of states a product may currently ship to (empty when gated).
     *
     * @return list<string>
     */
    public static function allowedStates(int $productId): array
    {
        if (!self::dtcShippingEnabled() || !self::productShipsDtc($productId)) {
            return [];
        }
        $rows = Database::fetchAll(
            'SELECT state FROM product_shippable_states WHERE product_id = ? ORDER BY state',
            [$productId]
        );
        return array_map(static fn (array $r): string => (string) $r['state'], $rows);
    }
}
