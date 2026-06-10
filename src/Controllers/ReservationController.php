<?php

declare(strict_types=1);

namespace Amelias\Controllers;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\Auth;
use Amelias\Services\Booking;
use Amelias\Services\Notifications\Notifier;
use Amelias\Services\Payments\StripeGateway;
use Amelias\Services\Settings;
use Amelias\Services\Waitlist;

/**
 * Public reservations (screen 12).
 *
 * Flow: party size + date -> real-time slot chips (full ones disabled) ->
 * guest details -> confirm. Large parties (>= deposit threshold) and ticketed
 * events take a Stripe deposit: a pending deposit order + PaymentIntent is
 * created and the slot is HELD until the deposit succeeds (the webhook flips
 * the order to paid; reconcileDeposit promotes held -> confirmed). Standard
 * parties confirm immediately. When a slot is full the guest can join the
 * waitlist. Modify/cancel happens through a tokenized link (no enumeration):
 * full refund before the cancel cutoff, forfeit after.
 *
 * Money is integer cents; UTC stored, Phoenix displayed.
 */
final class ReservationController extends Controller
{
    /** GET shows the booking form; POST creates the reservation. */
    public function index(Request $request): void
    {
        if ($request->isPost()) {
            $this->create($request);
            return;
        }

        $tz = new \DateTimeZone(FMT_TZ);
        $today = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        $date = $request->query('date', $today) ?? $today;
        $party = max(1, (int) ($request->query('party', '2') ?? '2'));

        $slots = Booking::availability($party, $date);

        $this->viewPublic('public/reserve', [
            'title'           => 'Reserve a table',
            'styles'          => ['pages/reserve.css'],
            'date'            => $date,
            'minDate'         => $today,
            'party'           => $party,
            'slots'           => $slots,
            'depositThreshold' => $this->depositThreshold(),
            'error'           => $this->flash('reserve_error'),
        ]);
    }

    /**
     * Availability JSON for a date (consumed by the slot picker as the date /
     * party changes). Returns the slots with display labels.
     */
    public function availability(Request $request): void
    {
        $tz = new \DateTimeZone(FMT_TZ);
        $today = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        $date = $request->query('date', $today) ?? $today;
        $party = max(1, (int) ($request->query('party', '2') ?? '2'));

        $slots = array_map(static function (array $s): array {
            return [
                'id'        => $s['id'],
                'label'     => fmt_date($s['slot_start'], 'g:ia'),
                'remaining' => $s['remaining'],
                'full'      => $s['remaining'] <= 0,
            ];
        }, Booking::availability($party, $date));

        $this->json([
            'date'             => $date,
            'party'            => $party,
            'depositThreshold' => $this->depositThreshold(),
            'slots'            => $slots,
        ]);
    }

    /** Create a reservation (confirm) or join the waitlist. */
    private function create(Request $request): void
    {
        if (!csrf_verify((string) $request->input('_csrf'))) {
            $this->fail('Your session expired. Please try again.');
            return;
        }

        $action = (string) $request->input('action', 'reserve');
        $slotId = (int) $request->input('slot_id', 0);
        $party  = max(1, (int) $request->input('party', 1));
        $name   = trim((string) $request->input('name', ''));
        $email  = trim((string) $request->input('email', ''));
        $phone  = trim((string) $request->input('phone', ''));
        $notes  = trim((string) $request->input('requests', ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('Please give us your name and a valid email.');
            return;
        }

        $customer = Auth::findOrCreateCustomer($email, [
            'first_name' => $name,
            'phone'      => $phone !== '' ? $phone : null,
        ]);
        $customerId = (int) ($customer['id'] ?? 0) ?: null;

        // Waitlist path (slot full or guest opted in).
        if ($action === 'waitlist') {
            Waitlist::add($slotId > 0 ? $slotId : null, $party, [
                'contact_name'  => $name,
                'contact_phone' => $phone !== '' ? $phone : null,
                'contact_email' => $email,
            ]);
            $this->viewPublic('public/reserve-confirmed', [
                'title'    => 'You are on the waitlist',
                'styles'   => ['pages/reserve.css'],
                'waitlist' => true,
            ]);
            return;
        }

        $slot = Database::fetch(
            'SELECT s.id, s.slot_start, s.capacity, s.booked_count, s.event_product_id
               FROM booking_slots s WHERE s.id = ?',
            [$slotId]
        );
        if ($slot === null) {
            $this->fail('Please pick an available time.');
            return;
        }

        $isEvent = $slot['event_product_id'] !== null;
        $needsDeposit = $isEvent || $party >= $this->depositThreshold();

        // Deposit path: hold the slot, create a pending deposit order + intent.
        if ($needsDeposit) {
            $this->reserveWithDeposit($slotId, $party, $customerId, $name, $email, $phone, $notes);
            return;
        }

        // Standard path: confirm immediately via the atomic claim.
        $bookingId = Booking::claimSlot($slotId, $party, [
            'customer_id'   => $customerId,
            'status'        => 'confirmed',
            'contact_name'  => $name,
            'contact_email' => $email,
            'contact_phone' => $phone !== '' ? $phone : null,
            'notes'         => $notes !== '' ? $notes : null,
        ]);

        if ($bookingId === null) {
            // Lost the race / slot filled, offer the waitlist instead.
            $this->fail('That time just filled up. Pick another time or join the waitlist.');
            return;
        }

        $this->confirmAndNotify($bookingId);
    }

    /**
     * Deposit reservation: atomically hold the slot, create a pending deposit
     * order linked to the booking, and a Stripe PaymentIntent. The slot stays
     * HELD until the deposit succeeds (webhook -> reconcileDeposit).
     */
    private function reserveWithDeposit(
        int $slotId,
        int $party,
        ?int $customerId,
        string $name,
        string $email,
        string $phone,
        string $notes
    ): void {
        $bookingId = Booking::claimSlot($slotId, $party, [
            'customer_id'   => $customerId,
            'status'        => 'held',
            'contact_name'  => $name,
            'contact_email' => $email,
            'contact_phone' => $phone !== '' ? $phone : null,
            'notes'         => $notes !== '' ? $notes : null,
        ]);
        if ($bookingId === null) {
            $this->fail('That time just filled up. Pick another time or join the waitlist.');
            return;
        }

        $depositCents = $this->depositCents() * $party; // per-guest deposit
        $token = bin2hex(random_bytes(16));
        $orderId = Database::insert(
            'INSERT INTO orders
                (public_token, customer_id, kind, fulfillment, status,
                 subtotal_cents, total_cents, amount_due_cents,
                 contact_email, contact_phone, contact_name, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $token, $customerId, 'standard', 'dine_in', 'pending',
                $depositCents, $depositCents, $depositCents,
                $email, $phone !== '' ? $phone : null, $name, 'Reservation deposit',
            ]
        );
        Booking::attachDeposit($bookingId, $orderId);

        $gateway = new StripeGateway();
        $clientSecret = null;
        if ($gateway->isConfigured()) {
            try {
                $intent = $gateway->createPaymentIntent($depositCents, 'usd', [
                    'order_id'   => $orderId,
                    'booking_id' => $bookingId,
                    'kind'       => 'reservation_deposit',
                ]);
                $clientSecret = $intent['client_secret'];
                Database::run(
                    'UPDATE orders SET stripe_payment_intent = ? WHERE id = ?',
                    [$intent['id'], $orderId]
                );
            } catch (\Throwable $e) {
                error_log('[reservation:deposit] ' . $e->getMessage());
            }
        }

        $booking = Database::fetch('SELECT public_token FROM bookings WHERE id = ?', [$bookingId]);

        $this->viewPublic('public/reserve-deposit', [
            'title'         => 'Secure your table',
            'styles'        => ['pages/reserve.css'],
            'depositCents'  => $depositCents,
            'party'         => $party,
            'clientSecret'  => $clientSecret,
            'publishableKey' => $gateway->publishableKey(),
            'bookingToken'  => (string) ($booking['public_token'] ?? ''),
            'stripeReady'   => $clientSecret !== null,
        ]);
    }

    /** Tokenized manage page: GET shows details, POST modifies/cancels. */
    public function manage(Request $request): void
    {
        $token = (string) $request->param('token');
        $booking = Booking::findByToken($token);
        if ($booking === null) {
            $this->viewPublic('errors/404', ['path' => $request->path], 404);
            return;
        }
        // Promote held->confirmed if the deposit cleared since last view.
        Booking::reconcileDeposit((int) $booking['id']);
        $booking = Booking::findByToken($token);
        if ($booking === null) {
            $this->viewPublic('errors/404', ['path' => $request->path], 404);
            return;
        }

        if ($request->isPost()) {
            $this->modify($request, $booking);
            return;
        }

        $this->viewPublic('public/reservation-manage', [
            'title'      => 'Your reservation',
            'styles'     => ['pages/reserve.css'],
            'booking'    => $booking,
            'canCancel'  => $this->beforeCutoff((string) $booking['slot_start']),
            'cutoffHours' => $this->cancelCutoffHours(),
            'notice'     => $this->flash('reserve_notice'),
        ]);
    }

    /** Apply a cancel (only action supported on the manage page for now). */
    private function modify(Request $request, array $booking): void
    {
        if (!csrf_verify((string) $request->input('_csrf'))) {
            $this->redirect(url('/reservation/' . $booking['public_token']));
            return;
        }
        $action = (string) $request->input('action', '');

        if ($action === 'cancel') {
            $beforeCutoff = $this->beforeCutoff((string) $booking['slot_start']);
            // Forfeit after cutoff: cancel the booking but keep the deposit.
            // Before cutoff: full refund of any deposit.
            if ($beforeCutoff && $booking['deposit_order_id'] !== null && ($booking['deposit_status'] ?? null) === 'paid') {
                $this->refundDeposit((int) $booking['deposit_order_id']);
            }
            Booking::clear((int) $booking['id'], 'cancelled');
            $_SESSION['_flash']['reserve_notice'] = $beforeCutoff
                ? 'Your reservation is cancelled. Any deposit will be refunded in full.'
                : 'Your reservation is cancelled. Per our policy the deposit is non-refundable this close to service.';
        }

        $this->redirect(url('/reservation/' . $booking['public_token']));
    }

    /** Issue a full refund for a paid deposit order via Stripe. */
    private function refundDeposit(int $orderId): void
    {
        $order = Database::fetch('SELECT stripe_payment_intent FROM orders WHERE id = ?', [$orderId]);
        $intent = (string) ($order['stripe_payment_intent'] ?? '');
        if ($intent === '') {
            return;
        }
        try {
            (new StripeGateway())->refund($intent); // full refund; webhook records the ledger
        } catch (\Throwable $e) {
            error_log('[reservation:refund] ' . $e->getMessage());
        }
    }

    /** Confirm a standard booking and send the confirmation notification. */
    private function confirmAndNotify(int $bookingId): void
    {
        $booking = Database::fetch(
            'SELECT b.*, s.slot_start FROM bookings b JOIN booking_slots s ON s.id = b.slot_id WHERE b.id = ?',
            [$bookingId]
        );
        if ($booking === null) {
            $this->fail('Something went wrong confirming your table. Please call us.');
            return;
        }

        $manageUrl = rtrim((string) config('url'), '/') . url('/reservation/' . $booking['public_token']);
        (new Notifier())->notify(
            'booking',
            (string) $bookingId,
            'reservation-confirmation',
            ['email' => (string) $booking['contact_email']],
            [
                'subject'    => "Your reservation at " . config('name'),
                'name'       => $booking['contact_name'],
                'partySize'  => (int) $booking['party_size'],
                'dateTime'   => fmt_date((string) $booking['slot_start'], 'l, F j · g:i A'),
                'manageUrl'  => $manageUrl,
            ],
            'email'
        );

        $this->viewPublic('public/reserve-confirmed', [
            'title'      => 'Reservation confirmed',
            'styles'     => ['pages/reserve.css'],
            'booking'    => $booking,
            'when'       => fmt_date((string) $booking['slot_start'], 'l, F j · g:i A'),
            'manageUrl'  => url('/reservation/' . $booking['public_token']),
        ]);
    }

    private function beforeCutoff(string $slotStartUtc): bool
    {
        $cutoffHours = $this->cancelCutoffHours();
        try {
            $start = new \DateTimeImmutable($slotStartUtc, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return false;
        }
        $deadline = $start->modify('-' . $cutoffHours . ' hours');
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC')) < $deadline;
    }

    private function depositThreshold(): int
    {
        return max(1, (int) (Settings::get('reservation.deposit_threshold', '8') ?? '8'));
    }

    private function depositCents(): int
    {
        return max(0, (int) (Settings::get('reservation.deposit_cents', '1000') ?? '1000'));
    }

    private function cancelCutoffHours(): int
    {
        return max(0, (int) (Settings::get('reservation.cancel_cutoff_hours', '24') ?? '24'));
    }

    /** Flash an error and re-render the form. */
    private function fail(string $message): void
    {
        $_SESSION['_flash']['reserve_error'] = $message;
        $this->redirect(url('/reserve'));
    }

    /** Read + clear a one-shot flash message. */
    private function flash(string $key): ?string
    {
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value !== null ? (string) $value : null;
    }
}
