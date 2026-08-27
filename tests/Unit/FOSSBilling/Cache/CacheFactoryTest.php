<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Cache\CacheFactory;
use FOSSBilling\Config;
use FOSSBilling\Doctrine\EntityManagerFactory;
use FOSSBilling\Exception;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->cacheFactoryOriginalConfig = Config::getConfig();
});

afterEach(function (): void {
    // $clearCache=false: none of these tests need the full cache-clearing side effects,
    // and skipping it keeps the suite from depending on any real Redis/Memcached backend.
    Config::setConfig($this->cacheFactoryOriginalConfig, false);
});

function setCacheConfig(?array $cacheConfig): void
{
    $config = Config::getConfig();
    if ($cacheConfig === null) {
        unset($config['cache']);
    } else {
        $config['cache'] = $cacheConfig;
    }
    Config::setConfig($config, false);
}

function setInstanceId(string $instanceId): void
{
    $config = Config::getConfig();
    $config['info']['instance_id'] = $instanceId;
    Config::setConfig($config, false);
}

test('defaults to a working filesystem cache when no cache configuration is set', function (): void {
    setCacheConfig(null);

    $pool = CacheFactory::create('cache_factory_test');

    expect($pool)->toBeInstanceOf(CacheItemPoolInterface::class);

    $item = $pool->getItem('probe');
    $item->set('value');
    $pool->save($item);

    expect($pool->getItem('probe')->get())->toBe('value');

    $pool->deleteItem('probe');
});

test('falls back to a working filesystem cache when config.php does not exist yet', function (): void {
    // The state SchemaInstaller/InstallSeeder's EntityManagerFactory::create() call hits during a
    // fresh install: config.php is only written at the very end of install(), so every earlier
    // step - including the Doctrine metadata cache this reaches for - has to run with no config
    // file on disk at all. Config::getProperty() throws a plain \RuntimeException in that case
    // (not this namespace's Exception), so this is the regression test for a narrower catch here
    // once again breaking every fresh install.
    $filesystem = new Filesystem();
    $backup = PATH_CONFIG . '.cache-factory-test-backup';
    $filesystem->rename(PATH_CONFIG, $backup);

    try {
        $pool = CacheFactory::create('cache_factory_test');

        expect($pool)->toBeInstanceOf(CacheItemPoolInterface::class);

        $item = $pool->getItem('probe');
        $item->set('value');
        $pool->save($item);

        expect($pool->getItem('probe')->get())->toBe('value');

        $pool->deleteItem('probe');
    } finally {
        $filesystem->rename($backup, PATH_CONFIG, true);
        clearstatcache(true, PATH_CONFIG);
    }
});

test('clearAll() alone cannot reach the Doctrine metadata cache pool, but explicitly clearing EntityManagerFactory::metadataCacheNamespace() does', function (): void {
    // EntityManagerFactory::create() stores the Doctrine metadata/query/result cache under a
    // namespace hashed from the current entity files, not clearAll()'s fixed NAMESPACE_DOCTRINE
    // constant - so clearAll() alone can never reach it, which matters most for a Redis/
    // Memcached-backed pool (a FilesystemAdapter's data is also caught by the plain PATH_CACHE
    // directory wipe every clearAll() caller already does alongside it).
    $namespace = EntityManagerFactory::metadataCacheNamespace();
    $pool = CacheFactory::create($namespace);
    $item = $pool->getItem('regression_probe');
    $item->set('value');
    $pool->save($item);

    CacheFactory::clearAll();
    expect(CacheFactory::create($namespace)->getItem('regression_probe')->isHit())->toBeTrue();

    CacheFactory::clearNamespace($namespace);
    expect(CacheFactory::create($namespace)->getItem('regression_probe')->isHit())->toBeFalse();
});

test('clearNamespace never throws even with a misconfigured driver', function (): void {
    if (hasRedisExtension()) {
        $this->markTestSkipped('This test requires an environment without the redis/relay extension.');
    }

    setCacheConfig(['driver' => 'redis', 'redis' => ['host' => '127.0.0.1', 'port' => 6379]]);

    CacheFactory::clearNamespace('cache_factory_test');
})->expectNotToPerformAssertions();

test('rejects an unsupported cache driver', function (): void {
    expect(fn () => CacheFactory::createFromConfig(['driver' => 'memory'], 'cache_factory_test', 0, true))
        ->toThrow(Exception::class, 'Unsupported cache driver');
});

test('falls back to the filesystem cache at runtime when the configured driver is unreachable', function (): void {
    if (hasRedisExtension()) {
        $this->markTestSkipped('This test requires an environment without the redis/relay extension.');
    }

    setCacheConfig(['driver' => 'redis', 'redis' => ['host' => '127.0.0.1', 'port' => 6379]]);

    $pool = CacheFactory::create('cache_factory_test');

    $item = $pool->getItem('probe');
    $item->set('value');
    $pool->save($item);

    expect($pool->getItem('probe')->get())->toBe('value');

    $pool->deleteItem('probe');
});

test('rejects saving a redis configuration when the redis extension is unavailable', function (): void {
    if (hasRedisExtension()) {
        $this->markTestSkipped('This test requires an environment without the redis/relay extension.');
    }

    expect(fn () => CacheFactory::createFromConfig(
        ['driver' => 'redis', 'redis' => ['host' => '127.0.0.1', 'port' => 6379]],
        'cache_factory_test',
        0,
        false,
    ))->toThrow(Exception::class, 'requires the PHP redis');
});

test('rejects saving a memcached configuration when the memcached extension is unavailable', function (): void {
    if (class_exists(Memcached::class)) {
        $this->markTestSkipped('This test requires an environment without the memcached extension.');
    }

    expect(fn () => CacheFactory::createFromConfig(
        ['driver' => 'memcached', 'memcached' => ['host' => '127.0.0.1', 'port' => 11211]],
        'cache_factory_test',
        0,
        false,
    ))->toThrow(Exception::class, 'requires the PHP memcached');
});

test('cache pools are isolated per installation instance id', function (): void {
    setInstanceId('install-a');
    $poolA = CacheFactory::create('cache_factory_test');
    $item = $poolA->getItem('shared-key');
    $item->set('value-a');
    $poolA->save($item);

    setInstanceId('install-b');
    $poolB = CacheFactory::create('cache_factory_test');
    expect($poolB->getItem('shared-key')->isHit())->toBeFalse();

    setInstanceId('install-a');
    $poolA2 = CacheFactory::create('cache_factory_test');
    expect($poolA2->getItem('shared-key')->get())->toBe('value-a');

    $poolA2->deleteItem('shared-key');
});

test('clearAll never throws even with a misconfigured driver', function (): void {
    if (hasRedisExtension()) {
        $this->markTestSkipped('This test requires an environment without the redis/relay extension.');
    }

    setCacheConfig(['driver' => 'redis', 'redis' => ['host' => '127.0.0.1', 'port' => 6379]]);

    CacheFactory::clearAll();
})->expectNotToPerformAssertions();

test('clearAll clears the pools for an explicitly given configuration, not just the live one', function (): void {
    // The live configuration stays on filesystem throughout; only the explicit config passed
    // to clearAll() below points at redis. This is the shape of a driver switch: by the time
    // the previous backend needs clearing, the live configuration already points at the new one.
    setCacheConfig(['driver' => 'filesystem']);

    if (hasRedisExtension()) {
        $this->markTestSkipped('This test requires an environment without the redis/relay extension.');
    }

    CacheFactory::clearAll(['driver' => 'redis', 'redis' => ['host' => '127.0.0.1', 'port' => 6379]]);
})->expectNotToPerformAssertions();

test('clearNamespace clears the pool for an explicitly given configuration, not just the live one', function (): void {
    setCacheConfig(['driver' => 'filesystem']);
    $pool = CacheFactory::create('cache_factory_test');
    $item = $pool->getItem('regression_probe');
    $item->set('value');
    $pool->save($item);

    // Passing the live filesystem config explicitly must reach the same pool as the live config
    // does implicitly.
    CacheFactory::clearNamespace('cache_factory_test', ['driver' => 'filesystem']);

    expect(CacheFactory::create('cache_factory_test')->getItem('regression_probe')->isHit())->toBeFalse();
});
