<?php

declare(strict_types=1);

/**
 * Drain the notification retry queue. Invoked every tick by cron/dispatcher.php.
 * flock-guarded so overlapping ticks don't double-send.
 */

use Amelias\Services\Notifications\Notifier;
use Amelias\Support\Cron;
use Amelias\Support\CronLock;

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$lock = CronLock::acquire('drain_notifications');
if ($lock === null) {
    return;
}
$sent = (new Notifier())->drain(50);
if ($sent > 0) {
    Cron::log('drain_notifications', "sent {$sent} queued notification(s)");
}
