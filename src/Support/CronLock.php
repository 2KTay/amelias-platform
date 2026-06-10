<?php

declare(strict_types=1);

namespace Amelias\Support;

/**
 * Advisory file lock for cron jobs.
 *
 * Shared cPanel has no job queue, so concurrency safety for scheduled work
 * (hold sweep, slot generation, backups) relies on flock(LOCK_EX|LOCK_NB):
 * if a previous run is still going, the new run exits immediately instead of
 * double-processing (which would, e.g., double-release inventory holds).
 */
final class CronLock
{
    /** @var resource|null */
    private $handle = null;

    private function __construct(private readonly string $path)
    {
    }

    /**
     * Acquire a non-blocking lock named $name. Returns null if another process
     * already holds it (caller should exit quietly).
     */
    public static function acquire(string $name): ?self
    {
        $dir = ROOT_PATH . '/logs/locks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $name) . '.lock';
        $lock = new self($path);

        $handle = fopen($path, 'c');
        if ($handle === false) {
            return null;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null; // someone else is running this job
        }
        $lock->handle = $handle;
        return $lock;
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
