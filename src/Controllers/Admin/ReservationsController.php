<?php

declare(strict_types=1);

namespace Amelias\Controllers\Admin;

use Amelias\Database\Database;
use Amelias\Http\Request;
use Amelias\Services\Booking;
use Amelias\Services\Waitlist;

/**
 * Host reservations dashboard (screen 21), owner / manager / staff.
 *
 * Day view of the service book (timeline of bookings for the selected Phoenix
 * date), with seat / clear / turn floor actions, waitlist management, deposit
 * status flags, and walk-in seating. Seating assigns a physical tables.table_id
 * from the floor (table_id is NULL until then).
 */
final class ReservationsController extends AdminController
{
    public function index(Request $request): void
    {
        $this->requireRole('owner', 'manager', 'staff');

        if ($request->isPost()) {
            $this->action($request);
            return;
        }

        $tz = new \DateTimeZone(FMT_TZ);
        $today = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        $date = $request->query('date', $today) ?? $today;

        [$startUtc, $endUtc] = $this->dayWindowUtc($date);

        $bookings = Database::fetchAll(
            "SELECT b.id, b.public_token, b.party_size, b.status, b.table_id,
                    b.contact_name, b.contact_phone, b.notes, b.deposit_order_id,
                    s.slot_start, t.label AS table_label,
                    o.status AS deposit_status
               FROM bookings b
               JOIN booking_slots s ON s.id = b.slot_id
               LEFT JOIN tables t ON t.id = b.table_id
               LEFT JOIN orders o ON o.id = b.deposit_order_id
              WHERE s.slot_start >= ? AND s.slot_start < ?
                AND b.status NOT IN ('cancelled')
              ORDER BY s.slot_start, b.id",
            [$startUtc, $endUtc]
        );

        $openTables = Database::fetchAll(
            "SELECT id, label, section, min_party, max_party
               FROM tables WHERE status = 'open' ORDER BY sort, label"
        );
        $allTables = Database::fetchAll(
            "SELECT id, label, status FROM tables ORDER BY sort, label"
        );

        $covers = 0;
        foreach ($bookings as $b) {
            if (!in_array($b['status'], ['cancelled', 'no_show'], true)) {
                $covers += (int) $b['party_size'];
            }
        }

        $this->viewAdmin('admin/reservations', [
            'title'      => 'Reservations',
            'styles'     => ['pages/admin-reservations.css'],
            'date'       => $date,
            'prevDate'   => (new \DateTimeImmutable($date, $tz))->modify('-1 day')->format('Y-m-d'),
            'nextDate'   => (new \DateTimeImmutable($date, $tz))->modify('+1 day')->format('Y-m-d'),
            'dayLabel'   => (new \DateTimeImmutable($date, $tz))->format('l, F j · Y'),
            'bookings'   => $bookings,
            'covers'     => $covers,
            'openTables' => $openTables,
            'allTables'  => $allTables,
            'waitlist'   => Waitlist::active(),
        ]);
    }

    /** Dispatch a floor action (seat / clear / no-show / turn / confirm / walk-in / waitlist). */
    private function action(Request $request): void
    {
        $this->requireRole('owner', 'manager', 'staff');

        $date = (string) $request->input('date', '');
        $redirect = url('/admin/reservations' . ($date !== '' ? '?date=' . rawurlencode($date) : ''));

        if (!csrf_verify((string) $request->input('_csrf'))) {
            $this->redirect($redirect);
            return;
        }

        $action = (string) $request->input('action', '');
        $bookingId = (int) $request->input('booking_id', 0);

        switch ($action) {
            case 'seat':
                $tableId = (int) $request->input('table_id', 0);
                if ($bookingId > 0 && $tableId > 0) {
                    Booking::seat($bookingId, $tableId);
                }
                break;

            case 'complete':
                if ($bookingId > 0) {
                    Booking::complete($bookingId);
                }
                break;

            case 'clear':
                if ($bookingId > 0) {
                    Booking::clear($bookingId, 'cancelled');
                }
                break;

            case 'no_show':
                if ($bookingId > 0) {
                    Booking::clear($bookingId, 'no_show');
                }
                break;

            case 'confirm':
                if ($bookingId > 0) {
                    Database::run(
                        "UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'held'",
                        [$bookingId]
                    );
                }
                break;

            case 'turn':
                $tableId = (int) $request->input('table_id', 0);
                if ($tableId > 0) {
                    Booking::turn($tableId);
                }
                break;

            case 'walkin':
                $this->seatWalkIn($request);
                break;

            case 'waitlist_seat':
                $waitlistId = (int) $request->input('waitlist_id', 0);
                if ($waitlistId > 0) {
                    Waitlist::convert($waitlistId);
                }
                break;

            case 'waitlist_add':
                $party = max(1, (int) $request->input('party', 1));
                Waitlist::add(null, $party, [
                    'contact_name'  => trim((string) $request->input('name', '')) ?: null,
                    'contact_phone' => trim((string) $request->input('phone', '')) ?: null,
                ]);
                break;
        }

        $this->redirect($redirect);
    }

    /**
     * Seat a walk-in: claim the next dining slot for the party and immediately
     * seat them at the chosen open table.
     */
    private function seatWalkIn(Request $request): void
    {
        $party = max(1, (int) $request->input('party', 1));
        $tableId = (int) $request->input('table_id', 0);
        $name = trim((string) $request->input('name', '')) ?: 'Walk-in';
        if ($tableId <= 0) {
            return;
        }

        // Use the current/most-recent dining slot so the walk-in still books a cover.
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $slot = Database::fetch(
            "SELECT s.id FROM booking_slots s
               JOIN booking_resources r ON r.id = s.resource_id
              WHERE r.type = 'dining' AND s.is_blackout = 0 AND s.booked_count < s.capacity
                AND s.slot_start <= ?
              ORDER BY s.slot_start DESC LIMIT 1",
            [$now]
        );
        if ($slot === null) {
            // Fall back to the next upcoming open slot.
            $slot = Database::fetch(
                "SELECT s.id FROM booking_slots s
                   JOIN booking_resources r ON r.id = s.resource_id
                  WHERE r.type = 'dining' AND s.is_blackout = 0 AND s.booked_count < s.capacity
                  ORDER BY s.slot_start LIMIT 1",
                []
            );
        }
        if ($slot === null) {
            return;
        }

        $bookingId = Booking::claimSlot((int) $slot['id'], $party, [
            'status'       => 'confirmed',
            'contact_name' => $name,
            'notes'        => 'Walk-in',
        ]);
        if ($bookingId !== null) {
            Booking::seat($bookingId, $tableId);
        }
    }

    /** @return array{0:string,1:string} */
    private function dayWindowUtc(string $date): array
    {
        $tz = new \DateTimeZone(FMT_TZ);
        try {
            $start = new \DateTimeImmutable($date . ' 00:00:00', $tz);
        } catch (\Exception) {
            $start = new \DateTimeImmutable('today', $tz);
        }
        $utc = new \DateTimeZone('UTC');
        return [
            $start->setTimezone($utc)->format('Y-m-d H:i:s'),
            $start->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }
}
