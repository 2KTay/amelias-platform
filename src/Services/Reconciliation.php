<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;

/**
 * Reports & reconciliation queries (screen 26, owner-only).
 *
 * Every figure is read from snapshotted rows: orders.*_cents, order_tax_lines,
 * and the payments ledger (webhook-fed source of truth). Historical totals are
 * never recomputed from live product prices. Money is integer cents throughout.
 *
 * All SQL uses bound params; date bounds arrive as 'YYYY-MM-DD' UTC strings.
 */
final class Reconciliation
{
    /** Order statuses that count as captured revenue. */
    private const PAID_STATUSES = ['paid', 'preparing', 'ready', 'fulfilled', 'partially_refunded'];

    /**
     * Sales grouped by revenue stream (derived from product type on the order's
     * line items, falling back to the order kind for catering/deposits).
     *
     * @return list<array{stream:string,revenue_cents:int}>
     */
    public static function salesByStream(string $fromUtc, string $toUtc): array
    {
        $placeholders = implode(',', array_fill(0, count(self::PAID_STATUSES), '?'));
        // Attribute each line item's total to a stream bucket via product type;
        // catering orders are attributed by order.kind regardless of line type.
        $rows = Database::fetchAll(
            "SELECT
                 CASE
                     WHEN o.kind IN ('catering_deposit','catering_balance') THEN 'Catering'
                     WHEN p.type = 'market' THEN 'Market'
                     WHEN p.type = 'giftcard' THEN 'Gift cards'
                     WHEN p.type = 'wine' THEN 'Wine'
                     WHEN p.type = 'event' THEN 'Events'
                     WHEN p.type = 'subscription' THEN 'Wine Club subscriptions'
                     ELSE 'Dine-in & Orders'
                 END AS stream,
                 COALESCE(SUM(oi.line_total_cents), 0) AS revenue_cents
               FROM orders o
               JOIN order_items oi ON oi.order_id = o.id
               LEFT JOIN products p ON p.id = oi.product_id
              WHERE o.status IN ($placeholders)
                AND o.paid_at >= ? AND o.paid_at < ?
              GROUP BY stream
              ORDER BY revenue_cents DESC",
            [...self::PAID_STATUSES, $fromUtc, $toUtc]
        );

        return array_map(static fn (array $r): array => [
            'stream'        => (string) $r['stream'],
            'revenue_cents' => (int) $r['revenue_cents'],
        ], $rows);
    }

    /**
     * Tax collected grouped by AZ TPT category, from snapshotted order_tax_lines.
     *
     * @return list<array{tax_category:string,taxable_base_cents:int,tax_cents:int}>
     */
    public static function taxByCategory(string $fromUtc, string $toUtc): array
    {
        $placeholders = implode(',', array_fill(0, count(self::PAID_STATUSES), '?'));
        $rows = Database::fetchAll(
            "SELECT t.tax_category,
                    COALESCE(SUM(t.taxable_base_cents), 0) AS taxable_base_cents,
                    COALESCE(SUM(t.tax_cents), 0)          AS tax_cents
               FROM order_tax_lines t
               JOIN orders o ON o.id = t.order_id
              WHERE o.status IN ($placeholders)
                AND o.paid_at >= ? AND o.paid_at < ?
              GROUP BY t.tax_category
              ORDER BY tax_cents DESC",
            [...self::PAID_STATUSES, $fromUtc, $toUtc]
        );

        return array_map(static fn (array $r): array => [
            'tax_category'       => (string) $r['tax_category'],
            'taxable_base_cents' => (int) $r['taxable_base_cents'],
            'tax_cents'          => (int) $r['tax_cents'],
        ], $rows);
    }

    /**
     * Headline totals for the period, from the payments ledger.
     *
     * @return array{gross_cents:int,refund_cents:int,net_cents:int,tax_cents:int}
     */
    public static function totals(string $fromUtc, string $toUtc): array
    {
        $gross = (int) Database::fetchValue(
            "SELECT COALESCE(SUM(amount_cents), 0) FROM payments
              WHERE type = 'payment' AND status = 'succeeded' AND created_at >= ? AND created_at < ?",
            [$fromUtc, $toUtc]
        );
        // Refunds are stored as negative amounts; report the absolute magnitude.
        $refund = (int) Database::fetchValue(
            "SELECT COALESCE(SUM(ABS(amount_cents)), 0) FROM payments
              WHERE type = 'refund' AND created_at >= ? AND created_at < ?",
            [$fromUtc, $toUtc]
        );
        $tax = (int) Database::fetchValue(
            "SELECT COALESCE(SUM(t.tax_cents), 0) FROM order_tax_lines t
               JOIN orders o ON o.id = t.order_id
              WHERE o.status IN ('paid','preparing','ready','fulfilled','partially_refunded')
                AND o.paid_at >= ? AND o.paid_at < ?",
            [$fromUtc, $toUtc]
        );

        return [
            'gross_cents'  => $gross,
            'refund_cents' => $refund,
            'net_cents'    => $gross - $refund,
            'tax_cents'    => $tax,
        ];
    }

    /**
     * Per-payout reconciliation: ledger amount grouped by Stripe payout_id.
     * The owner enters the actual payout total from the Stripe dashboard; the
     * controller compares it against the ledger sum to surface discrepancies.
     *
     * @return list<array{payout_id:string,gross_cents:int,refund_cents:int,net_cents:int,txn_count:int}>
     */
    public static function payoutLedger(string $fromUtc, string $toUtc): array
    {
        $rows = Database::fetchAll(
            "SELECT payout_id,
                    COALESCE(SUM(CASE WHEN type = 'payment' THEN amount_cents ELSE 0 END), 0) AS gross_cents,
                    COALESCE(SUM(CASE WHEN type = 'refund'  THEN ABS(amount_cents) ELSE 0 END), 0) AS refund_cents,
                    COALESCE(SUM(amount_cents), 0) AS net_cents,
                    COUNT(*) AS txn_count
               FROM payments
              WHERE payout_id IS NOT NULL AND payout_id <> ''
                AND created_at >= ? AND created_at < ?
              GROUP BY payout_id
              ORDER BY MAX(created_at) DESC",
            [$fromUtc, $toUtc]
        );

        return array_map(static fn (array $r): array => [
            'payout_id'    => (string) $r['payout_id'],
            'gross_cents'  => (int) $r['gross_cents'],
            'refund_cents' => (int) $r['refund_cents'],
            'net_cents'    => (int) $r['net_cents'],
            'txn_count'    => (int) $r['txn_count'],
        ], $rows);
    }

    /**
     * Refund detail rows for the period.
     *
     * @return list<array<string,mixed>>
     */
    public static function refunds(string $fromUtc, string $toUtc): array
    {
        return Database::fetchAll(
            "SELECT pay.id, pay.order_id, pay.amount_cents, pay.status, pay.created_at,
                    o.public_token
               FROM payments pay
               LEFT JOIN orders o ON o.id = pay.order_id
              WHERE pay.type = 'refund' AND pay.created_at >= ? AND pay.created_at < ?
              ORDER BY pay.created_at DESC",
            [$fromUtc, $toUtc]
        );
    }
}
