<?php

declare(strict_types=1);

namespace Amelias\Support;

/**
 * Tiny cron helper: structured logging + due-interval gating.
 *
 * A single host cron line calls cron/dispatcher.php every few minutes (no
 * per-job crontab entries, see docs/runbooks/deploy.md). The dispatcher uses
 * dueEvery() so each job runs on its own cadence, and log() appends one line
 * per job run to logs/cron.log.
 */
final class Cron
{
    public static function log(string $job, string $message): void
    {
        $dir = ROOT_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] %s: %s\n", gmdate('Y-m-d\TH:i:s\Z'), $job, $message);
        @file_put_contents($dir . '/cron.log', $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * True at most once per $minutes window, tracked by a marker file. Lets the
     * frequent dispatcher run a job (e.g. backup) only daily.
     */
    public static function dueEvery(string $job, int $minutes): bool
    {
        $dir = ROOT_PATH . '/logs/cron-state';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $marker = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $job) . '.last';
        $now = time();
        if (is_file($marker)) {
            $last = (int) file_get_contents($marker);
            if (($now - $last) < $minutes * 60) {
                return false;
            }
        }
        file_put_contents($marker, (string) $now);
        return true;
    }
}
