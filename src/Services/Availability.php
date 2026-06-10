<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Read-side availability checks for ordering (Task 2.2).
 *
 * Pure reads, no mutation. The authoritative claim (decrementing stock and the
 * slot booked_count atomically) happens in HoldManager inside a transaction.
 * These helpers gate the cart/checkout UI and pre-validate before the claim.
 *
 *   - reservableStock()  = inventory.stock − SUM(active inventory_holds.quantity)
 *   - openPickupSlots()  = future slots with booked_count < capacity (+ lead time)
 *   - dayPartActive()    = order-time gating against a product's availability window
 */
final class Availability
{
    /** Default lead time (minutes) before a pickup slot can be booked. */
    private const SLOT_LEAD_MINUTES = 30;

    /**
     * Reservable units for a finite product right now.
     * Reservable = on-hand − active holds. Non-tracked products are unlimited
     * (returns PHP_INT_MAX as a sentinel so callers treat them as always-OK).
     */
    public static function reservableStock(int $productId): int
    {
        $tracks = Database::fetchValue(
            'SELECT tracks_inventory FROM products WHERE id = ?',
            [$productId]
        );
        if ($tracks === false || (int) $tracks === 0) {
            return PHP_INT_MAX;
        }

        $stock = (int) Database::fetchValue(
            'SELECT COALESCE(stock, 0) FROM inventory WHERE product_id = ?',
            [$productId]
        );
        $held = (int) Database::fetchValue(
            "SELECT COALESCE(SUM(quantity), 0) FROM inventory_holds
              WHERE product_id = ? AND status = 'active' AND expires_at > UTC_TIMESTAMP()",
            [$productId]
        );
        return $stock - $held;
    }

    /**
     * Future pickup slots that still have capacity, respecting the lead time.
     *
     * @return list<array<string,mixed>> each: id, slot_start, slot_end,
     *         capacity, booked_count, remaining
     */
    public static function openPickupSlots(int $leadMinutes = self::SLOT_LEAD_MINUTES, int $limit = 24): array
    {
        $minStart = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . max(0, $leadMinutes) . ' minutes')
            ->format('Y-m-d H:i:s');

        $rows = Database::fetchAll(
            'SELECT id, slot_start, slot_end, capacity, booked_count
               FROM pickup_slots
              WHERE is_active = 1
                AND slot_start >= ?
                AND booked_count < capacity
              ORDER BY slot_start ASC
              LIMIT ' . (int) $limit,
            [$minStart]
        );

        return array_map(static function (array $r): array {
            return [
                'id'           => (int) $r['id'],
                'slot_start'   => (string) $r['slot_start'],
                'slot_end'     => (string) $r['slot_end'],
                'capacity'     => (int) $r['capacity'],
                'booked_count' => (int) $r['booked_count'],
                'remaining'    => (int) $r['capacity'] - (int) $r['booked_count'],
            ];
        }, $rows);
    }

    /** Fetch a single bookable slot (capacity-checked) or null. */
    public static function pickupSlot(int $slotId): ?array
    {
        $row = Database::fetch(
            'SELECT id, slot_start, slot_end, capacity, booked_count, is_active
               FROM pickup_slots WHERE id = ?',
            [$slotId]
        );
        return $row;
    }

    /**
     * Is the product orderable at $now given its day-part window?
     *
     * A NULL available_from/available_to window means "always orderable when
     * active". When a window is set, $now's Phoenix wall-clock time must fall
     * within [from, to). Windows that wrap past midnight are handled.
     *
     * @param array<string,mixed> $product must include available_from, available_to
     */
    public static function dayPartActive(array $product, ?DateTimeImmutable $now = null): bool
    {
        $from = $product['available_from'] ?? null;
        $to   = $product['available_to'] ?? null;
        if ($from === null || $to === null || $from === '' || $to === '') {
            return true;
        }

        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        // Order-time gating is judged against local (Phoenix) wall clock.
        $localNow = $now->setTimezone(new DateTimeZone('America/Phoenix'))->format('H:i:s');

        $from = self::normalizeTime((string) $from);
        $to   = self::normalizeTime((string) $to);

        if ($from <= $to) {
            return $localNow >= $from && $localNow < $to;
        }
        // Wrapping window (e.g. 22:00 → 02:00).
        return $localNow >= $from || $localNow < $to;
    }

    private static function normalizeTime(string $t): string
    {
        // Accept "H:i" or "H:i:s".
        return strlen($t) === 5 ? $t . ':00' : $t;
    }
}
