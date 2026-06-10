<?php

declare(strict_types=1);

namespace Amelias\Controllers\Admin;

use Amelias\Database\Database;
use Amelias\Http\Request;

/**
 * Customers (screen 24, 6.2), Manager + Owner.
 *
 * Search by name / email / phone; a unified profile pulls orders, reservations,
 * feedback, and Wine Club subscription. Lifetime value is computed as an SQL
 * aggregate over paid orders (never a stored column). CSV export streams the
 * matching list.
 */
final class CustomersController extends AdminController
{
    /** Order statuses that count toward lifetime value. */
    private const PAID_STATUSES = ['paid', 'preparing', 'ready', 'fulfilled', 'partially_refunded'];

    public function index(Request $request): void
    {
        $this->requireRole('owner', 'manager');

        $q = trim((string) $request->query('q', ''));

        if ($request->query('export') === 'csv') {
            $this->exportCsv($q);
            return;
        }

        $customers = $this->search($q);

        // Selected profile: explicit ?id=, else the first result.
        $selectedId = (int) ($request->query('id') ?? ($customers[0]['id'] ?? 0));
        $profile = $selectedId > 0 ? $this->profile($selectedId) : null;

        $this->viewAdmin('admin/customers', [
            'title'      => 'Customers',
            'styles'     => ['pages/admin-depth.css'],
            'q'          => $q,
            'customers'  => $customers,
            'selectedId' => $selectedId,
            'profile'    => $profile,
        ]);
    }

    /**
     * Customer list with order count + LTV aggregate + club flag.
     *
     * @return list<array<string,mixed>>
     */
    private function search(string $q): array
    {
        $placeholders = implode(',', array_fill(0, count(self::PAID_STATUSES), '?'));
        // LTV is a per-customer aggregate of paid-order totals (never stored).
        $sql =
            "SELECT c.id, c.email, c.phone, c.first_name, c.last_name,
                    COUNT(po.id) AS order_count,
                    COALESCE(SUM(po.total_cents), 0) AS ltv_cents,
                    MAX(po.paid_at) AS last_order_at,
                    EXISTS(SELECT 1 FROM subscriptions s WHERE s.customer_id = c.id AND s.status = 'active') AS in_club
               FROM customers c
               LEFT JOIN orders po
                      ON po.customer_id = c.id AND po.status IN ($placeholders)";

        $params = [...self::PAID_STATUSES];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= ' WHERE c.email LIKE ? OR c.phone LIKE ? OR CONCAT_WS(\' \', c.first_name, c.last_name) LIKE ?';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' GROUP BY c.id ORDER BY ltv_cents DESC, c.id DESC LIMIT 200';

        $rows = Database::fetchAll($sql, $params);
        return array_map(static function (array $r): array {
            $r['order_count'] = (int) $r['order_count'];
            $r['ltv_cents']   = (int) $r['ltv_cents'];
            $r['in_club']     = (bool) $r['in_club'];
            $r['name']        = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: '(no name)';
            return $r;
        }, $rows);
    }

    /**
     * Unified profile: customer + LTV + orders + reservations + feedback + club.
     *
     * @return array<string,mixed>|null
     */
    private function profile(int $id): ?array
    {
        $customer = Database::fetch('SELECT * FROM customers WHERE id = ?', [$id]);
        if ($customer === null) {
            return null;
        }
        $customer['name'] = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: '(no name)';

        $placeholders = implode(',', array_fill(0, count(self::PAID_STATUSES), '?'));
        $ltv = (int) Database::fetchValue(
            "SELECT COALESCE(SUM(total_cents), 0) FROM orders
              WHERE customer_id = ? AND status IN ($placeholders)",
            [$id, ...self::PAID_STATUSES]
        );

        $orders = Database::fetchAll(
            'SELECT id, public_token, total_cents, status, placed_at, paid_at
               FROM orders WHERE customer_id = ? ORDER BY placed_at DESC LIMIT 20',
            [$id]
        );

        $reservations = Database::fetchAll(
            'SELECT b.id, b.party_size, b.status, s.slot_start
               FROM bookings b
               JOIN booking_slots s ON s.id = b.slot_id
              WHERE b.customer_id = ? ORDER BY s.slot_start DESC LIMIT 20',
            [$id]
        );

        $feedback = Database::fetchAll(
            'SELECT id, rating, comment, table_code, created_at
               FROM feedback WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20',
            [$id]
        );

        $subscription = Database::fetch(
            'SELECT s.status, s.current_period_end, t.name AS tier_name, t.price_cents, t.`interval`
               FROM subscriptions s
               JOIN subscription_tiers t ON t.id = s.tier_id
              WHERE s.customer_id = ? ORDER BY s.created_at DESC LIMIT 1',
            [$id]
        );

        return [
            'customer'     => $customer,
            'ltv_cents'    => $ltv,
            'order_count'  => count($orders),
            'orders'       => $orders,
            'reservations' => $reservations,
            'feedback'     => $feedback,
            'subscription' => $subscription,
        ];
    }

    /** Stream the matching customer list as CSV. */
    private function exportCsv(string $q): void
    {
        $customers = $this->search($q);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="customers.csv"');
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Orders', 'LTV', 'Wine Club', 'Last order']);
        foreach ($customers as $c) {
            fputcsv($out, [
                $c['id'],
                $c['name'],
                $c['email'],
                $c['phone'] ?? '',
                $c['order_count'],
                fmt_money($c['ltv_cents']),
                $c['in_club'] ? 'yes' : 'no',
                $c['last_order_at'] !== null ? fmt_date((string) $c['last_order_at'], 'Y-m-d') : '',
            ]);
        }
        fclose($out);
    }
}
