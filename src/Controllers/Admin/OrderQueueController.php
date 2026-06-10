<?php

declare(strict_types=1);

namespace Amelias\Controllers\Admin;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\GiftCard;
use Amelias\Services\HoldManager;
use Amelias\Services\Payments\PaymentStateMachine;
use Amelias\Services\Payments\StripeGateway;

/**
 * Live order queue (screen 20, Task 2.5), owner / manager / staff.
 *
 * Lists active orders grouped by status with a new-order badge. Status actions:
 *   - accept     paid -> preparing
 *   - ready      preparing -> ready (fires the order-ready notification)
 *   - fulfilled  ready -> fulfilled (picked up)
 *   - refund     full/partial; pre-fulfillment refunds restore stock + slot
 *   - comp       zero out + mark comped (audit-logged)
 *   - void       cancel an unpaid/pending order (audit-logged)
 *
 * Forward transitions are guarded so a stale tab can't skip states. Refund/comp/
 * void are audit-logged. All mutations are CSRF-guarded POSTs (PRG).
 */
final class OrderQueueController extends AdminController
{
    /** Statuses considered "live" for the default queue. */
    private const LIVE = ['paid', 'preparing', 'ready'];

    public function index(Request $request): void
    {
        $this->requireRole('owner', 'manager', 'staff');

        $filter = (string) ($request->query('status') ?? 'live');
        $orders = $this->orders($filter);
        $counts = $this->counts();

        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        $this->viewAdmin('admin/orders', [
            'title'   => 'Orders',
            'styles'  => ['pages/admin-orders.css'],
            'orders'  => $orders,
            'counts'  => $counts,
            'filter'  => $filter,
            'flash'   => $flash,
        ]);
    }

    public function updateStatus(Request $request): void
    {
        $user = $this->requireRole('owner', 'manager', 'staff');

        if (!csrf_verify((string) $request->input('_csrf'))) {
            $this->redirect(url('/admin/orders'));
            return;
        }

        $orderId = (int) $request->param('id');
        $action  = (string) $request->input('action');
        $order   = $orderId > 0 ? Database::fetch('SELECT * FROM orders WHERE id = ?', [$orderId]) : null;
        if ($order === null) {
            $this->redirect(url('/admin/orders'));
            return;
        }

        match ($action) {
            'accept'    => $this->transition($order, 'paid', 'preparing'),
            'ready'     => $this->markReady($order),
            'fulfilled' => $this->transition($order, 'ready', 'fulfilled'),
            'refund'    => $this->refund($order, $request, (int) $user['id']),
            'comp'      => $this->comp($order, (int) $user['id']),
            'void'      => $this->void($order, (int) $user['id']),
            default     => null,
        };

        $this->redirect(url('/admin/orders'));
    }

    /** Guarded forward transition (no-op if the order moved on). */
    private function transition(array $order, string $from, string $to): void
    {
        $ok = Database::affected(
            'UPDATE orders SET status = ? WHERE id = ? AND status = ?',
            [$to, (int) $order['id'], $from]
        );
        if ($ok === 1) {
            $this->flash('success', 'Order #' . $order['id'] . ' marked ' . $to . '.');
        } else {
            $this->flash('error', 'Order #' . $order['id'] . ' had already moved on.');
        }
    }

    private function markReady(array $order): void
    {
        $ok = Database::affected(
            'UPDATE orders SET status = ? WHERE id = ? AND status = ?',
            ['ready', (int) $order['id'], 'preparing']
        );
        if ($ok !== 1) {
            $this->flash('error', 'Order #' . $order['id'] . ' is not in preparing.');
            return;
        }
        // Fire the order-ready notification (send-once).
        if (!empty($order['contact_email']) || !empty($order['contact_phone'])) {
            (new \Amelias\Services\Notifications\Notifier())->notify(
                'order',
                (string) $order['id'],
                'order-ready',
                ['email' => $order['contact_email'] ?? null, 'sms' => $order['contact_phone'] ?? null],
                [
                    'subject'     => "Your Amelia's order #" . $order['id'] . ' is ready',
                    'name'        => (string) ($order['contact_name'] ?? ''),
                    'orderNumber' => (string) $order['id'],
                ],
                'email'
            );
        }
        $this->flash('success', 'Order #' . $order['id'] . ' marked ready, customer notified.');
    }

    private function refund(array $order, Request $request, int $userId): void
    {
        $orderId = (int) $order['id'];
        $status  = (string) $order['status'];
        if (!in_array($status, ['paid', 'preparing', 'ready', 'fulfilled'], true)) {
            $this->flash('error', 'Order #' . $orderId . ' is not refundable.');
            return;
        }

        // Full unless a partial amount (dollars) is provided.
        $partialInput = trim((string) $request->input('amount', ''));
        $cardCharge   = (int) $order['amount_due_cents'];
        $refundCents  = $partialInput !== '' ? cents($partialInput) : $cardCharge;
        $refundCents  = max(0, min($refundCents, $cardCharge));
        $partial      = $refundCents > 0 && $refundCents < $cardCharge;

        // Card refund via Stripe (skip pure gift-card-funded orders).
        if ($refundCents > 0 && $order['stripe_payment_intent'] !== null && !str_starts_with((string) $order['stripe_payment_intent'], 'giftcard:')) {
            try {
                (new StripeGateway())->refund((string) $order['stripe_payment_intent'], $refundCents);
            } catch (\Throwable $e) {
                error_log('[admin.refund] ' . $e->getMessage());
                $this->flash('error', 'Stripe refund failed for #' . $orderId . '. Check the dashboard.');
                return;
            }
        }

        // Pre-fulfillment refunds restore stock + slot.
        if ($status !== 'fulfilled') {
            HoldManager::release($orderId);
        }

        // Credit back any gift-card portion on a full refund.
        if (!$partial) {
            $giftApplied = (int) $order['giftcard_applied_cents'];
            if ($giftApplied > 0) {
                $gc = Database::fetch(
                    "SELECT gift_card_id FROM gift_card_transactions WHERE order_id = ? AND type = 'redeem' ORDER BY id ASC LIMIT 1",
                    [$orderId]
                );
                if ($gc !== null) {
                    GiftCard::refundCredit((int) $gc['gift_card_id'], $giftApplied, $orderId);
                }
            }
        }

        (new PaymentStateMachine())->markRefunded($orderId, $refundCents, $partial);
        $this->audit($userId, 'order.refund', $orderId, ['amount_cents' => $refundCents, 'partial' => $partial]);
        $this->flash('success', 'Refunded ' . fmt_money($refundCents) . ' on order #' . $orderId . '.');
    }

    private function comp(array $order, int $userId): void
    {
        $orderId = (int) $order['id'];
        if (in_array((string) $order['status'], ['comped', 'voided', 'refunded'], true)) {
            $this->flash('error', 'Order #' . $orderId . ' is already closed.');
            return;
        }
        Database::run('UPDATE orders SET status = ? WHERE id = ?', ['comped', $orderId]);
        $this->audit($userId, 'order.comp', $orderId, ['from' => (string) $order['status']]);
        $this->flash('success', 'Order #' . $orderId . ' comped.');
    }

    private function void(array $order, int $userId): void
    {
        $orderId = (int) $order['id'];
        // Void releases any holds (pre-payment) and marks voided.
        HoldManager::release($orderId);
        Database::run('UPDATE orders SET status = ? WHERE id = ?', ['voided', $orderId]);
        $this->audit($userId, 'order.void', $orderId, ['from' => (string) $order['status']]);
        $this->flash('success', 'Order #' . $orderId . ' voided.');
    }

    /**
     * Orders for the queue. 'live' = paid|preparing|ready; otherwise an exact
     * status; 'all' = everything except pending/expired noise.
     *
     * @return list<array<string,mixed>>
     */
    private function orders(string $filter): array
    {
        if ($filter === 'live') {
            $rows = Database::fetchAll(
                "SELECT o.*, ps.slot_start
                   FROM orders o
              LEFT JOIN pickup_slots ps ON ps.id = o.pickup_slot_id
                  WHERE o.status IN ('paid','preparing','ready')
                  ORDER BY ps.slot_start IS NULL, ps.slot_start ASC, o.placed_at ASC"
            );
        } elseif ($filter === 'all') {
            $rows = Database::fetchAll(
                "SELECT o.*, ps.slot_start
                   FROM orders o
              LEFT JOIN pickup_slots ps ON ps.id = o.pickup_slot_id
                  WHERE o.status NOT IN ('pending','expired')
                  ORDER BY o.placed_at DESC LIMIT 100"
            );
        } else {
            $rows = Database::fetchAll(
                "SELECT o.*, ps.slot_start
                   FROM orders o
              LEFT JOIN pickup_slots ps ON ps.id = o.pickup_slot_id
                  WHERE o.status = ?
                  ORDER BY o.placed_at DESC LIMIT 100",
                [$filter]
            );
        }

        foreach ($rows as &$o) {
            $names = Database::fetchAll(
                'SELECT name_snapshot, quantity FROM order_items WHERE order_id = ?',
                [(int) $o['id']]
            );
            $parts = [];
            foreach ($names as $n) {
                $qty = (int) $n['quantity'];
                $parts[] = $qty > 1 ? $qty . '× ' . $n['name_snapshot'] : (string) $n['name_snapshot'];
            }
            $o['items_summary'] = implode(', ', $parts);
            // "New" = paid but not yet accepted into preparing.
            $o['is_new'] = (string) $o['status'] === 'paid';
        }
        unset($o);
        return $rows;
    }

    /** @return array<string,int> status => count for the filter pills. */
    private function counts(): array
    {
        $rows = Database::fetchAll(
            "SELECT status, COUNT(*) AS n FROM orders
              WHERE status IN ('paid','preparing','ready','fulfilled') GROUP BY status"
        );
        $counts = ['paid' => 0, 'preparing' => 0, 'ready' => 0, 'fulfilled' => 0];
        foreach ($rows as $r) {
            $counts[(string) $r['status']] = (int) $r['n'];
        }
        $counts['live'] = $counts['paid'] + $counts['preparing'] + $counts['ready'];
        return $counts;
    }

    private function audit(int $userId, string $action, int $orderId, array $after): void
    {
        Database::run(
            'INSERT INTO audit_log (actor_type, actor_id, action, entity_type, entity_id, after_json)
             VALUES (?, ?, ?, ?, ?, ?)',
            ['user', $userId, $action, 'order', (string) $orderId, json_encode($after, JSON_THROW_ON_ERROR)]
        );
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }
}
