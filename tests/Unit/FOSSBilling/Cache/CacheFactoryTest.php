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
use FOSSBilling\Exception;
use Psr\Cache\CacheItemPoolInterface;

beforeEach(function (): void {
    $this->cacheFactoryOriginalConfig = Config::getConfig();
});

afterEach(function (): void {
    // $clearCache=false: none of these tests need the full cache-clearing side effects,
    // and skipping it keeps the suite from depending on any real Redis/Memcached backend.
    Config::setConfig($this->cacheFactoryOriginalConfig, false);
});

function hasRedisExtension(): bool
{
    return class_exists(Redis::class) || class_exists(Relay\Relay::class) || class_exists(RedisCluster::class);
}

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

test('clearAll never throws even with a misconfigured driver', function (): void {
    if (hasRedisExtension()) {
        $this->markTestSkipped('This test requires an environment without the redis/relay extension.');
    }

    setCacheConfig(['driver' => 'redis', 'redis' => ['host' => '127.0.0.1', 'port' => 6379]]);

    CacheFactory::clearAll();
})->expectNotToPerformAssertions();
