<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;

/**
 * Promo-code validation + pre-tax discount computation (Task 2.x, C-V7-6).
 *
 * validate() checks the active window, min-subtotal, global redemption cap and
 * per-customer limit, returning the promotion row (or null + a reason). The
 * computed discount is pre-tax integer cents, capped at the subtotal. The
 * actual snapshot into order_discounts happens inside the checkout transaction
 * (HoldManager) so a code can't be over-redeemed under concurrency.
 */
final class Promotion
{
    /**
     * Validate a code against the rules. Returns:
     *   ['ok' => true, 'promotion' => row, 'discount_cents' => int]
     *   ['ok' => false, 'reason' => string]
     *
     * @param int $subtotalCents pre-tax subtotal the discount applies to
     * @param int|null $customerId for per-customer-limit checks (null = guest)
     */
    public static function validate(string $code, int $subtotalCents, ?int $customerId = null): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return ['ok' => false, 'reason' => 'Enter a promo code.'];
        }

        $promo = Database::fetch(
            'SELECT * FROM promotions WHERE code = ? AND is_active = 1',
            [$code]
        );
        if ($promo === null) {
            return ['ok' => false, 'reason' => 'That code is not valid.'];
        }

        // Active window (NULL bounds = open-ended).
        $now = gmdate('Y-m-d H:i:s');
        if ($promo['starts_at'] !== null && $now < (string) $promo['starts_at']) {
            return ['ok' => false, 'reason' => 'That code is not active yet.'];
        }
        if ($promo['ends_at'] !== null && $now > (string) $promo['ends_at']) {
            return ['ok' => false, 'reason' => 'That code has expired.'];
        }

        // Minimum subtotal.
        if ($subtotalCents < (int) $promo['min_subtotal_cents']) {
            $need = fmt_money((int) $promo['min_subtotal_cents']);
            return ['ok' => false, 'reason' => "Spend at least {$need} to use this code."];
        }

        // Global redemption cap.
        if ($promo['max_redemptions'] !== null
            && (int) $promo['redemption_count'] >= (int) $promo['max_redemptions']) {
            return ['ok' => false, 'reason' => 'That code has reached its redemption limit.'];
        }

        // Per-customer limit (only enforceable for known customers).
        if ($customerId !== null && $promo['per_customer_limit'] !== null) {
            $used = (int) Database::fetchValue(
                'SELECT COUNT(*) FROM order_discounts od
                   JOIN orders o ON o.id = od.order_id
                  WHERE od.promotion_id = ? AND o.customer_id = ?
                    AND o.status NOT IN (\'expired\',\'cancelled\',\'voided\')',
                [(int) $promo['id'], $customerId]
            );
            if ($used >= (int) $promo['per_customer_limit']) {
                return ['ok' => false, 'reason' => 'You have already used this code.'];
            }
        }

        $discount = self::discountFor($promo, $subtotalCents);
        if ($discount <= 0) {
            return ['ok' => false, 'reason' => 'That code does not apply to this order.'];
        }

        return ['ok' => true, 'promotion' => $promo, 'discount_cents' => $discount];
    }

    /**
     * Pre-tax discount cents for a promotion against a subtotal, capped at the
     * subtotal. percent => value is basis points; fixed => value is cents.
     *
     * @param array<string,mixed> $promo
     */
    public static function discountFor(array $promo, int $subtotalCents): int
    {
        if ($subtotalCents <= 0) {
            return 0;
        }
        $discount = ((string) $promo['type'] === 'percent')
            ? bps($subtotalCents, (int) $promo['value'])
            : (int) $promo['value'];
        return max(0, min($discount, $subtotalCents));
    }

    /**
     * Atomically increment the global redemption counter, respecting the cap.
     * Returns true if counted, false if the cap was already reached. MUST be
     * called inside the checkout transaction. Snapshotting the order_discounts
     * row is the caller's job.
     */
    public static function consume(int $promotionId): bool
    {
        $affected = Database::affected(
            'UPDATE promotions
                SET redemption_count = redemption_count + 1
              WHERE id = ?
                AND is_active = 1
                AND (max_redemptions IS NULL OR redemption_count < max_redemptions)',
            [$promotionId]
        );
        return $affected === 1;
    }
}
