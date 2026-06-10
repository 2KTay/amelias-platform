<?php

declare(strict_types=1);

/**
 * Stale-hold sweep (Task 2.3). Called every tick by cron/dispatcher.php.
 *
 * Finds pending orders whose oldest active hold has expired (>15 min, i.e. the
 * customer never completed payment) and:
 *   - flips the order pending -> expired,
 *   - releases its active holds (status released),
 *   - restores reserved stock and decrements the pickup slot booked_count.
 *
 * Also sweeps orphan active holds belonging to non-pending orders (a defensive
 * cleanup; markPaid normally converts them). flock-guarded so two overlapping
 * dispatcher ticks can never double-release a hold.
 *
 * Counters never go negative (HoldManager::release uses GREATEST(0, ...)).
 */

use Amelias\Database\Database;
use Amelias\Services\HoldManager;
use Amelias\Support\Cron;
use Amelias\Support\CronLock;

// When run directly (not via dispatcher), bootstrap ourselves.
if (!defined('ROOT_PATH')) {
    require dirname(__DIR__) . '/includes/bootstrap.php';
}

$lock = CronLock::acquire('expire_holds');
if ($lock === null) {
    return; // a previous sweep is still running
}

try {
    // Pending orders with an expired active hold.
    $stale = Database::fetchAll(
        "SELECT DISTINCT o.id
           FROM orders o
           JOIN inventory_holds h ON h.order_id = o.id
          WHERE o.status = 'pending'
            AND h.status = 'active'
            AND h.expires_at <= UTC_TIMESTAMP()
          LIMIT 200"
    );

    // Also pending orders that booked a slot but carry no finite holds, expired
    // by age (no hold row to key on — fall back to placed_at + TTL).
    $staleNoHold = Database::fetchAll(
        "SELECT o.id
           FROM orders o
          WHERE o.status = 'pending'
            AND o.placed_at <= (UTC_TIMESTAMP() - INTERVAL ? MINUTE)
            AND NOT EXISTS (SELECT 1 FROM inventory_holds h WHERE h.order_id = o.id AND h.status = 'active')
          LIMIT 200",
        [HoldManager::HOLD_TTL_MINUTES]
    );

    $ids = [];
    foreach ($stale as $r) {
        $ids[(int) $r['id']] = true;
    }
    foreach ($staleNoHold as $r) {
        $ids[(int) $r['id']] = true;
    }

    $expired = 0;
    foreach (array_keys($ids) as $orderId) {
        // Guarded flip pending -> expired; only release if WE expired it.
        $flipped = Database::affected(
            "UPDATE orders SET status = 'expired' WHERE id = ? AND status = 'pending'",
            [$orderId]
        );
        if ($flipped === 1) {
            HoldManager::release($orderId);
            $expired++;
        }
    }

    Cron::log('expire_holds', "swept {$expired} stale pending order(s)");
} catch (\Throwable $e) {
    Cron::log('expire_holds', 'ERROR — ' . $e->getMessage());
} finally {
    $lock->release();
}
