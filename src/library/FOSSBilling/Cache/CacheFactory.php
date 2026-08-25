<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Cache;

use FOSSBilling\Exception\BaseException;
use FOSSBilling\System\Config;
use FOSSBilling\Tools;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Contracts\Cache\CacheInterface;

class CacheFactory
{
    /**
     * List of supported cache drivers.
     *
     * @var string[]
     */
    public const SUPPORTED_DRIVERS = [
        'filesystem',
        'redis',
        'memcached',
    ];

    /**
     * Namespace used for the general-purpose application cache (`$di['cache']`).
     */
    public const NAMESPACE_APP = 'sf_cache';

    /**
     * Namespace used for the rate limiter's cache (`$di['rate_limit_cache']`).
     */
    public const NAMESPACE_RATE_LIMIT = 'rate_limit';

    /**
     * Namespace used for the Doctrine ORM metadata/query/result cache.
     */
    public const NAMESPACE_DOCTRINE = 'doctrine';

    /**
     * Namespace used only to validate a not-yet-saved cache configuration.
     */
    public const NAMESPACE_CONNECTION_TEST = 'connection_test';

    /**
     * All namespaces FOSSBilling uses, kept in one place so cache-wide invalidation
     * (see {@see clearAll()}) doesn't drift out of sync with the pools above.
     *
     * @var string[]
     */
    private const array ALL_NAMESPACES = [
        self::NAMESPACE_APP,
        self::NAMESPACE_RATE_LIMIT,
        self::NAMESPACE_DOCTRINE,
    ];

    /**
     * Builds a cache pool for the given namespace, based on the live configuration.
     *
     * This never throws: if the configured driver is unreachable or its PHP extension
     * isn't installed, it logs a warning and falls back to the filesystem driver so that
     * a cache problem never takes down the rest of the application.
     */
    public static function create(string $namespace, int $defaultLifetime = 0): CacheItemPoolInterface
    {
        try {
            return self::createFromConfig(self::getCacheConfig(), $namespace, $defaultLifetime, fallbackOnFailure: true);
        } catch (BaseException) {
            // Unsupported driver values are also treated as a soft failure at runtime; a hard failure
            // here would otherwise break every feature that reads from $di['cache'].
            return new FilesystemAdapter($namespace, $defaultLifetime, PATH_CACHE);
        }
    }

    /**
     * Builds a cache pool from an explicit (potentially not-yet-saved) configuration array.
     *
     * Used by the admin settings form to validate a driver before it's written to disk. When
     * $fallbackOnFailure is false, connection/extension problems are thrown as a FOSSBilling
     * Exception with a specific reason instead of silently degrading to filesystem.
     *
     * @throws BaseException if the driver is unsupported, or (when $fallbackOnFailure is false) unreachable
     */
    public static function createFromConfig(array $cacheConfig, string $namespace, int $defaultLifetime, bool $fallbackOnFailure): CacheItemPoolInterface
    {
        // Scope every pool to this installation, so installs that happen to share a Redis
        // database or Memcached server don't collide on the same cache keys.
        $namespace = self::scopeNamespaceToInstallation($namespace);

        $driver = $cacheConfig['driver'] ?? 'filesystem';

        if (!in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            throw new BaseException('Unsupported cache driver :driver. Supported drivers are: :supported.', [':driver' => $driver, ':supported' => implode(', ', self::SUPPORTED_DRIVERS)]);
        }

        if ($driver === 'filesystem') {
            return new FilesystemAdapter($namespace, $defaultLifetime, PATH_CACHE);
        }

        try {
            $pool = match ($driver) {
                'redis' => self::createRedisAdapter($cacheConfig['redis'] ?? [], $namespace, $defaultLifetime),
                'memcached' => self::createMemcachedAdapter($cacheConfig['memcached'] ?? [], $namespace, $defaultLifetime),
            };

            self::assertUsable($pool);

            return $pool;
        } catch (\Throwable $e) {
            if (!$fallbackOnFailure) {
                throw new BaseException('Could not connect to the configured ":driver" cache backend: :message', [':driver' => $driver, ':message' => $e->getMessage()]);
            }

            error_log(sprintf('FOSSBilling: failed to initialize the "%s" cache driver (%s); falling back to the filesystem cache.', $driver, $e->getMessage()));

            return new FilesystemAdapter($namespace, $defaultLifetime, PATH_CACHE);
        }
    }

    /**
     * Clears every cache pool FOSSBilling knows about. Best-effort: a misconfigured or
     * unreachable backend must not prevent the rest of the cache from being cleared.
     */
    public static function clearAll(): void
    {
        foreach (self::ALL_NAMESPACES as $namespace) {
            try {
                self::create($namespace)->clear();
            } catch (\Throwable) {
                // Clearing the cache is best-effort; a failure here shouldn't halt execution.
            }
        }
    }

    /**
     * Returns the `cache` configuration block, defaulting to the filesystem driver
     * when unset so existing installs without a `cache` key keep their current behavior.
     */
    private static function getCacheConfig(): array
    {
        $cacheConfig = Config::getProperty('cache', []);

        if (!is_array($cacheConfig)) {
            throw new BaseException('Cache configuration is invalid.');
        }

        $cacheConfig['driver'] ??= 'filesystem';

        return $cacheConfig;
    }

    /**
     * Appends this installation's stable instance ID to a namespace, so multiple FOSSBilling
     * installs pointed at the same Redis/Memcached server don't read or overwrite each other's
     * cache entries.
     */
    private static function scopeNamespaceToInstallation(string $namespace): string
    {
        $instanceId = (string) Config::getProperty('info.instance_id', '');

        if ($instanceId === '') {
            return $namespace;
        }

        return $namespace . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $instanceId);
    }

    private static function createRedisAdapter(array $redisConfig, string $namespace, int $defaultLifetime): RedisAdapter
    {
        if (!class_exists(\Redis::class) && !class_exists(\Relay\Relay::class) && !class_exists(\RedisCluster::class)) {
            throw new BaseException('The "redis" cache driver requires the PHP redis (or relay) extension to be installed.');
        }

        $connection = RedisAdapter::createConnection(self::buildRedisDsn($redisConfig));

        return new RedisAdapter($connection, $namespace, $defaultLifetime);
    }

    private static function createMemcachedAdapter(array $memcachedConfig, string $namespace, int $defaultLifetime): MemcachedAdapter
    {
        if (!class_exists(\Memcached::class)) {
            throw new BaseException('The "memcached" cache driver requires the PHP memcached extension to be installed.');
        }

        $connection = MemcachedAdapter::createConnection(self::buildMemcachedDsn($memcachedConfig));

        return new MemcachedAdapter($connection, $namespace, $defaultLifetime);
    }

    private static function buildRedisDsn(array $redisConfig): string
    {
        $host = $redisConfig['host'] ?? '127.0.0.1';
        $port = Tools::normalizePort($redisConfig['port'] ?? null, 6379);
        $database = (int) ($redisConfig['database'] ?? 0);

        $auth = '';
        if (!empty($redisConfig['password'])) {
            $auth = rawurlencode((string) $redisConfig['password']) . '@';
        }

        return sprintf('redis://%s%s:%d%s', $auth, $host, $port, $database !== 0 ? '/' . $database : '');
    }

    private static function buildMemcachedDsn(array $memcachedConfig): string
    {
        $host = $memcachedConfig['host'] ?? '127.0.0.1';
        $port = Tools::normalizePort($memcachedConfig['port'] ?? null, 11211);

        return sprintf('memcached://%s:%d', $host, $port);
    }

    /**
     * Forces an eager connection attempt. Both RedisAdapter and MemcachedAdapter lazily
     * connect on first use, so without this, a bad host/port/password would only surface
     * the first time some unrelated feature happened to read from the cache.
     *
     * @throws \Throwable if the backend cannot be reached
     */
    private static function assertUsable(CacheItemPoolInterface&CacheInterface $pool): void
    {
        $testKey = '__fossbilling_cache_connection_test__';
        $pool->get($testKey, static fn (): true => true);
        $pool->delete($testKey);
    }
}
