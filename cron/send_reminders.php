<?php

declare(strict_types=1);

/**
 * Reservation reminders (Task 3.2).
 *
 * Sends the `reservation-reminder` notification to confirmed/seated bookings
 * whose slot_start falls within the next reminder window. Run every 15 minutes
 * by cron/dispatcher.php.
 *
 * Guarantees:
 *   - flock-guarded (CronLock) so overlapping ticks never double-send.
 *   - EXACTLY ONCE per booking: Notifier enforces send-once via notification_log
 *     keyed on (entity_type, entity_id, template, channel). The query selects a
 *     window so a booking is eligible on a few ticks, but the log gate dedupes.
 *   - Also rolls expired waitlist offers onward while we're here.
 *
 * Configurable via Settings:
 *   reservation.reminder_window_hours  (default 3 — remind ~3h before service)
 *   reservation.reminder_lead_minutes  (default 30 — width of the eligibility band)
 */

use Amelias\Database\Database;
use Amelias\Services\Notifications\Notifier;
use Amelias\Services\Settings;
use Amelias\Services\Waitlist;
use Amelias\Support\Cron;
use Amelias\Support\CronLock;

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$lock = CronLock::acquire('send_reminders');
if ($lock === null) {
    return;
}

// Roll any expired waitlist offers to the next party (cheap, idempotent).
Waitlist::rollExpired();

$windowHours = max(0, (int) (Settings::get('reservation.reminder_window_hours', '3') ?? '3'));
$bandMinutes = max(5, (int) (Settings::get('reservation.reminder_lead_minutes', '30') ?? '30'));

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
// Eligible band: slot_start between (now + window) and (now + window + band).
$bandStart = $now->modify("+{$windowHours} hours")->format('Y-m-d H:i:s');
$bandEnd   = $now->modify("+{$windowHours} hours")->modify("+{$bandMinutes} minutes")->format('Y-m-d H:i:s');

$bookings = Database::fetchAll(
    "SELECT b.id, b.contact_name, b.contact_email, b.party_size, b.public_token, s.slot_start
       FROM bookings b
       JOIN booking_slots s ON s.id = b.slot_id
      WHERE b.status IN ('confirmed', 'seated')
        AND b.contact_email IS NOT NULL
        AND s.slot_start >= ? AND s.slot_start < ?",
    [$bandStart, $bandEnd]
);

$notifier = new Notifier();
$sent = 0;
foreach ($bookings as $b) {
    $manageUrl = rtrim((string) config('url'), '/') . url('/reservation/' . $b['public_token']);
    $notifier->notify(
        'booking',
        (string) $b['id'],
        'reservation-reminder',
        ['email' => (string) $b['contact_email']],
        [
            'subject'    => 'Reminder: your reservation at ' . config('name'),
            'name'       => $b['contact_name'],
            'partySize'  => (int) $b['party_size'],
            'dateTime'   => fmt_date((string) $b['slot_start'], 'l, F j · g:i A'),
            'manageUrl'  => $manageUrl,
        ],
        'email'
    );
    $sent++;
}

if ($sent > 0) {
    Cron::log('send_reminders', "dispatched reminders for {$sent} booking(s)");
}
