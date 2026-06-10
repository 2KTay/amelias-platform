<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\GiftCard;
use Amelias\Services\HoldManager;
use Amelias\Services\Payments\PaymentStateMachine;
use Amelias\Services\Payments\StripeGateway;

/**
 * Order confirmation + live status + guest cancel (screen 8, Task 2.5).
 *
 * show(): looks up the order by its un-enumerable public_token. The row exists
 *   from the moment checkout created it (pending), so the confirmation renders
 *   even if the Stripe webhook lags. If a PaymentIntent client_secret was
 *   stashed in the session for this token, the page mounts the Stripe Payment
 *   Element to confirm payment client-side. ?format=json returns the live
 *   status for the stepper's polling.
 *
 * cancel(): tokenized guest cancel, allowed only while paid AND not yet
 *   preparing. Refunds the charge (Stripe + gift-card credit) via the
 *   PaymentStateMachine / GiftCard and restores stock + slot via HoldManager.
 *
 * On first view of a paid order, the confirmation email is sent (send-once via
 * the Notifier's notification_log).
 */
final class OrderController extends Controller
{
    /** Statuses that map onto the received -> preparing -> ready stepper. */
    private const STEP_MAP = [
        'pending'   => 'received',
        'paid'      => 'received',
        'preparing' => 'preparing',
        'ready'     => 'ready',
        'fulfilled' => 'ready',
    ];

    public function show(Request $request): void
    {
        $token = (string) $request->param('token');
        $order = $this->orderByToken($token);
        if ($order === null) {
            $this->viewPublic('errors/404', ['path' => $request->path], 404);
            return;
        }

        $orderId = (int) $order['id'];
        $items   = $this->items($orderId);
        $taxLines = Database::fetchAll(
            'SELECT tax_category, taxable_base_cents, rate_bps, tax_cents FROM order_tax_lines WHERE order_id = ?',
            [$orderId]
        );

        // Send the confirmation email once the order is paid (send-once guarded).
        if (in_array((string) $order['status'], ['paid', 'preparing', 'ready', 'fulfilled'], true)) {
            $this->sendConfirmation($order, $items);
        }

        if ($request->query('format') === 'json') {
            $this->json([
                'status' => (string) $order['status'],
                'step'   => self::STEP_MAP[(string) $order['status']] ?? null,
            ]);
            return;
        }

        // Mount the Payment Element only when we still owe a card charge and a
        // client_secret for THIS token is in the session (set by checkout).
        $payment = null;
        $pi = $_SESSION['_pi'] ?? null;
        if (
            is_array($pi)
            && ($pi['order_token'] ?? null) === $token
            && (string) $order['status'] === 'pending'
            && (int) $order['amount_due_cents'] > 0
        ) {
            $payment = [
                'client_secret' => (string) $pi['client_secret'],
                'publishable'   => (string) $pi['publishable'],
                'amount_due'    => (int) $pi['amount_due'],
                'return_url'    => rtrim((string) config('url'), '/') . url('/order/' . $token),
            ];
        }

        $slot = null;
        if ($order['pickup_slot_id'] !== null) {
            $slot = Database::fetch('SELECT slot_start, slot_end FROM pickup_slots WHERE id = ?', [(int) $order['pickup_slot_id']]);
        }

        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        $canCancel = (string) $order['status'] === 'paid';

        $this->viewPublic('public/order-confirmation', [
            'title'      => 'Order ' . (in_array((string) $order['status'], ['paid','preparing','ready','fulfilled'], true) ? 'confirmed' : 'received'),
            'bodyClass'  => 'has-grain',
            'styles'     => ['pages/order-confirmation.css'],
            'order'      => $order,
            'items'      => $items,
            'taxLines'   => $taxLines,
            'slot'       => $slot,
            'step'       => self::STEP_MAP[(string) $order['status']] ?? null,
            'payment'    => $payment,
            'canCancel'  => $canCancel,
            'flash'      => $flash,
        ]);
    }

    public function cancel(Request $request): void
    {
        $token = (string) $request->param('token');
        $order = $this->orderByToken($token);
        if ($order === null) {
            $this->viewPublic('errors/404', ['path' => $request->path], 404);
            return;
        }
        if (!csrf_verify((string) $request->input('_csrf'))) {
            $this->redirect(url('/order/' . $token));
            return;
        }

        // Cancellable only while paid and not yet being prepared.
        if ((string) $order['status'] !== 'paid') {
            $_SESSION['_flash'] = ['type' => 'error', 'message' => 'This order can no longer be cancelled online, please call us.'];
            $this->redirect(url('/order/' . $token));
            return;
        }

        $orderId = (int) $order['id'];

        // Refund: gift-card credit first (if any was applied), then the card.
        $giftApplied = (int) $order['giftcard_applied_cents'];
        if ($giftApplied > 0) {
            $gc = Database::fetch(
                "SELECT gift_card_id FROM gift_card_transactions
                  WHERE order_id = ? AND type = 'redeem' ORDER BY id ASC LIMIT 1",
                [$orderId]
            );
            if ($gc !== null) {
                GiftCard::refundCredit((int) $gc['gift_card_id'], $giftApplied, $orderId);
            }
        }

        $cardCharge = (int) $order['amount_due_cents'];
        $machine = new PaymentStateMachine();
        if ($cardCharge > 0 && $order['stripe_payment_intent'] !== null && !str_starts_with((string) $order['stripe_payment_intent'], 'giftcard:')) {
            try {
                (new StripeGateway())->refund((string) $order['stripe_payment_intent'], $cardCharge);
            } catch (\Throwable $e) {
                error_log('[order.cancel] refund failed: ' . $e->getMessage());
                // Continue: we still cancel + restore; the charge.refunded webhook
                // will reconcile the ledger if the refund eventually lands.
            }
        }

        // Restore stock + slot.
        HoldManager::release($orderId);

        // Flip status to cancelled.
        Database::run('UPDATE orders SET status = ? WHERE id = ? AND status = ?', ['cancelled', $orderId, 'paid']);

        $_SESSION['_flash'] = ['type' => 'success', 'message' => 'Your order was cancelled and refunded.'];
        $this->redirect(url('/order/' . $token));
    }

    /** @return array<string,mixed>|null */
    private function orderByToken(string $token): ?array
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        return Database::fetch('SELECT * FROM orders WHERE public_token = ?', [$token]);
    }

    /** @return list<array<string,mixed>> */
    private function items(int $orderId): array
    {
        $items = Database::fetchAll(
            'SELECT id, name_snapshot, unit_price_cents, quantity, line_total_cents
               FROM order_items WHERE order_id = ? ORDER BY id ASC',
            [$orderId]
        );
        foreach ($items as &$it) {
            $it['modifiers'] = Database::fetchAll(
                'SELECT name_snapshot, price_delta_cents FROM order_item_modifiers WHERE order_item_id = ?',
                [(int) $it['id']]
            );
        }
        unset($it);
        return $items;
    }

    /**
     * Send the order-confirmation email (send-once via notification_log).
     *
     * @param array<string,mixed> $order
     * @param list<array<string,mixed>> $items
     */
    private function sendConfirmation(array $order, array $items): void
    {
        if (empty($order['contact_email'])) {
            return;
        }
        $pickupTime = '';
        if ($order['pickup_slot_id'] !== null) {
            $slot = Database::fetchValue('SELECT slot_start FROM pickup_slots WHERE id = ?', [(int) $order['pickup_slot_id']]);
            if ($slot) {
                $pickupTime = fmt_date((string) $slot, 'g:i A');
            }
        }
        $emailItems = array_map(static fn (array $i): array => [
            'name'  => (string) $i['name_snapshot'],
            'qty'   => (int) $i['quantity'],
            'price' => fmt_money((int) $i['line_total_cents']),
        ], $items);

        (new \Amelias\Services\Notifications\Notifier())->notify(
            'order',
            (string) $order['id'],
            'order-confirmation',
            ['email' => (string) $order['contact_email']],
            [
                'subject'     => "Your Amelia's order #" . $order['id'] . ' is confirmed',
                'name'        => (string) ($order['contact_name'] ?? ''),
                'orderNumber' => (string) $order['id'],
                'total'       => fmt_money((int) $order['total_cents']),
                'items'       => $emailItems,
                'pickupTime'  => $pickupTime,
                'statusUrl'   => rtrim((string) config('url'), '/') . url('/order/' . $order['public_token']),
            ],
            'email'
        );
    }
}
