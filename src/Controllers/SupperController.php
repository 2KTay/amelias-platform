<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\Auth;
use Amelias\Services\Payments\StripeGateway;
use Amelias\Support\Session;

/**
 * Sunday Supper (screen 15, Task 4.5).
 *
 * A monthly communal-table dinner sold as prepaid seats. Upcoming suppers are
 * booking_slots whose event_product_id points at a type='event' product (the
 * seeded 'sunday-supper'); each slot's capacity/booked_count tracks seats. The
 * per-seat price comes from the event product (price_cents).
 *
 * buy(): claim ONE seat atomically using the same guarded UPDATE pattern as
 * reservations (booked_count < capacity), create a pending order for the seat
 * price, and a Stripe one-time PaymentIntent. The webhook flips the order to
 * paid; the held seat is already counted so the table can't oversell. If
 * Stripe isn't configured the seat is still held and staff follow up.
 *
 * Restaurant-cancellation refunds every ticket (note shown to guests). Money is
 * integer cents; UTC stored / Phoenix displayed; CSRF on the buy form.
 */
final class SupperController extends Controller
{
    public function index(Request $request): void
    {
        // Upcoming supper slots (event slots in the future, not blacked out),
        // joined to their event product for name/price/description.
        $upcoming = Database::fetchAll(
            "SELECT s.id AS slot_id, s.slot_start, s.capacity, s.booked_count,
                    p.id AS product_id, p.name, p.description, p.price_cents
               FROM booking_slots s
               JOIN products p ON p.id = s.event_product_id
              WHERE s.event_product_id IS NOT NULL
                AND s.is_blackout = 0
                AND s.slot_start >= UTC_TIMESTAMP()
              ORDER BY s.slot_start ASC
              LIMIT 24"
        );

        $events = array_map(static function (array $r): array {
            $remaining = max(0, (int) $r['capacity'] - (int) $r['booked_count']);
            return [
                'slot_id'     => (int) $r['slot_id'],
                'name'        => (string) $r['name'],
                'description' => (string) ($r['description'] ?? ''),
                'price_cents' => (int) $r['price_cents'],
                'slot_start'  => (string) $r['slot_start'],
                'remaining'   => $remaining,
                'sold_out'    => $remaining <= 0,
            ];
        }, $upcoming);

        $this->viewPublic('public/supper', [
            'title'     => 'Sunday Supper',
            'bodyClass' => 'has-grain',
            'styles'    => ['pages/supper.css'],
            'events'    => $events,
            'payment'   => $this->takePayment(),
            'flash'     => $this->flash(),
        ]);
    }

    /** POST /sunday-supper/{id}/buy, claim a seat + start payment. */
    public function buy(Request $request): void
    {
        if (!csrf_verify((string) $request->input('_csrf'))) {
            $this->fail('Your session expired. Please try again.');
            return;
        }

        $slotId = (int) $request->param('id');
        $name   = trim((string) $request->input('name', ''));
        $email  = trim((string) $request->input('email', ''));
        $phone  = trim((string) $request->input('phone', ''));
        $notes  = trim((string) $request->input('notes', ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('Please give us your name and a valid email to hold your seat.');
            return;
        }
        if (!Auth::rateLimit('supper:' . $request->ip(), 8, 600)) {
            $this->fail('Too many attempts. Please wait a moment and try again.');
            return;
        }

        // Load the supper slot + its event product (price + capacity).
        $slot = Database::fetch(
            "SELECT s.id, s.slot_start, s.capacity, s.booked_count, s.is_blackout,
                    p.id AS product_id, p.name, p.price_cents
               FROM booking_slots s
               JOIN products p ON p.id = s.event_product_id
              WHERE s.id = ? AND s.event_product_id IS NOT NULL",
            [$slotId]
        );
        if ($slot === null) {
            $this->fail('That supper could not be found. Please choose an available date.');
            return;
        }

        $seatPriceCents = (int) $slot['price_cents'];
        if ($seatPriceCents <= 0) {
            $this->fail('Seats for this supper are not on sale yet. Please check back soon.');
            return;
        }

        $sessionCustomer = Session::customer();
        if ($sessionCustomer !== null) {
            $customerId = (int) $sessionCustomer['id'];
        } else {
            $created = Auth::findOrCreateCustomer($email, [
                'first_name' => $name,
                'phone'      => $phone !== '' ? $phone : null,
            ]);
            $customerId = (int) ($created['id'] ?? 0) ?: null;
        }

        // Atomic seat claim + pending order, in one transaction. The guarded
        // UPDATE only succeeds while seats remain, so no oversell on a race.
        $publicToken = bin2hex(random_bytes(16));
        try {
            $orderId = Database::transaction(static function () use (
                $slotId,
                $seatPriceCents,
                $customerId,
                $publicToken,
                $name,
                $email,
                $phone,
                $notes,
                $slot
            ): ?int {
                $claimed = Database::affected(
                    'UPDATE booking_slots
                        SET booked_count = booked_count + 1
                      WHERE id = ? AND booked_count < capacity AND is_blackout = 0',
                    [$slotId]
                );
                if ($claimed !== 1) {
                    return null; // sold out / lost the race
                }

                $orderId = Database::insert(
                    'INSERT INTO orders
                        (public_token, customer_id, kind, fulfillment, status,
                         subtotal_cents, total_cents, amount_due_cents,
                         contact_email, contact_phone, contact_name, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $publicToken, $customerId, 'standard', 'dine_in', 'pending',
                        $seatPriceCents, $seatPriceCents, $seatPriceCents,
                        $email, $phone !== '' ? $phone : null, $name,
                        'Sunday Supper seat, ' . (string) $slot['name']
                            . ' · ' . fmt_date((string) $slot['slot_start'], 'M j, Y')
                            . ($notes !== '' ? ' · ' . $notes : ''),
                    ]
                );
                Database::run(
                    'INSERT INTO order_items
                        (order_id, product_id, name_snapshot, unit_price_cents, quantity, tax_treatment, tax_amount_cents, line_total_cents)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $orderId, (int) $slot['product_id'],
                        'Sunday Supper seat, ' . (string) $slot['name'],
                        $seatPriceCents, 1, 'prepared_food', 0, $seatPriceCents,
                    ]
                );
                return $orderId;
            });
        } catch (\Throwable $e) {
            error_log('[supper] seat claim failed: ' . $e->getMessage());
            $this->fail('We could not hold your seat. Please try again.');
            return;
        }

        if ($orderId === null) {
            $this->fail('That supper just sold out. Please choose another date.');
            return;
        }

        $gateway = new StripeGateway();
        if (!$gateway->isConfigured()) {
            // Seat is held; payment isn't wired on this environment.
            $_SESSION['_flash'] = [
                'type'    => 'info',
                'message' => 'Your seat is held. Online payment isn\'t enabled here yet, our team will follow up to confirm your ticket.',
            ];
            $this->redirect(url('/sunday-supper'));
            return;
        }

        try {
            $intent = $gateway->createPaymentIntent($seatPriceCents, 'usd', [
                'order_id' => $orderId,
                'slot_id'  => $slotId,
                'kind'     => 'supper_ticket',
            ]);
        } catch (\Throwable $e) {
            error_log('[supper] PaymentIntent failed: ' . $e->getMessage());
            $this->fail('We could not start payment. Your card was not charged. Please try again.');
            return;
        }

        Database::run('UPDATE orders SET stripe_payment_intent = ? WHERE id = ?', [$intent['id'], $orderId]);

        $_SESSION['_supper_pi'] = [
            'client_secret' => $intent['client_secret'],
            'publishable'   => $gateway->publishableKey(),
            'amount'        => $seatPriceCents,
            'event'         => (string) $slot['name'],
            'when'          => fmt_date((string) $slot['slot_start'], 'l, F j · g:i A'),
            'order_token'   => $publicToken,
            'return_url'    => rtrim((string) config('url'), '/') . url('/order/' . $publicToken),
        ];
        $_SESSION['_flash'] = [
            'type'    => 'info',
            'message' => 'Almost there, confirm payment to claim your seat at the table.',
        ];
        $this->redirect(url('/sunday-supper'));
    }

    /** Read + clear the pending supper PaymentIntent payload. */
    private function takePayment(): ?array
    {
        $payment = $_SESSION['_supper_pi'] ?? null;
        unset($_SESSION['_supper_pi']);
        return is_array($payment) ? $payment : null;
    }

    private function fail(string $message): void
    {
        $_SESSION['_flash'] = ['type' => 'error', 'message' => $message];
        $this->redirect(url('/sunday-supper'));
    }

    private function flash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return is_array($flash) ? $flash : null;
    }
}
