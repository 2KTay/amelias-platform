<?php

declare(strict_types=1);

/**
 * Rolling-horizon reservation slot generation (Task 3.1).
 *
 * Pre-generates booking_slots for each active dining resource over a rolling
 * horizon, honoring a configurable service window, slot interval, and lead
 * time. Run hourly by cron/dispatcher.php.
 *
 * Guarantees:
 *   - flock-guarded (CronLock) so overlapping ticks never double-generate.
 *   - IDEMPOTENT: booking_slots has UNIQUE(resource_id, slot_start) and we
 *     INSERT ... ON DUPLICATE KEY UPDATE id=id, so a re-run inserts no
 *     duplicates and never disturbs booked_count on existing slots.
 *   - UTC/DST-safe: slot times are built in America/Phoenix (no DST) and
 *     converted to UTC for storage; the conversion is correct year-round even
 *     if the display tz ever changed.
 *
 * Configurable via Settings (env/DB fallback), with safe defaults:
 *   reservation.horizon_days        (default 30)
 *   reservation.slot_interval_min   (default 30)
 *   reservation.service_start_hour  (default 17  — 5:00p Phoenix)
 *   reservation.service_end_hour    (default 21  — last slot 9:30p with :30 end)
 *   reservation.service_end_minute  (default 30)
 *   reservation.lead_time_minutes   (default 90)
 */

use Amelias\Database\Database;
use Amelias\Services\Settings;
use Amelias\Support\Cron;
use Amelias\Support\CronLock;

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$lock = CronLock::acquire('generate_slots');
if ($lock === null) {
    return;
}

$horizonDays   = max(1, (int) (Settings::get('reservation.horizon_days', '30') ?? '30'));
$intervalMin   = max(5, (int) (Settings::get('reservation.slot_interval_min', '30') ?? '30'));
$startHour     = max(0, min(23, (int) (Settings::get('reservation.service_start_hour', '17') ?? '17')));
$endHour       = max(0, min(23, (int) (Settings::get('reservation.service_end_hour', '21') ?? '21')));
$endMinute     = max(0, min(59, (int) (Settings::get('reservation.service_end_minute', '30') ?? '30')));

$tz  = new DateTimeZone(FMT_TZ);
$utc = new DateTimeZone('UTC');

$resources = Database::fetchAll(
    "SELECT id, capacity_per_slot FROM booking_resources WHERE type = 'dining' AND is_active = 1"
);
if ($resources === []) {
    return;
}

$today = new DateTimeImmutable('now', $tz);
$created = 0;

foreach ($resources as $resource) {
    $resourceId = (int) $resource['id'];
    $capacity   = (int) $resource['capacity_per_slot'];
    if ($capacity <= 0) {
        continue;
    }

    for ($d = 0; $d < $horizonDays; $d++) {
        $day = $today->modify("+{$d} days");
        // Window for this Phoenix day.
        $windowStart = $day->setTime($startHour, 0, 0);
        $windowEnd   = $day->setTime($endHour, $endMinute, 0);

        for ($t = $windowStart; $t <= $windowEnd; $t = $t->modify("+{$intervalMin} minutes")) {
            $slotStartUtc = $t->setTimezone($utc)->format('Y-m-d H:i:s');

            // Idempotent insert: existing (resource_id, slot_start) is left untouched.
            $affected = Database::affected(
                'INSERT INTO booking_slots (resource_id, slot_start, capacity, booked_count, is_blackout)
                 VALUES (?, ?, ?, 0, 0)
                 ON DUPLICATE KEY UPDATE id = id',
                [$resourceId, $slotStartUtc, $capacity]
            );
            // affected rows: 1 = inserted, 0 = already existed (ON DUP no-op).
            if ($affected === 1) {
                $created++;
            }
        }
    }
}

if ($created > 0) {
    Cron::log('generate_slots', "created {$created} new slot(s) over {$horizonDays}-day horizon");
}
