<?php

declare(strict_types=1);

namespace Amelias\Services\Payments;

use Amelias\Database\Database;

/**
 * Applies Stripe payment outcomes to order state. The Stripe webhook is the
 * source of truth for payment state, so these transitions are driven from
 * verified webhook events and are all idempotent, replaying the same event
 * never double-applies.
 *
 * Money is always integer cents; timestamps are written with UTC_TIMESTAMP().
 */
final class PaymentStateMachine
{
    /**
     * Mark an order paid: flip status, stamp paid_at + payment intent, record a
     * payments-ledger row, and convert the order's active inventory holds.
     * No-op if the order is already paid (idempotent on replay).
     */
    public function markPaid(int $orderId, string $paymentIntent, int $amountCents): void
    {
        Database::transaction(static function () use ($orderId, $paymentIntent, $amountCents): void {
            $current = Database::fetch(
                'SELECT id, status FROM orders WHERE id = ? FOR UPDATE',
                [$orderId]
            );
            if ($current === null) {
                return;
            }
            if ($current['status'] === 'paid') {
                return; // already applied
            }

            Database::run(
                'UPDATE orders
                    SET status = ?, paid_at = UTC_TIMESTAMP(), stripe_payment_intent = ?
                  WHERE id = ?',
                ['paid', $paymentIntent, $orderId]
            );

            // Ledger row, guard against a duplicate payment row for this intent.
            $existing = Database::fetchValue(
                'SELECT COUNT(*) FROM payments WHERE order_id = ? AND stripe_payment_intent = ? AND type = ?',
                [$orderId, $paymentIntent, 'payment']
            );
            if ((int) $existing === 0) {
                Database::run(
                    'INSERT INTO payments (order_id, stripe_payment_intent, type, amount_cents, currency, status)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$orderId, $paymentIntent, 'payment', abs($amountCents), 'USD', 'succeeded']
                );
            }

            // Convert any active holds for this order.
            Database::run(
                'UPDATE inventory_holds SET status = ? WHERE order_id = ? AND status = ?',
                ['converted', $orderId, 'active']
            );
        });
    }

    /**
     * Payment failed or the intent expired: if the order is still pending,
     * expire it and release its active inventory holds. Idempotent.
     */
    public function markFailedOrExpired(int $orderId): void
    {
        Database::transaction(static function () use ($orderId): void {
            $affected = Database::affected(
                'UPDATE orders SET status = ? WHERE id = ? AND status = ?',
                ['expired', $orderId, 'pending']
            );
            if ($affected === 0) {
                return; // not pending anymore, nothing to release
            }
            Database::run(
                'UPDATE inventory_holds SET status = ? WHERE order_id = ? AND status = ?',
                ['released', $orderId, 'active']
            );
        });
    }

    /**
     * Record a refund and set order status. $partial decides
     * partially_refunded vs refunded. The ledger amount is stored negative.
     */
    public function markRefunded(int $orderId, int $amountCents, bool $partial): void
    {
        $status = $partial ? 'partially_refunded' : 'refunded';
        Database::transaction(static function () use ($orderId, $amountCents, $status): void {
            $current = Database::fetch(
                'SELECT id, stripe_payment_intent FROM orders WHERE id = ? FOR UPDATE',
                [$orderId]
            );
            if ($current === null) {
                return;
            }

            Database::run(
                'UPDATE orders SET status = ? WHERE id = ?',
                [$status, $orderId]
            );

            Database::run(
                'INSERT INTO payments (order_id, stripe_payment_intent, type, amount_cents, currency, status)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$orderId, $current['stripe_payment_intent'], 'refund', -abs($amountCents), 'USD', 'succeeded']
            );
        });
    }

    /** Mark an order disputed (chargeback opened). Idempotent. */
    public function markDisputed(int $orderId): void
    {
        Database::run(
            'UPDATE orders SET status = ? WHERE id = ?',
            ['disputed', $orderId]
        );
    }

    /**
     * Resolve an order id from a Stripe PaymentIntent id (preferred) or fall
     * back to an explicit order_id pulled from event metadata.
     */
    public function resolveOrderId(?string $paymentIntent, ?int $metadataOrderId): ?int
    {
        if ($paymentIntent !== null && $paymentIntent !== '') {
            $id = Database::fetchValue(
                'SELECT id FROM orders WHERE stripe_payment_intent = ? LIMIT 1',
                [$paymentIntent]
            );
            if ($id !== false && $id !== null) {
                return (int) $id;
            }
        }
        if ($metadataOrderId !== null && $metadataOrderId > 0) {
            $id = Database::fetchValue(
                'SELECT id FROM orders WHERE id = ? LIMIT 1',
                [$metadataOrderId]
            );
            if ($id !== false && $id !== null) {
                return (int) $id;
            }
        }
        return null;
    }
}
