<?php

declare(strict_types=1);

/**
 * Scheduled daily off-server database backup.
 *
 * Dumps the database with mysqldump to a dated file in BACKUP_DIR (kept OFF the
 * web root), prunes dumps older than BACKUP_RETENTION_DAYS, and logs the run.
 * flock-guarded so an overrunning dump never overlaps the next tick. Invoked by
 * cron/dispatcher.php on a daily cadence (C-V7-10).
 *
 * This protects live data BETWEEN deploys; it is independent of the
 * backup-before-deploy step in the GitHub Actions workflow.
 */

use Amelias\Support\Cron;
use Amelias\Support\CronLock;

require dirname(__DIR__) . '/includes/bootstrap.php';

return (static function (): int {
    $lock = CronLock::acquire('backup_db');
    if ($lock === null) {
        Cron::log('backup_db', 'skipped — another run holds the lock');
        return 0;
    }

    $db = config('db');
    $backupDir = env('BACKUP_DIR') ?: (ROOT_PATH . '/storage/backups');
    $retentionDays = (int) env('BACKUP_RETENTION_DAYS', 30);

    if (!is_dir($backupDir) && !@mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
        Cron::log('backup_db', "FAILED — cannot create backup dir {$backupDir}");
        return 1;
    }

    $stamp = gmdate('Y-m-d_His');
    $file  = rtrim($backupDir, '/\\') . "/amelias_{$stamp}.sql.gz";

    // Build mysqldump command. Credentials via env file to keep them off the
    // process list / shell history.
    $credFile = tempnam(sys_get_temp_dir(), 'mycnf');
    file_put_contents($credFile, sprintf(
        "[client]\nhost=%s\nport=%d\nuser=%s\npassword=\"%s\"\n",
        $db['host'],
        (int) $db['port'],
        $db['user'],
        $db['pass'],
    ));
    @chmod($credFile, 0600);

    $cmd = sprintf(
        'mysqldump --defaults-extra-file=%s --single-transaction --quick --routines %s | gzip > %s',
        escapeshellarg($credFile),
        escapeshellarg($db['name']),
        escapeshellarg($file),
    );

    exec($cmd, $output, $exitCode);
    @unlink($credFile);

    if ($exitCode !== 0 || !is_file($file) || filesize($file) === 0) {
        Cron::log('backup_db', "FAILED — mysqldump exit {$exitCode}");
        return 1;
    }

    // Prune old dumps.
    $cutoff = time() - $retentionDays * 86400;
    $pruned = 0;
    foreach (glob(rtrim($backupDir, '/\\') . '/amelias_*.sql.gz') ?: [] as $old) {
        if (filemtime($old) < $cutoff) {
            @unlink($old);
            $pruned++;
        }
    }

    Cron::log('backup_db', sprintf('ok — %s (%d bytes), pruned %d old', basename($file), filesize($file), $pruned));
    return 0;
})();
