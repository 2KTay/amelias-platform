<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\Availability;
use Amelias\Services\CapacityException;
use Amelias\Services\Cart;
use Amelias\Services\GiftCard;
use Amelias\Services\HoldManager;
use Amelias\Services\Payments\StripeGateway;
use Amelias\Services\Pricing;
use Amelias\Support\Session;
use RuntimeException;

/**
 * Checkout (screen 7, Task 2.5).
 *
 * GET show(): contact/guest fields, order summary with per-category tax, and
 *   the Stripe Payment Element (client_secret + publishable key) with Apple /
 *   Google Pay via automatic_payment_methods. Empty carts are handled
 *   gracefully (redirect back to the menu).
 *
 * POST place(): rate-limit, re-validate availability + day-part gating, then in
 *   sequence:
 *     1. HoldManager::claim(), pending order + holds + snapshot, atomically.
 *     2. Gift-card redemption (guarded) inside its own step against the order.
 *     3. Stripe PaymentIntent for the remaining amount_due.
 *   The webhook flips the order pending->paid and converts holds. A card
 *   decline leaves the order pending with the hold intact (cron sweeps it). A
 *   slot/stock race surfaces as a re-prompt without charging.
 *
 * SAQ-A: card data never touches our server, only the PaymentIntent.
 */
final class CheckoutController extends Controller
{
    /** Checkout attempts allowed per IP per window. */
    private const RL_MAX = 8;
    private const RL_WINDOW = 300;

    public function show(Request $request): void
    {
        $cart = Cart::fromSession();
        if ($cart->isEmpty()) {
            $_SESSION['_flash'] = ['type' => 'info', 'message' => 'Your cart is empty, add something delicious first.'];
            $this->redirect(url('/menu'));
            return;
        }

        $customer = Session::customer();
        $pricing  = Pricing::forCart($cart, $customer['id'] ?? null);

        $gateway = new StripeGateway();
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        $this->viewPublic('public/checkout', [
            'title'           => 'Checkout',
            'bodyClass'       => 'has-grain',
            'styles'          => ['pages/checkout.css'],
            'pricing'         => $pricing,
            'cart'            => $cart->state(),
            'customer'        => $customer,
            'stripeReady'     => $gateway->isConfigured(),
            'publishableKey'  => $gateway->publishableKey(),
            'flash'           => $flash,
        ]);
    }

    public function place(Request $request): void
    {
        $cart = Cart::fromSession();

        if (!csrf_verify((string) $request->input('_csrf'))) {
            $this->fail('Your session expired. Please review your order and try again.');
            return;
        }
        if ($cart->isEmpty()) {
            $this->redirect(url('/menu'));
            return;
        }

        // Rate-limit checkout attempts per IP.
        if (!\Amelias\Services\Auth::rateLimit('checkout:' . $request->ip(), self::RL_MAX, self::RL_WINDOW)) {
            $this->fail('Too many attempts. Please wait a moment and try again.');
            return;
        }

        // Contact details (guest or logged-in).
        $customer = Session::customer();
        $contact  = [
            'name'  => trim((string) $request->input('first_name', '') . ' ' . (string) $request->input('last_name', '')) ?: ($customer['name'] ?? null),
            'email' => trim((string) $request->input('email', '')) ?: ($customer['email'] ?? null),
            'phone' => trim((string) $request->input('phone', '')) ?: null,
        ];
        if ($contact['email'] === null || $contact['email'] === '' || !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
            $this->fail('Please enter a valid email for your receipt.');
            return;
        }
        if ($contact['phone'] === null || $contact['phone'] === '') {
            $this->fail('Please enter a mobile number for your order-ready text.');
            return;
        }

        // Re-resolve pricing against live data at submit time.
        $customerId = $customer['id'] ?? null;
        $pricing = Pricing::forCart($cart, $customerId !== null ? (int) $customerId : null);
        if ($pricing['lines'] === []) {
            $this->fail('Your cart is empty, add something delicious first.');
            return;
        }

        // Validate availability + day-part + 86 before claiming.
        foreach ($pricing['lines'] as $line) {
            if (!$line['is_active'] || $line['is_86']) {
                $this->fail($line['name'] . ' is no longer available. Please update your cart.');
                return;
            }
            if (!Availability::dayPartActive($line)) {
                $this->fail($line['name'] . ' is not available at this time of day. Please update your cart.');
                return;
            }
            if ($line['tracks_inventory']) {
                $reservable = Availability::reservableStock((int) $line['product_id']);
                if ($reservable < (int) $line['quantity']) {
                    $this->fail($line['name'] . ' just sold out or is low in stock. Please update your cart.');
                    return;
                }
            }
        }

        // Validate the pickup slot if one was chosen.
        $state  = $cart->state();
        $slotId = null;
        if (($state['pickup_mode'] ?? 'asap') === 'scheduled' && !empty($state['pickup_slot_id'])) {
            $slot = Availability::pickupSlot((int) $state['pickup_slot_id']);
            if ($slot === null || (int) $slot['is_active'] !== 1 || (int) $slot['booked_count'] >= (int) $slot['capacity']) {
                $this->fail('That pickup time just filled up. Please choose another slot.');
                return;
            }
            $slotId = (int) $state['pickup_slot_id'];
        }

        // 1. Claim stock + slot + create the pending snapshotted order.
        try {
            $order = HoldManager::claim([
                'lines'                => $pricing['lines'],
                'subtotal_cents'       => $pricing['subtotal_cents'],
                'discount_cents'       => $pricing['discount_cents'],
                'tax_lines'            => $pricing['tax_lines'],
                'tax_cents'            => $pricing['tax_cents'],
                'tip_cents'            => $pricing['tip_cents'],
                'pickup_slot_id'       => $slotId,
                'customer_id'          => $customerId !== null ? (int) $customerId : null,
                'contact'              => $contact,
                'promotion'            => $pricing['promotion'],
                'promo_discount_cents' => $pricing['discount_cents'],
            ]);
        } catch (CapacityException $e) {
            $this->fail($e->getMessage());
            return;
        } catch (\Throwable $e) {
            error_log('[checkout] claim failed: ' . $e->getMessage());
            $this->fail('We could not place your order just now. Please try again.');
            return;
        }

        $orderId = (int) $order['id'];

        // 2. Apply gift card (guarded atomic redemption against this order).
        $giftApplied = 0;
        if (!empty($state['gift_card_code'])) {
            $card = GiftCard::findByCode((string) $state['gift_card_code']);
            if ($card !== null && (string) $card['status'] === 'active') {
                $want = min((int) $card['current_balance'], (int) $order['total_cents']);
                if ($want > 0) {
                    $giftApplied = Database::transaction(static function () use ($card, $want, $orderId): int {
                        return GiftCard::redeem((int) $card['id'], $want, $orderId);
                    });
                }
            }
        }
        $amountDue = max(0, (int) $order['total_cents'] - $giftApplied);
        if ($giftApplied > 0) {
            Database::run(
                'UPDATE orders SET giftcard_applied_cents = ?, amount_due_cents = ? WHERE id = ?',
                [$giftApplied, $amountDue, $orderId]
            );
        }

        // 3a. Gift card fully covers the order, no card charge. Mark paid now.
        if ($amountDue === 0) {
            (new \Amelias\Services\Payments\PaymentStateMachine())->markPaid($orderId, 'giftcard:' . (int) ($card['id'] ?? 0), (int) $order['total_cents']);
            $cart->clear();
            $this->redirect(url('/order/' . $order['public_token']));
            return;
        }

        // 3b. Create the Stripe PaymentIntent for the remaining amount due.
        $gateway = new StripeGateway();
        if (!$gateway->isConfigured()) {
            // Stripe not configured (keys pending), leave the order pending; the
            // hold will expire via cron. Surface a clear, non-charging message.
            $this->fail('Online payment is not available yet. Your order was not charged. Please call us to complete it.');
            return;
        }

        try {
            $intent = $gateway->createPaymentIntent($amountDue, 'usd', ['order_id' => $orderId]);
        } catch (\Throwable $e) {
            error_log('[checkout] PaymentIntent failed: ' . $e->getMessage());
            $this->fail('We could not start the payment. Your order was not charged. Please try again.');
            return;
        }

        Database::run(
            'UPDATE orders SET stripe_payment_intent = ? WHERE id = ?',
            [$intent['id'], $orderId]
        );

        // Hand off to the confirmation page, which mounts the Payment Element
        // with this client_secret and confirms client-side. The webhook flips
        // the order to paid on success; a decline keeps it pending (hold intact).
        $cart->clear();
        $_SESSION['_pi'] = [
            'order_token'    => $order['public_token'],
            'client_secret'  => $intent['client_secret'],
            'amount_due'     => $amountDue,
            'publishable'    => $gateway->publishableKey(),
        ];
        $this->redirect(url('/order/' . $order['public_token']));
    }

    private function fail(string $message): void
    {
        $_SESSION['_flash'] = ['type' => 'error', 'message' => $message];
        $this->redirect(url('/checkout'));
    }
}
