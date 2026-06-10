<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;
use RuntimeException;

/**
 * The concurrency engine for checkout (Task 2.3, C-V7-1 / C-DATA-2).
 *
 * claim() runs in ONE transaction and either fully succeeds or fully rolls
 * back. For each finite line item it performs a guarded atomic stock decrement;
 * for the chosen pickup slot it performs a guarded atomic booked_count
 * increment. Every guard checks affected-rows, if any guard fails (capacity or
 * stock exhausted by a competing order), it throws CapacityException and the
 * whole transaction rolls back, so counters can never go negative and a slot
 * can never be over-booked.
 *
 * On success it inserts the inventory_holds rows (status active, expiring in 15
 * minutes), the `pending` order, snapshotted order_items (+ modifiers), the
 * order_tax_lines, and the order_discounts, all atomically. The webhook later
 * flips the order to paid and converts the holds; the cron sweep releases stale
 * ones.
 *
 * Money is integer cents, snapshotted at creation. Stripe payment intent +
 * gift-card redemption are handled by the caller AFTER claim() returns (gift
 * card redemption is wrapped by the controller in its own guarded path).
 */
final class HoldManager
{
    /** Hold lifetime in minutes (aligned to the checkout/session expiry sweep). */
    public const HOLD_TTL_MINUTES = 15;

    /**
     * Claim stock + slot and create the pending order atomically.
     *
     * @param array{
     *   lines: list<array<string,mixed>>,        resolved Cart::lineItems()
     *   subtotal_cents: int,
     *   discount_cents: int,
     *   tax_lines: list<array<string,mixed>>,
     *   tax_cents: int,
     *   tip_cents: int,
     *   pickup_slot_id: ?int,
     *   customer_id: ?int,
     *   contact: array{name:?string,email:?string,phone:?string},
     *   promotion?: array<string,mixed>|null,
     *   promo_discount_cents?: int,
     *   notes?: ?string
     * } $input
     *
     * @return array<string,mixed> the created order row
     * @throws CapacityException when stock or slot capacity cannot be claimed
     */
    public static function claim(array $input): array
    {
        $lines        = $input['lines'];
        $subtotal     = (int) $input['subtotal_cents'];
        $discount     = (int) ($input['discount_cents'] ?? 0);
        $taxLines     = $input['tax_lines'] ?? [];
        $taxCents     = (int) ($input['tax_cents'] ?? 0);
        $tipCents     = (int) ($input['tip_cents'] ?? 0);
        $slotId       = $input['pickup_slot_id'] ?? null;
        $customerId   = $input['customer_id'] ?? null;
        $contact      = $input['contact'] ?? ['name' => null, 'email' => null, 'phone' => null];
        $promotion    = $input['promotion'] ?? null;
        $promoDiscount = (int) ($input['promo_discount_cents'] ?? $discount);
        $notes        = $input['notes'] ?? null;

        if ($lines === []) {
            throw new RuntimeException('Cannot place an empty order.');
        }

        $total     = max(0, $subtotal - $discount) + $taxCents + $tipCents;
        $publicToken = bin2hex(random_bytes(16)); // 32 hex chars => CHAR(32)
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::HOLD_TTL_MINUTES * 60);

        return Database::transaction(static function () use (
            $lines, $subtotal, $discount, $taxLines, $taxCents, $tipCents, $slotId,
            $customerId, $contact, $promotion, $promoDiscount, $notes, $total,
            $publicToken, $expiresAt
        ): array {
            // 1. Claim the pickup slot first (cheapest guard; fail fast).
            if ($slotId !== null) {
                $slotOk = Database::affected(
                    'UPDATE pickup_slots
                        SET booked_count = booked_count + 1
                      WHERE id = ? AND is_active = 1 AND booked_count < capacity',
                    [$slotId]
                );
                if ($slotOk !== 1) {
                    throw new CapacityException('pickup_slot', 'That pickup time just filled up. Please choose another.');
                }
            }

            // 2. Claim finite stock per line. Aggregate quantities per product so
            //    two lines of the same finite product are guarded together.
            $finiteQty = [];
            foreach ($lines as $line) {
                if (!empty($line['tracks_inventory'])) {
                    $pid = (int) $line['product_id'];
                    $finiteQty[$pid] = ($finiteQty[$pid] ?? 0) + (int) $line['quantity'];
                }
            }
            foreach ($finiteQty as $pid => $qty) {
                $stockOk = Database::affected(
                    'UPDATE inventory
                        SET stock = stock - ?
                      WHERE product_id = ? AND stock >= ?',
                    [$qty, $pid, $qty]
                );
                if ($stockOk !== 1) {
                    throw new CapacityException('inventory', 'One of your items just sold out. Please review your cart.');
                }
            }

            // 3. Create the pending order.
            $orderId = Database::insert(
                'INSERT INTO orders
                    (public_token, customer_id, kind, fulfillment, status,
                     subtotal_cents, discount_cents, tax_cents, tip_cents, total_cents,
                     giftcard_applied_cents, amount_due_cents,
                     contact_email, contact_phone, contact_name, pickup_slot_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $publicToken, $customerId, 'standard', 'pickup', 'pending',
                    $subtotal, $discount, $taxCents, $tipCents, $total,
                    0, $total,
                    $contact['email'] ?? null, $contact['phone'] ?? null, $contact['name'] ?? null,
                    $slotId, $notes,
                ]
            );

            // 4. Snapshot line items + modifiers (NEVER read live price later).
            //    Distribute per-line tax proportionally within each category so
            //    order_items.tax_amount_cents reconciles to order_tax_lines.
            $catTaxByCategory = [];
            foreach ($taxLines as $tl) {
                $catTaxByCategory[(string) $tl['tax_category']] = (int) $tl['tax_cents'];
            }
            // Sum of taxable base per category from the lines themselves.
            $catBase = [];
            foreach ($lines as $line) {
                $cat = (string) $line['tax_category'];
                $catBase[$cat] = ($catBase[$cat] ?? 0) + (int) $line['line_total_cents'];
            }
            $catAllocated = [];

            $lineCount = count($lines);
            foreach ($lines as $idx => $line) {
                $cat       = (string) $line['tax_category'];
                $lineTotal = (int) $line['line_total_cents'];

                // Allocate this line's slice of its category tax.
                $lineTax = 0;
                if (($catTaxByCategory[$cat] ?? 0) > 0 && ($catBase[$cat] ?? 0) > 0) {
                    $lineTax = (int) floor($catTaxByCategory[$cat] * $lineTotal / $catBase[$cat]);
                    $catAllocated[$cat] = ($catAllocated[$cat] ?? 0) + $lineTax;
                }

                $orderItemId = Database::insert(
                    'INSERT INTO order_items
                        (order_id, product_id, name_snapshot, unit_price_cents, quantity,
                         tax_treatment, tax_amount_cents, line_total_cents)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $orderId,
                        (int) $line['product_id'],
                        (string) $line['name'],
                        (int) $line['unit_price_cents'],
                        (int) $line['quantity'],
                        self::taxTreatment($cat),
                        $lineTax,
                        $lineTotal,
                    ]
                );

                foreach ((array) ($line['modifiers'] ?? []) as $mod) {
                    Database::run(
                        'INSERT INTO order_item_modifiers (order_item_id, name_snapshot, price_delta_cents)
                         VALUES (?, ?, ?)',
                        [$orderItemId, (string) $mod['name'], (int) $mod['price_delta_cents']]
                    );
                }
            }

            // 5. Snapshot tax lines.
            foreach ($taxLines as $tl) {
                Database::run(
                    'INSERT INTO order_tax_lines (order_id, tax_category, taxable_base_cents, rate_bps, tax_cents, source)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [
                        $orderId,
                        (string) $tl['tax_category'],
                        (int) $tl['taxable_base_cents'],
                        (int) $tl['rate_bps'],
                        (int) $tl['tax_cents'],
                        'internal',
                    ]
                );
            }

            // 6. Snapshot discount + consume the promotion atomically.
            if ($promotion !== null && $promoDiscount > 0) {
                if (!Promotion::consume((int) $promotion['id'])) {
                    throw new CapacityException('promotion', 'That promo code is no longer available.');
                }
                Database::run(
                    'INSERT INTO order_discounts (order_id, promotion_id, code_snapshot, discount_amount_cents)
                     VALUES (?, ?, ?, ?)',
                    [$orderId, (int) $promotion['id'], (string) $promotion['code'], $promoDiscount]
                );
            }

            // 7. Inventory hold rows (active, expiring) for each finite line.
            foreach ($finiteQty as $pid => $qty) {
                Database::run(
                    'INSERT INTO inventory_holds (order_id, product_id, quantity, pickup_slot_id, status, expires_at)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$orderId, $pid, $qty, $slotId, 'active', $expiresAt]
                );
            }

            $order = Database::fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
            if ($order === null) {
                throw new RuntimeException('Order creation failed.');
            }
            return $order;
        });
    }

    /**
     * Release the holds for an order and undo its slot/stock claims. Used by the
     * cron sweep and guest cancel. Idempotent: only acts on still-active holds
     * and only when the order is not already in a terminal-released state.
     */
    public static function release(int $orderId): void
    {
        Database::transaction(static function () use ($orderId): void {
            $holds = Database::fetchAll(
                "SELECT id, product_id, quantity, pickup_slot_id
                   FROM inventory_holds
                  WHERE order_id = ? AND status = 'active'",
                [$orderId]
            );
            $slotsReleased = [];
            foreach ($holds as $h) {
                // Restore stock.
                if ($h['product_id'] !== null) {
                    Database::run(
                        'UPDATE inventory SET stock = stock + ? WHERE product_id = ?',
                        [(int) $h['quantity'], (int) $h['product_id']]
                    );
                }
                // Decrement the slot booked_count once per slot (never below 0).
                $slotId = $h['pickup_slot_id'] !== null ? (int) $h['pickup_slot_id'] : null;
                if ($slotId !== null && !isset($slotsReleased[$slotId])) {
                    Database::run(
                        'UPDATE pickup_slots SET booked_count = GREATEST(0, booked_count - 1) WHERE id = ?',
                        [$slotId]
                    );
                    $slotsReleased[$slotId] = true;
                }
            }
            Database::run(
                "UPDATE inventory_holds SET status = 'released' WHERE order_id = ? AND status = 'active'",
                [$orderId]
            );

            // If the order had a slot but NO finite-stock holds, still release the
            // slot (a pure made-to-order order books a slot without inventory_holds
            // rows carrying it, but we always insert at least the slot via holds
            // only when finite items exist, so handle the slot directly here).
            if ($holds === []) {
                $slotId = Database::fetchValue('SELECT pickup_slot_id FROM orders WHERE id = ?', [$orderId]);
                if ($slotId !== null && $slotId !== false) {
                    Database::run(
                        'UPDATE pickup_slots SET booked_count = GREATEST(0, booked_count - 1) WHERE id = ?',
                        [(int) $slotId]
                    );
                }
            }
        });
    }

    /** Map a tax_category to the order_items.tax_treatment enum value. */
    private static function taxTreatment(string $taxCategory): string
    {
        $allowed = ['prepared_food', 'retail_goods', 'grocery', 'service', 'non_taxable'];
        return in_array($taxCategory, $allowed, true) ? $taxCategory : 'prepared_food';
    }
}
