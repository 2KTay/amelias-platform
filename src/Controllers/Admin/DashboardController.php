<?php

declare(strict_types=1);

namespace Amelias\Controllers\Admin;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\Reconciliation;
use Amelias\Support\Session;

/**
 * Admin dashboard (screen 19). Role-filtered KPIs, Staff see operations,
 * financial figures are owner/manager only. Expanded in Task 6.1.
 */
final class DashboardController extends AdminController
{
    public function index(Request $request): void
    {
        $this->requireRole('owner', 'manager', 'staff');
        $canSeeMoney = Session::hasRole('owner', 'manager');

        $todayUtc = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');

        $ordersToday = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM orders WHERE status IN ('paid','preparing','ready','fulfilled') AND DATE(paid_at) = ?",
            [$todayUtc]
        );
        $revenueToday = (int) Database::fetchValue(
            "SELECT COALESCE(SUM(total_cents),0) FROM orders WHERE status IN ('paid','preparing','ready','fulfilled') AND DATE(paid_at) = ?",
            [$todayUtc]
        );
        $openOrders = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM orders WHERE status IN ('paid','preparing')", []
        );
        $avgFeedback = Database::fetchValue("SELECT ROUND(AVG(rating),1) FROM feedback WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY", []);
        $feedbackCount = (int) Database::fetchValue("SELECT COUNT(*) FROM feedback WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY", []);

        // Today's covers: guests booked across today's non-cancelled reservations
        // (Phoenix day window resolved below for the reservation list).
        $tz = new \DateTimeZone(FMT_TZ);
        $startLocal = (new \DateTimeImmutable('now', $tz))->setTime(0, 0);
        $startUtc = $startLocal->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $startLocal->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $covers = (int) Database::fetchValue(
            "SELECT COALESCE(SUM(b.party_size),0)
               FROM bookings b
               JOIN booking_slots s ON s.id = b.slot_id
              WHERE s.slot_start >= ? AND s.slot_start < ?
                AND b.status NOT IN ('cancelled','no_show')",
            [$startUtc, $endUtc]
        );

        // Live order queue (a handful of the most imminent active orders).
        $queue = Database::fetchAll(
            "SELECT o.id, o.status, o.total_cents, o.contact_name, ps.slot_start
               FROM orders o
          LEFT JOIN pickup_slots ps ON ps.id = o.pickup_slot_id
              WHERE o.status IN ('paid','preparing','ready')
              ORDER BY ps.slot_start IS NULL, ps.slot_start ASC, o.placed_at ASC
              LIMIT 6"
        );

        // Upcoming reservations for the rest of today (Phoenix day window above).
        $reservations = Database::fetchAll(
            "SELECT b.id, b.party_size, b.status, b.contact_name, s.slot_start, t.label AS table_label
               FROM bookings b
               JOIN booking_slots s ON s.id = b.slot_id
          LEFT JOIN tables t ON t.id = b.table_id
              WHERE s.slot_start >= ? AND s.slot_start < ?
                AND b.status NOT IN ('cancelled')
              ORDER BY s.slot_start, b.id
              LIMIT 6",
            [$startUtc, $endUtc]
        );

        // Low-stock items needing attention (reservable at/below threshold).
        $lowStock = Database::fetchAll(
            "SELECT p.name,
                    (i.stock - COALESCE((
                        SELECT SUM(h.quantity) FROM inventory_holds h
                         WHERE h.product_id = p.id AND h.status = 'active'
                    ), 0)) AS reservable
               FROM products p
               JOIN inventory i ON i.product_id = p.id
              WHERE p.tracks_inventory = 1
                AND (i.stock - COALESCE((
                        SELECT SUM(h.quantity) FROM inventory_holds h
                         WHERE h.product_id = p.id AND h.status = 'active'
                    ), 0)) <= i.low_stock_threshold
              ORDER BY reservable ASC
              LIMIT 6"
        );

        // Recent feedback snippets, with the reviewer name when the response is
        // tied to a known customer (QR feedback is often anonymous → NULL name).
        $recentFeedback = Database::fetchAll(
            "SELECT f.rating, f.comment, f.created_at, c.first_name, c.last_name
               FROM feedback f
          LEFT JOIN customers c ON c.id = f.customer_id
              ORDER BY f.created_at DESC LIMIT 3"
        );

        // Today's revenue split by stream (owner/manager only). Reuses the
        // reconciliation bucketing scoped to the current Phoenix day in UTC.
        $revenueStreams = [];
        if ($canSeeMoney) {
            $revenueStreams = Reconciliation::salesByStream($startUtc, $endUtc);
            $streamTotal = array_sum(array_column($revenueStreams, 'revenue_cents'));
            foreach ($revenueStreams as &$rs) {
                $rs['pct'] = $streamTotal > 0
                    ? (int) round($rs['revenue_cents'] / $streamTotal * 100)
                    : 0;
            }
            unset($rs);
        }

        $this->viewAdmin('admin/dashboard', [
            'title'          => 'Dashboard',
            'styles'         => ['pages/admin-dashboard.css'],
            'canSeeMoney'    => $canSeeMoney,
            'ordersToday'    => $ordersToday,
            'revenueToday'   => $revenueToday,
            'openOrders'     => $openOrders,
            'covers'         => $covers,
            'avgFeedback'    => $avgFeedback,
            'feedbackCount'  => $feedbackCount,
            'revenueStreams' => $revenueStreams,
            'queue'          => $queue,
            'reservations'   => $reservations,
            'lowStock'       => $lowStock,
            'recentFeedback' => $recentFeedback,
        ]);
    }
}
