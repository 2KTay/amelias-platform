<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;

/**
 * Reservation booking engine.
 *
 * Capacity is booked against pre-generated booking_slots (see
 * cron/generate_slots.php). A physical `tables` row is assigned only at seat
 * time, bookings.table_id stays NULL until then (C-V5-1).
 *
 * The critical invariant is that two guests can never claim the last seat in a
 * slot. claimSlot() enforces this with a single guarded UPDATE
 *   UPDATE booking_slots SET booked_count = booked_count + 1
 *    WHERE id = ? AND booked_count < capacity AND is_blackout = 0
 * and checks the affected-row count: exactly one concurrent caller wins. No
 * read-then-write race window exists because the guard lives inside the UPDATE.
 *
 * Money is integer cents; timestamps are stored/compared in UTC and only
 * converted to America/Phoenix for display.
 */
final class Booking
{
    /** Minutes a held reservation is kept while a deposit is pending. */
    public const HELD_TTL_MINUTES = 30;

    /**
     * Open slots for a party on a given local (Phoenix) date.
     *
     * A slot is bookable when it is not a blackout, has remaining capacity
     * (booked_count < capacity), is in the future, and respects the configured
     * lead time. The Phoenix calendar day is converted to a UTC window so the
     * stored UTC slot_start values compare correctly.
     *
     * @param int    $partySize  guests (only used to filter; capacity is per-cover)
     * @param string $date       YYYY-MM-DD in America/Phoenix
     * @param string $resourceType 'dining' | 'event'
     * @return list<array{id:int,resource_id:int,slot_start:string,capacity:int,booked_count:int,remaining:int,event_product_id:?int}>
     */
    public static function availability(int $partySize, string $date, string $resourceType = 'dining'): array
    {
        $partySize = max(1, $partySize);
        [$startUtc, $endUtc] = self::dayWindowUtc($date);

        // Earliest bookable instant = now + lead time (minutes).
        $leadMinutes = (int) (Settings::get('reservation.lead_time_minutes', '90') ?? '90');
        $earliest = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('+' . max(0, $leadMinutes) . ' minutes')
            ->format('Y-m-d H:i:s');

        $rows = Database::fetchAll(
            "SELECT s.id, s.resource_id, s.slot_start, s.capacity, s.booked_count, s.event_product_id
               FROM booking_slots s
               JOIN booking_resources r ON r.id = s.resource_id
              WHERE r.type = ?
                AND r.is_active = 1
                AND s.is_blackout = 0
                AND s.slot_start >= ?
                AND s.slot_start >= ?
                AND s.slot_start < ?
              ORDER BY s.slot_start",
            [$resourceType, $startUtc, $earliest, $endUtc]
        );

        $out = [];
        foreach ($rows as $r) {
            $capacity = (int) $r['capacity'];
            $booked   = (int) $r['booked_count'];
            $remaining = $capacity - $booked;
            $out[] = [
                'id'               => (int) $r['id'],
                'resource_id'      => (int) $r['resource_id'],
                'slot_start'       => (string) $r['slot_start'],
                'capacity'         => $capacity,
                'booked_count'     => $booked,
                'remaining'        => max(0, $remaining),
                'event_product_id' => $r['event_product_id'] !== null ? (int) $r['event_product_id'] : null,
            ];
        }
        return $out;
    }

    /**
     * Atomically claim one cover in a slot and create the booking.
     *
     * Returns the new booking id on success, or null when the slot is full /
     * blacked out / missing (the guarded UPDATE affected zero rows). This is the
     * concurrency-safe primitive: the WHERE clause is the guard, rowCount() is
     * the verdict, no separate read happens, so there is no TOCTOU window.
     *
     * @param array{customer_id?:?int,party_size?:int,status?:string,contact_name?:?string,contact_email?:?string,contact_phone?:?string,notes?:?string,deposit_order_id?:?int} $details
     */
    public static function claimSlot(int $slotId, int $partySize, array $details = []): ?int
    {
        $partySize = max(1, $partySize);
        $status = $details['status'] ?? 'confirmed';

        return Database::transaction(static function () use ($slotId, $partySize, $details, $status): ?int {
            // Guarded atomic claim, only succeeds while there is room.
            $affected = Database::affected(
                'UPDATE booking_slots
                    SET booked_count = booked_count + 1
                  WHERE id = ? AND booked_count < capacity AND is_blackout = 0',
                [$slotId]
            );
            if ($affected !== 1) {
                return null; // full, blacked out, or no such slot
            }

            $token = bin2hex(random_bytes(16)); // 32-char public_token
            $bookingId = Database::insert(
                'INSERT INTO bookings
                    (public_token, slot_id, table_id, customer_id, party_size, status,
                     deposit_order_id, contact_name, contact_email, contact_phone, notes)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $token,
                    $slotId,
                    $details['customer_id'] ?? null,
                    $partySize,
                    $status,
                    $details['deposit_order_id'] ?? null,
                    $details['contact_name'] ?? null,
                    $details['contact_email'] ?? null,
                    $details['contact_phone'] ?? null,
                    $details['notes'] ?? null,
                ]
            );
            return $bookingId;
        });
    }

    /** Attach a pending deposit order to a held booking (deposit flow). */
    public static function attachDeposit(int $bookingId, int $orderId): void
    {
        Database::run(
            'UPDATE bookings SET deposit_order_id = ? WHERE id = ?',
            [$orderId, $bookingId]
        );
    }

    /**
     * Reconcile a held-with-deposit booking against its deposit order. When the
     * Stripe webhook has flipped the deposit order to paid, promote the booking
     * held -> confirmed. Idempotent; safe to call on every view of the booking.
     */
    public static function reconcileDeposit(int $bookingId): void
    {
        $row = Database::fetch(
            "SELECT b.id, b.status, b.deposit_order_id, o.status AS order_status
               FROM bookings b
               JOIN orders o ON o.id = b.deposit_order_id
              WHERE b.id = ? AND b.deposit_order_id IS NOT NULL",
            [$bookingId]
        );
        if ($row === null) {
            return;
        }
        if ($row['status'] === 'held' && $row['order_status'] === 'paid') {
            Database::run(
                "UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'held'",
                [$bookingId]
            );
        }
    }

    /** Fetch a booking by its public token, with slot + resource context. */
    public static function findByToken(string $token): ?array
    {
        return Database::fetch(
            "SELECT b.*, s.slot_start, s.resource_id, r.name AS resource_name, r.type AS resource_type,
                    o.status AS deposit_status, o.total_cents AS deposit_cents
               FROM bookings b
               JOIN booking_slots s ON s.id = b.slot_id
               JOIN booking_resources r ON r.id = s.resource_id
               LEFT JOIN orders o ON o.id = b.deposit_order_id
              WHERE b.public_token = ?",
            [$token]
        );
    }

    /**
     * Host: seat a confirmed/held booking at a physical table. Assigns table_id,
     * flips booking -> seated, and marks the table seated. Atomic.
     */
    public static function seat(int $bookingId, int $tableId): bool
    {
        return (bool) Database::transaction(static function () use ($bookingId, $tableId): bool {
            $booking = Database::fetch(
                "SELECT id, status FROM bookings WHERE id = ? FOR UPDATE",
                [$bookingId]
            );
            if ($booking === null || in_array($booking['status'], ['seated', 'completed', 'cancelled', 'no_show'], true)) {
                return false;
            }
            // Claim the table only if it is currently open.
            $claimed = Database::affected(
                "UPDATE tables SET status = 'seated' WHERE id = ? AND status = 'open'",
                [$tableId]
            );
            if ($claimed !== 1) {
                return false;
            }
            Database::run(
                "UPDATE bookings SET table_id = ?, status = 'seated' WHERE id = ?",
                [$tableId, $bookingId]
            );
            return true;
        });
    }

    /**
     * Host: complete a seated booking (table turns). Frees the slot cover and
     * marks the physical table dirty for bussing. Atomic + idempotent.
     */
    public static function complete(int $bookingId): bool
    {
        return (bool) Database::transaction(static function () use ($bookingId): bool {
            $booking = Database::fetch(
                "SELECT id, slot_id, table_id, status FROM bookings WHERE id = ? FOR UPDATE",
                [$bookingId]
            );
            if ($booking === null || in_array($booking['status'], ['completed', 'cancelled', 'no_show'], true)) {
                return false;
            }
            Database::run("UPDATE bookings SET status = 'completed' WHERE id = ?", [$bookingId]);
            self::releaseCover((int) $booking['slot_id']);
            if ($booking['table_id'] !== null) {
                Database::run("UPDATE tables SET status = 'dirty' WHERE id = ?", [(int) $booking['table_id']]);
            }
            return true;
        });
    }

    /**
     * Host: clear/cancel a booking (no-show or removed before service). Frees the
     * slot cover and releases any assigned table back to open. Atomic.
     *
     * @param string $status 'cancelled' | 'no_show'
     */
    public static function clear(int $bookingId, string $status = 'cancelled'): bool
    {
        $status = in_array($status, ['cancelled', 'no_show'], true) ? $status : 'cancelled';
        return (bool) Database::transaction(static function () use ($bookingId, $status): bool {
            $booking = Database::fetch(
                "SELECT id, slot_id, table_id, status FROM bookings WHERE id = ? FOR UPDATE",
                [$bookingId]
            );
            if ($booking === null || in_array($booking['status'], ['completed', 'cancelled', 'no_show'], true)) {
                return false;
            }
            Database::run("UPDATE bookings SET status = ? WHERE id = ?", [$status, $bookingId]);
            self::releaseCover((int) $booking['slot_id']);
            if ($booking['table_id'] !== null) {
                Database::run("UPDATE tables SET status = 'open' WHERE id = ?", [(int) $booking['table_id']]);
            }
            return true;
        });
    }

    /**
     * Host: mark a dirty table turned (bussed) and available again. Convenience
     * for the floor dashboard "Turn" action.
     */
    public static function turn(int $tableId): bool
    {
        return Database::affected(
            "UPDATE tables SET status = 'open' WHERE id = ? AND status IN ('dirty','seated')",
            [$tableId]
        ) === 1;
    }

    /** Free one cover from a slot, never going below zero. */
    private static function releaseCover(int $slotId): void
    {
        Database::run(
            'UPDATE booking_slots SET booked_count = booked_count - 1 WHERE id = ? AND booked_count > 0',
            [$slotId]
        );
    }

    /**
     * Convert a Phoenix calendar date (YYYY-MM-DD) to a [startUtc, endUtc)
     * window covering that whole local day, formatted for DATETIME comparison.
     *
     * @return array{0:string,1:string}
     */
    private static function dayWindowUtc(string $date): array
    {
        $tz = new \DateTimeZone(FMT_TZ);
        try {
            $start = new \DateTimeImmutable($date . ' 00:00:00', $tz);
        } catch (\Exception) {
            $start = new \DateTimeImmutable('today', $tz);
        }
        $end = $start->modify('+1 day');
        $utc = new \DateTimeZone('UTC');
        return [
            $start->setTimezone($utc)->format('Y-m-d H:i:s'),
            $end->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }
}
