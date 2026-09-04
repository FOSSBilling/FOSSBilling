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

use FOSSBilling\Config;
use FOSSBilling\Exception;
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
        } catch (\Throwable) {
            // Catches more than this method's own Exception: getCacheConfig() reads the config
            // file via Config::getProperty(), which throws a plain \RuntimeException (not this
            // namespace's Exception) when config.php doesn't exist yet - the normal state during
            // a fresh install, before install() has written it. SchemaInstaller/InstallSeeder's
            // EntityManagerFactory::create() call reaches here for the Doctrine metadata cache at
            // exactly that point, so a narrower catch here would otherwise break every fresh
            // install, not just an unreachable/unsupported cache driver.
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
     * @throws Exception if the driver is unsupported, if a remote driver is selected without an
     *                   installation identifier configured, or (when $fallbackOnFailure is false)
     *                   the backend is unreachable
     */
    public static function createFromConfig(array $cacheConfig, string $namespace, int $defaultLifetime, bool $fallbackOnFailure): CacheItemPoolInterface
    {
        $instanceId = (string) Config::getProperty('info.instance_id', '');

        // Scope every pool to this installation, so installs that happen to share a Redis
        // database or Memcached server don't collide on the same cache keys.
        $namespace = self::scopeNamespaceToInstallation($namespace, $instanceId);

        $driver = $cacheConfig['driver'] ?? 'filesystem';

        if (!in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            throw new Exception('Unsupported cache driver :driver. Supported drivers are: :supported.', [':driver' => $driver, ':supported' => implode(', ', self::SUPPORTED_DRIVERS)], 5001);
        }

        if ($driver === 'filesystem') {
            return new FilesystemAdapter($namespace, $defaultLifetime, PATH_CACHE);
        }

        // Every install/upgrade path generates info.instance_id, but a manually edited config.php
        // could leave it blank - silently sharing the unscoped namespace with another install. This
        // sits outside the try/catch below on purpose: from create() (fallbackOnFailure: true) it's
        // caught by create()'s own outer catch and degrades to filesystem like any other
        // misconfiguration; from createFromConfig() directly (fallbackOnFailure: false), it reaches
        // the caller as this specific message instead of the generic "could not connect" one below.
        if ($instanceId === '') {
            throw new Exception('The ":driver" cache driver requires an installation identifier ("info.instance_id" in the configuration file) so that installations sharing the same server don\'t collide. Reinstall or update FOSSBilling to have one generated automatically, or set it manually.', [':driver' => $driver], 5001);
        }

        try {
            $pool = match ($driver) {
                'redis' => self::createRedisAdapter($cacheConfig['redis'] ?? [], $namespace, $defaultLifetime),
                'memcached' => self::createMemcachedAdapter($cacheConfig['memcached'] ?? [], $namespace, $defaultLifetime),
            };

            self::assertUsable($pool);

            return $pool;
        } catch (\Exception $e) {
            // Deliberately narrower than \Throwable: the redis/memcached extensions and Symfony's
            // cache adapters only ever raise \Exception (or a subclass) for an expected connection/
            // configuration failure - a bad host, missing extension, wrong credentials, and so on.
            // A \Error here (TypeError, ArgumentCountError, ...) means a genuine bug in our own
            // adapter-construction code, not an admin misconfiguration, so it's left to propagate
            // and be reported as usual instead of being swallowed under the ":driver" code below.
            if (!$fallbackOnFailure) {
                throw new Exception('Could not connect to the configured ":driver" cache backend: :message', [':driver' => $driver, ':message' => $e->getMessage()], 5001);
            }

            error_log(sprintf('FOSSBilling: failed to initialize the "%s" cache driver (%s); falling back to the filesystem cache.', $driver, $e->getMessage()));

            return new FilesystemAdapter($namespace, $defaultLifetime, PATH_CACHE);
        }
    }

    /**
     * Clears every cache pool FOSSBilling knows about. Best-effort: a misconfigured or
     * unreachable backend must not prevent the rest of the cache from being cleared.
     *
     * Pass an explicit $cacheConfig to clear a backend other than the currently configured
     * one - e.g. the previous backend right after the admin settings form switches drivers,
     * since by then the live configuration already points at the new one.
     */
    public static function clearAll(?array $cacheConfig = null): void
    {
        foreach (self::ALL_NAMESPACES as $namespace) {
            self::clearNamespace($namespace, $cacheConfig);
        }
    }

    /**
     * Clears one specific cache pool by namespace. Best-effort, same as {@see self::clearAll()}:
     * create() already falls back to the filesystem driver for a misconfigured/unreachable
     * backend, but clear() itself can still fail on its own (e.g. a Redis ACL that permits get/set
     * but not the flush command) - callers that already did the work clear() is meant to follow up
     * (writing a new config file, wiping the filesystem cache directory) must not have that
     * reported as their own failure.
     *
     * Pass an explicit $cacheConfig for the same reason as {@see self::clearAll()}.
     */
    public static function clearNamespace(string $namespace, ?array $cacheConfig = null): void
    {
        try {
            $pool = $cacheConfig !== null
                ? self::createFromConfig($cacheConfig, $namespace, 0, fallbackOnFailure: true)
                : self::create($namespace);
            $pool->clear();
        } catch (\Throwable) {
            // Clearing the cache is best-effort; a failure here shouldn't halt execution.
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
            throw new Exception('Cache configuration is invalid.');
        }

        $cacheConfig['driver'] ??= 'filesystem';

        return $cacheConfig;
    }

    /**
     * Appends this installation's stable instance ID to a namespace, so multiple FOSSBilling
     * installs pointed at the same Redis/Memcached server don't read or overwrite each other's
     * cache entries.
     */
    private static function scopeNamespaceToInstallation(string $namespace, string $instanceId): string
    {
        if ($instanceId === '') {
            return $namespace;
        }

        return $namespace . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $instanceId);
    }

    private static function createRedisAdapter(array $redisConfig, string $namespace, int $defaultLifetime): RedisAdapter
    {
        // Checked ahead of the extension-availability check below: whether a configuration is
        // internally safe to use doesn't depend on what happens to be installed on this server,
        // and doing it first lets this be validated in environments without the redis extension.
        self::assertRedisTransportIsSafe($redisConfig);

        if (!class_exists(\Redis::class) && !class_exists(\Relay\Relay::class) && !class_exists(\RedisCluster::class)) {
            throw new Exception('The "redis" cache driver requires the PHP redis (or relay) extension to be installed.');
        }

        $connection = RedisAdapter::createConnection(self::buildRedisDsn($redisConfig), self::buildRedisConnectionOptions($redisConfig));

        return new RedisAdapter($connection, $namespace, $defaultLifetime);
    }

    /**
     * Rejects a password-authenticated Redis connection to a non-loopback host when TLS isn't
     * enabled - without it, both the password and every cached value would cross the network in
     * plaintext. A loopback host (127.0.0.1/::1/localhost) is exempt, since that traffic never
     * leaves the machine regardless of TLS. This intentionally does NOT try to recognize "trusted
     * private network" hosts (a Docker service name, a VPC-internal IP): there's no reliable way
     * to tell those apart from a genuinely remote host from the hostname/IP alone, so an admin
     * relying on that kind of network for security should leave the Redis password unset rather
     * than expect this check to special-case their setup.
     *
     * @throws Exception if the connection would send a password over an unencrypted network hop
     */
    private static function assertRedisTransportIsSafe(array $redisConfig): void
    {
        if (empty($redisConfig['password'])) {
            return;
        }

        if (Tools::normalizeBoolean($redisConfig['tls']['enabled'] ?? false, false)) {
            return;
        }

        $host = (string) ($redisConfig['host'] ?? '127.0.0.1');
        if (self::isLoopbackHost($host)) {
            return;
        }

        throw new Exception('Refusing to send the Redis password to ":host" without TLS enabled. Enable "cache.redis.tls.enabled", or connect over a loopback address (127.0.0.1/::1/localhost) instead.', [':host' => $host]);
    }

    private static function isLoopbackHost(string $host): bool
    {
        $host = strtolower(trim($host));

        if ($host === 'localhost') {
            return true;
        }

        // Strip brackets from a literal IPv6 host, e.g. "[::1]".
        $host = trim($host, '[]');

        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip === false) {
            return false;
        }

        return $ip === '::1' || str_starts_with($ip, '127.');
    }

    private static function createMemcachedAdapter(array $memcachedConfig, string $namespace, int $defaultLifetime): MemcachedAdapter
    {
        if (!class_exists(\Memcached::class)) {
            throw new Exception('The "memcached" cache driver requires the PHP memcached extension to be installed.');
        }

        $connection = MemcachedAdapter::createConnection(self::buildMemcachedDsn($memcachedConfig));

        return new MemcachedAdapter($connection, $namespace, $defaultLifetime);
    }

    private static function buildRedisDsn(array $redisConfig): string
    {
        $host = $redisConfig['host'] ?? '127.0.0.1';
        $port = Tools::normalizePort($redisConfig['port'] ?? null, 6379);
        $database = (int) ($redisConfig['database'] ?? 0);
        $scheme = Tools::normalizeBoolean($redisConfig['tls']['enabled'] ?? false, false) ? 'rediss' : 'redis';

        $auth = '';
        if (!empty($redisConfig['password'])) {
            $auth = rawurlencode((string) $redisConfig['password']) . '@';
        }

        return sprintf('%s://%s%s:%d%s', $scheme, $auth, $host, $port, $database !== 0 ? '/' . $database : '');
    }

    /**
     * Builds the connection-options array passed as RedisAdapter::createConnection()'s second
     * argument. The 'ssl' key maps directly onto PHP's SSL stream-context options
     * (https://www.php.net/manual/en/context.ssl.php) - the same shape Symfony's Redis adapter
     * documents for TLS. Only populated when TLS is enabled; a plaintext connection has nothing
     * to configure here.
     */
    private static function buildRedisConnectionOptions(array $redisConfig): array
    {
        $tlsConfig = $redisConfig['tls'] ?? [];

        if (!Tools::normalizeBoolean($tlsConfig['enabled'] ?? false, false)) {
            return [];
        }

        $ssl = [
            'verify_peer' => Tools::normalizeBoolean($tlsConfig['verify_peer'] ?? true, true),
            'verify_peer_name' => Tools::normalizeBoolean($tlsConfig['verify_peer_name'] ?? true, true),
            'allow_self_signed' => Tools::normalizeBoolean($tlsConfig['allow_self_signed'] ?? false, false),
        ];

        if (!empty($tlsConfig['cafile'])) {
            $ssl['cafile'] = (string) $tlsConfig['cafile'];
        }

        return ['ssl' => $ssl];
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
