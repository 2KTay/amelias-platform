<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\GiftCard;
use Amelias\Services\Payments\StripeGateway;
use Amelias\Support\Session;

/**
 * Gift cards (screen 10, Task 2.x).
 *
 * index() GET: the purchase form + a balance-check field.
 * index() POST:
 *   - action=balance: look up and report a card's balance (no auth).
 *   - action=purchase: issue a tax-exempt gift card. The card is issued as a
 *     pending order's product so payment flows through Stripe like any order;
 *     to keep this self-contained the gift card is created immediately and a
 *     PaymentIntent is created for the (non-taxable) amount. The emailed code is
 *     delivered once the order is paid (webhook), here we create the order +
 *     card and hand the client_secret to the confirmation page.
 *
 * Gift-card purchases are NEVER taxed (tax_category non_taxable).
 */
final class GiftCardController extends Controller
{
    /** Allowed preset amounts (cents) + custom bounds. */
    private const PRESETS = [2500, 5000, 10000, 15000];
    private const MIN_CENTS = 1000;
    private const MAX_CENTS = 50000;

    public function index(Request $request): void
    {
        if ($request->isPost()) {
            if (!csrf_verify((string) $request->input('_csrf'))) {
                $this->redirect(url('/gift-cards'));
                return;
            }
            $action = (string) $request->input('action', 'purchase');
            if ($action === 'balance') {
                $this->checkBalance($request);
                return;
            }
            $this->purchase($request);
            return;
        }

        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        // If a gift-card PaymentIntent is pending, expose it for the Element.
        $payment = $_SESSION['_gc_pi'] ?? null;
        if ($payment !== null) {
            unset($_SESSION['_gc_pi']);
        }

        $gateway = new StripeGateway();
        $this->viewPublic('public/giftcards', [
            'title'          => 'Gift Cards',
            'bodyClass'      => 'has-grain',
            'styles'         => ['pages/giftcards.css'],
            'presets'        => self::PRESETS,
            'minCents'       => self::MIN_CENTS,
            'maxCents'       => self::MAX_CENTS,
            'stripeReady'    => $gateway->isConfigured(),
            'publishableKey' => $gateway->publishableKey(),
            'balanceResult'  => $_SESSION['_gc_balance'] ?? null,
            'payment'        => $payment,
            'flash'          => $flash,
        ]);
        unset($_SESSION['_gc_balance']);
    }

    private function checkBalance(Request $request): void
    {
        $code = trim((string) $request->input('code', ''));
        $balance = $code !== '' ? GiftCard::balanceForCode($code) : null;
        $_SESSION['_gc_balance'] = $balance !== null
            ? ['ok' => true, 'message' => 'Balance: ' . fmt_money($balance) . '.']
            : ['ok' => false, 'message' => 'No active gift card found for that code.'];
        $this->redirect(url('/gift-cards'));
    }

    private function purchase(Request $request): void
    {
        // Resolve amount (preset or custom), clamp to bounds.
        $amount = (string) $request->input('amount', '');
        if ($amount === 'custom') {
            $cents = cents((string) $request->input('custom_amount', '0'));
        } else {
            $cents = (int) $amount; // posted as cents from the form
        }
        if ($cents < self::MIN_CENTS || $cents > self::MAX_CENTS) {
            $this->fail('Please choose an amount between ' . fmt_money(self::MIN_CENTS) . ' and ' . fmt_money(self::MAX_CENTS) . '.');
            return;
        }

        $purchaserEmail = trim((string) $request->input('purchaser_email', ''));
        $recipientEmail = trim((string) $request->input('recipient_email', ''));
        if (!filter_var($purchaserEmail, FILTER_VALIDATE_EMAIL)) {
            $this->fail('Please enter a valid email for your receipt.');
            return;
        }
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $this->fail("Please enter the recipient's email.");
            return;
        }

        if (!\Amelias\Services\Auth::rateLimit('giftcard:' . $request->ip(), 6, 300)) {
            $this->fail('Too many attempts. Please wait a moment and try again.');
            return;
        }

        $customerId = Session::customer()['id'] ?? null;
        $publicToken = bin2hex(random_bytes(16));

        // Create a non-taxable pending order for the gift card purchase + the
        // card itself, atomically. The card is delivered (emailed) on payment
        // confirmation via the webhook / first paid view.
        try {
            $result = Database::transaction(static function () use ($cents, $purchaserEmail, $recipientEmail, $customerId, $publicToken, $request): array {
                $orderId = Database::insert(
                    'INSERT INTO orders
                        (public_token, customer_id, kind, fulfillment, status,
                         subtotal_cents, discount_cents, tax_cents, tip_cents, total_cents,
                         giftcard_applied_cents, amount_due_cents,
                         contact_email, contact_name, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $publicToken, $customerId !== null ? (int) $customerId : null, 'standard', 'shipping', 'pending',
                        $cents, 0, 0, 0, $cents,
                        0, $cents,
                        $purchaserEmail, trim((string) $request->input('purchaser_name', '')) ?: null,
                        'Gift card purchase',
                    ]
                );
                Database::run(
                    'INSERT INTO order_items
                        (order_id, product_id, name_snapshot, unit_price_cents, quantity, tax_treatment, tax_amount_cents, line_total_cents)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$orderId, null, 'Gift card', $cents, 1, 'non_taxable', 0, $cents]
                );
                $card = GiftCard::purchase($cents, $purchaserEmail, $recipientEmail, $orderId);
                return ['order_id' => $orderId, 'card' => $card];
            });
        } catch (\Throwable $e) {
            error_log('[giftcard] purchase failed: ' . $e->getMessage());
            $this->fail('We could not start your gift card purchase. Please try again.');
            return;
        }

        $orderId = (int) $result['order_id'];

        $gateway = new StripeGateway();
        if (!$gateway->isConfigured()) {
            $this->fail('Online payment is not available yet. Your card was not charged.');
            return;
        }

        try {
            $intent = $gateway->createPaymentIntent($cents, 'usd', ['order_id' => $orderId, 'kind' => 'giftcard']);
        } catch (\Throwable $e) {
            error_log('[giftcard] PaymentIntent failed: ' . $e->getMessage());
            $this->fail('We could not start the payment. Your card was not charged. Please try again.');
            return;
        }

        Database::run('UPDATE orders SET stripe_payment_intent = ? WHERE id = ?', [$intent['id'], $orderId]);

        $_SESSION['_gc_pi'] = [
            'client_secret' => $intent['client_secret'],
            'publishable'   => $gateway->publishableKey(),
            'amount'        => $cents,
            'order_token'   => $publicToken,
            'return_url'    => rtrim((string) config('url'), '/') . url('/order/' . $publicToken),
        ];
        $_SESSION['_flash'] = ['type' => 'info', 'message' => 'Almost there, confirm payment to send your gift card.'];
        $this->redirect(url('/gift-cards'));
    }

    private function fail(string $message): void
    {
        $_SESSION['_flash'] = ['type' => 'error', 'message' => $message];
        $this->redirect(url('/gift-cards'));
    }
}
