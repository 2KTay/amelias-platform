<?php

declare(strict_types=1);

/**
 * Single cron entry point. The host runs ONE crontab line (every 5 minutes):
 *
 *   [*-slash-5] * * * * php /home/USER/app/cron/dispatcher.php >/dev/null 2>&1
 *   (see docs/runbooks/deploy.md for the literal crontab syntax)
 *
 * The dispatcher then runs each job on its own cadence via Cron::dueEvery(),
 * staggered so they never all fire on the same tick. Avoids a wall of
 * per-job crontab lines and keeps scheduling logic in code (C-T1-2).
 */

use Amelias\Support\Cron;

require dirname(__DIR__) . '/includes/bootstrap.php';

/** Run a job script if present and due. Each script returns its own exit int. */
$run = static function (string $script, callable $due) {
    $path = __DIR__ . '/' . $script;
    if (is_file($path) && $due()) {
        try {
            require $path;
        } catch (\Throwable $e) {
            Cron::log($script, 'ERROR — ' . $e->getMessage());
        }
    }
};

// Hold sweep: every tick (aligned to Stripe checkout-session expiry). (Task 2.3)
$run('expire_holds.php', static fn () => true);

// Reservation slot generation: hourly. (Task 3.1)
$run('generate_slots.php', static fn () => Cron::dueEvery('generate_slots', 60));

// Reservation reminders: every 15 min. (Task 3.2)
$run('send_reminders.php', static fn () => Cron::dueEvery('send_reminders', 15));

// Notification queue drain: every tick. (Task 1.4)
$run('drain_notifications.php', static fn () => true);

// Daily off-server DB backup, ~03:30 Phoenix. (Task 0.3 / C-V7-10)
$run('backup_db.php', static fn () => Cron::dueEvery('backup_db', 1440));

Cron::log('dispatcher', 'tick complete');
