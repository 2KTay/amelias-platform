<?php

declare(strict_types=1);

namespace Amelias\Services;

use Amelias\Database\Database;

/**
 * Reservation waitlist.
 *
 * When a slot is full a guest joins the waitlist (status `waiting`). The host
 * (or an automated sweep) `offer`s the next party a freed seat: the row flips
 * to `offered` with a 10-minute expiry. If the guest does not convert in time
 * the offer expires and rolls to the next waiting party. `convert` records that
 * the offered party booked.
 *
 * Timestamps are UTC.
 */
final class Waitlist
{
    /** Minutes an offer is held before it expires and rolls onward. */
    public const OFFER_TTL_MINUTES = 10;

    /**
     * Add a party to the waitlist for a slot (or general waitlist when slot is
     * null). Returns the new waitlist id.
     *
     * @param array{contact_name?:?string,contact_phone?:?string,contact_email?:?string} $contact
     */
    public static function add(?int $slotId, int $partySize, array $contact = []): int
    {
        return Database::insert(
            'INSERT INTO waitlist (slot_id, party_size, contact_name, contact_phone, contact_email, status)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $slotId,
                max(1, $partySize),
                $contact['contact_name'] ?? null,
                $contact['contact_phone'] ?? null,
                $contact['contact_email'] ?? null,
                'waiting',
            ]
        );
    }

    /**
     * Offer the freed seat to a specific waiting party: status -> offered, stamp
     * offered_at = now and offer_expires_at = now + 10min. Atomic guard so the
     * same row is offered only once. Returns true when the offer was made.
     */
    public static function offer(int $waitlistId): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expires = $now->modify('+' . self::OFFER_TTL_MINUTES . ' minutes');

        return Database::affected(
            "UPDATE waitlist
                SET status = 'offered', offered_at = ?, offer_expires_at = ?
              WHERE id = ? AND status = 'waiting'",
            [$now->format('Y-m-d H:i:s'), $expires->format('Y-m-d H:i:s'), $waitlistId]
        ) === 1;
    }

    /**
     * Offer the freed seat to the longest-waiting party that fits a slot. Returns
     * the offered waitlist row, or null when none is waiting.
     *
     * @return array<string,mixed>|null
     */
    public static function offerNext(?int $slotId, int $minPartyFits = PHP_INT_MAX): ?array
    {
        $next = Database::fetch(
            "SELECT * FROM waitlist
              WHERE status = 'waiting'
                AND party_size <= ?
                AND (slot_id = ? OR slot_id IS NULL)
              ORDER BY created_at
              LIMIT 1",
            [$minPartyFits, $slotId]
        );
        if ($next === null) {
            return null;
        }
        if (self::offer((int) $next['id'])) {
            return Database::fetch('SELECT * FROM waitlist WHERE id = ?', [(int) $next['id']]);
        }
        return null;
    }

    /**
     * Expire offers past their TTL and roll each onward to the next waiting
     * party for the same slot. Returns the number of offers that expired.
     * Idempotent / safe to run repeatedly (e.g. from cron).
     */
    public static function rollExpired(): int
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $expired = Database::fetchAll(
            "SELECT id, slot_id, party_size FROM waitlist
              WHERE status = 'offered' AND offer_expires_at IS NOT NULL AND offer_expires_at <= ?",
            [$now]
        );
        $count = 0;
        foreach ($expired as $row) {
            $flipped = Database::affected(
                "UPDATE waitlist SET status = 'expired' WHERE id = ? AND status = 'offered'",
                [(int) $row['id']]
            );
            if ($flipped !== 1) {
                continue;
            }
            $count++;
            // Roll the freed seat to the next waiting party for that slot.
            self::offerNext($row['slot_id'] !== null ? (int) $row['slot_id'] : null);
        }
        return $count;
    }

    /** Mark an offered party converted (they booked). Idempotent. */
    public static function convert(int $waitlistId): bool
    {
        return Database::affected(
            "UPDATE waitlist SET status = 'converted' WHERE id = ? AND status IN ('waiting','offered')",
            [$waitlistId]
        ) === 1;
    }

    /**
     * Waiting + offered parties (for the host side panel), oldest first.
     *
     * @return list<array<string,mixed>>
     */
    public static function active(?int $slotId = null): array
    {
        if ($slotId !== null) {
            return Database::fetchAll(
                "SELECT * FROM waitlist
                  WHERE status IN ('waiting','offered') AND (slot_id = ? OR slot_id IS NULL)
                  ORDER BY created_at",
                [$slotId]
            );
        }
        return Database::fetchAll(
            "SELECT * FROM waitlist WHERE status IN ('waiting','offered') ORDER BY created_at"
        );
    }
}
