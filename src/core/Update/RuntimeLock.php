<?php

declare(strict_types=1);

namespace FOSSBilling\Core\Update;

/** Bootstrap-safe lock: readers enter before loading application classes. */
final class RuntimeLock
{
    public const string FILENAME = 'data/uploads/.update-runtime.lock';

    /** @var array<string, resource> */
    private static array $readers = [];

    public static function acquire(string $root): void
    {
        if (isset(self::$readers[$root])) {
            return;
        }
        $handle = @fopen($root . '/' . self::FILENAME, 'c');
        if ($handle === false || !flock($handle, LOCK_SH)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw new \RuntimeException('Unable to acquire the application update lock.');
        }
        self::$readers[$root] = $handle;
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
