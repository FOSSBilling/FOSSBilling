<?php

declare(strict_types=1);

namespace FOSSBilling\Core\Update;

/** Bootstrap-safe lock: readers enter before loading application classes. */
final class RuntimeLock
{
    public const string FILENAME = 'data/uploads/.update-runtime.lock';
    public const string EXTRACTION_MARKER_FILENAME = '.update-extraction-pending';

    /** @var array<string, resource> */
    private static array $readers = [];

    /**
     * Claim a fresh or abandoned extraction marker without unlinking another
     * contender's marker between checking its age and acquiring ownership.
     *
     * @return resource|false
     */
    public static function claimMarker(string $path, int $staleSeconds): mixed
    {
        $handle = @fopen($path, 'x');
        $created = $handle !== false;
        if (!$created) {
            $handle = @fopen($path, 'r+');
        }
        if ($handle === false) {
            return false;
        }
        // A bootstrap probe can briefly hold SH on the newly created file.
        // Wait for that probe instead of abandoning our own fresh marker.
        if (!flock($handle, $created ? LOCK_EX : LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }
        clearstatcache(true, $path);
        $opened = fstat($handle);
        $current = @stat($path);
        if ($opened === false || $current === false
            || $opened['dev'] !== $current['dev'] || $opened['ino'] !== $current['ino']
            || (!$created && time() - $opened['mtime'] <= $staleSeconds)) {
            fclose($handle);

            return false;
        }

        // Reuse a stale inode under its exclusive lock. Unlink-and-recreate
        // would let two contenders remove each other's newly created marker.
        return $handle;
    }

    /** @param resource|null $bootstrapLock Already-held bootstrap reader, transferred to this class. */
    public static function acquire(string $root, mixed $bootstrapLock = null): void
    {
        if (isset(self::$readers[$root])) {
            return;
        }
        $handle = $bootstrapLock ?? @fopen($root . '/' . self::FILENAME, 'c');
        if ($handle === false || !flock($handle, LOCK_SH)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw new \RuntimeException('Unable to acquire the application update lock.');
        }
        self::$readers[$root] = $handle;
    }

    /** Older coordination releases retain this reader until process shutdown. */
    public static function acquireFinalizationReader(string $dataPath): void
    {
        $key = 'finalization:' . $dataPath;
        if (isset(self::$readers[$key])) {
            return;
        }
        $handle = @fopen($dataPath . '/update-finalization.lock', 'c');
        if ($handle === false || !flock($handle, LOCK_SH)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw new \RuntimeException('Unable to acquire the update finalization reader.');
        }
        self::$readers[$key] = $handle;
    }

    public static function release(string $root): void
    {
        if (isset(self::$readers[$root])) {
            flock(self::$readers[$root], LOCK_UN);
            fclose(self::$readers[$root]);
            unset(self::$readers[$root]);
        }
    }

    public static function exclusively(string $root, \Closure $callback, float $timeout = 30): mixed
    {
        $held = isset(self::$readers[$root]);
        $handle = $held ? self::$readers[$root] : @fopen($root . '/' . self::FILENAME, 'c');
        if ($handle === false) {
            throw new \RuntimeException('Unable to acquire the application update lock.');
        }
        // Release our reader before waiting: two updaters must not keep each
        // other alive with shared locks while each waits for exclusivity.
        flock($handle, LOCK_UN);
        $deadline = microtime(true) + $timeout;

        try {
            while (!flock($handle, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    throw new \RuntimeException('Active requests or cron jobs are still finishing. No update files were extracted. Please retry the update shortly.');
                }
                usleep(10000);
            }

            return $callback();
        } finally {
            if ($held) {
                flock($handle, LOCK_SH);
            } else {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    }
}
