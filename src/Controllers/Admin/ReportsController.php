<?php

declare(strict_types=1);

namespace Amelias\Controllers\Admin;

use Amelias\Http\Request;
use Amelias\Services\Reconciliation;

/**
 * Reports & reconciliation (screen 26, 6.3), Owner ONLY.
 *
 * Sales by stream, AZ TPT tax by category, Stripe payout reconciliation, and
 * refunds, all from snapshotted figures (orders/order_tax_lines/payments).
 * The owner enters a payout total to reconcile against the ledger sum. CSV
 * export streams the active report. Dates are bounded in UTC (Phoenix display).
 */
final class ReportsController extends AdminController
{
    public function index(Request $request): void
    {
        $this->requireRole('owner');

        [$fromUtc, $toUtc, $fromDisplay, $toDisplay] = $this->range($request);

        if ($request->query('export') === 'csv') {
            $this->exportCsv($fromUtc, $toUtc, (string) $request->query('report', 'streams'));
            return;
        }

        $totals  = Reconciliation::totals($fromUtc, $toUtc);
        $streams = Reconciliation::salesByStream($fromUtc, $toUtc);
        $taxes   = Reconciliation::taxByCategory($fromUtc, $toUtc);
        $payouts = Reconciliation::payoutLedger($fromUtc, $toUtc);
        $refunds = Reconciliation::refunds($fromUtc, $toUtc);

        $grossAll = $totals['gross_cents'];
        foreach ($streams as &$s) {
            $s['pct'] = $grossAll > 0 ? round($s['revenue_cents'] / $grossAll * 100, 1) : 0.0;
        }
        unset($s);

        // Owner-entered payout total to reconcile (single field, in dollars).
        $enteredPayout = $request->query('payout_total');
        $enteredCents  = $enteredPayout !== null && $enteredPayout !== '' ? cents($enteredPayout) : null;
        $ledgerNet     = array_sum(array_column($payouts, 'net_cents'));
        $variance      = $enteredCents !== null ? $enteredCents - $ledgerNet : null;

        $this->viewAdmin('admin/reports', [
            'title'         => 'Reports',
            'styles'        => ['pages/admin-depth.css'],
            'fromDate'      => $fromDisplay,
            'toDate'        => $toDisplay,
            'totals'        => $totals,
            'streams'       => $streams,
            'streamTotal'   => array_sum(array_column($streams, 'revenue_cents')),
            'taxes'         => $taxes,
            'taxTotal'      => array_sum(array_column($taxes, 'tax_cents')),
            'payouts'       => $payouts,
            'ledgerNet'     => $ledgerNet,
            'enteredPayout' => $enteredPayout,
            'variance'      => $variance,
            'refunds'       => $refunds,
        ]);
    }

    /**
     * Resolve the from/to window. Inputs are 'YYYY-MM-DD' (date inputs); we
     * treat them as Phoenix days and convert to a half-open UTC range.
     *
     * @return array{0:string,1:string,2:string,3:string}
     */
    private function range(Request $request): array
    {
        $tz  = new \DateTimeZone(FMT_TZ);
        $utc = new \DateTimeZone('UTC');

        $fromIn = (string) $request->query('from', '');
        $toIn   = (string) $request->query('to', '');

        try {
            $from = $fromIn !== ''
                ? new \DateTimeImmutable($fromIn . ' 00:00:00', $tz)
                : (new \DateTimeImmutable('now', $tz))->modify('first day of this month')->setTime(0, 0);
        } catch (\Exception) {
            $from = (new \DateTimeImmutable('now', $tz))->modify('first day of this month')->setTime(0, 0);
        }
        try {
            $toBase = $toIn !== ''
                ? new \DateTimeImmutable($toIn . ' 00:00:00', $tz)
                : new \DateTimeImmutable('now', $tz);
        } catch (\Exception) {
            $toBase = new \DateTimeImmutable('now', $tz);
        }
        // Half-open upper bound: start of the day after `to`.
        $toExclusive = $toBase->modify('+1 day')->setTime(0, 0);

        return [
            $from->setTimezone($utc)->format('Y-m-d H:i:s'),
            $toExclusive->setTimezone($utc)->format('Y-m-d H:i:s'),
            $from->format('Y-m-d'),
            $toBase->format('Y-m-d'),
        ];
    }

    private function exportCsv(string $fromUtc, string $toUtc, string $report): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="report-' . $report . '.csv"');
        $out = fopen('php://output', 'wb');

        switch ($report) {
            case 'tax':
                fputcsv($out, ['Tax category', 'Taxable base', 'Tax collected']);
                foreach (Reconciliation::taxByCategory($fromUtc, $toUtc) as $t) {
                    fputcsv($out, [$t['tax_category'], fmt_money($t['taxable_base_cents']), fmt_money($t['tax_cents'])]);
                }
                break;
            case 'payouts':
                fputcsv($out, ['Payout ID', 'Gross', 'Refunds', 'Net', 'Transactions']);
                foreach (Reconciliation::payoutLedger($fromUtc, $toUtc) as $p) {
                    fputcsv($out, [$p['payout_id'], fmt_money($p['gross_cents']), fmt_money($p['refund_cents']), fmt_money($p['net_cents']), $p['txn_count']]);
                }
                break;
            case 'refunds':
                fputcsv($out, ['Payment ID', 'Order token', 'Amount', 'Status', 'Date']);
                foreach (Reconciliation::refunds($fromUtc, $toUtc) as $r) {
                    fputcsv($out, [$r['id'], $r['public_token'] ?? '', fmt_money((int) $r['amount_cents']), $r['status'], fmt_date((string) $r['created_at'], 'Y-m-d H:i')]);
                }
                break;
            case 'streams':
            default:
                fputcsv($out, ['Stream', 'Revenue']);
                foreach (Reconciliation::salesByStream($fromUtc, $toUtc) as $s) {
                    fputcsv($out, [$s['stream'], fmt_money($s['revenue_cents'])]);
                }
                break;
        }
        fclose($out);
    }
}
