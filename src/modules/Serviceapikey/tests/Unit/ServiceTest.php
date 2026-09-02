<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Order\Entity\Order;
use Box\Mod\Serviceapikey\Entity\ServiceApiKey;
use Box\Mod\Serviceapikey\Repository\ServiceApiKeyRepository;
use Box\Mod\Serviceapikey\Service;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('toApiArray serializes service api key entity', function (): void {
    $service = new Service();
    $service->setDi(container());

    $entity = createEntity(ServiceApiKey::class);
    $entity->id = 1;
    $entity->setApiKey('KEY-123');
    $entity->setConfig('{"length":32}');
    $entity->onPrePersist();

    $result = $service->toApiArray($entity);

    expect($result['id'])->toBe(1)
        ->and($result['api_key'])->toBe('KEY-123')
        ->and($result['config'])->toBe(['length' => 32])
        ->and($result)->toHaveKey('created_at')
        ->and($result)->toHaveKey('updated_at');
});

test('isValid throws exception when key is empty', function (): void {
    $service = new Service();
    $service->setDi(container());

    expect(fn (): bool => $service->isValid([]))
        ->toThrow(Exception::class);
});

test('isValid throws exception when api key not found', function (): void {
    $service = new Service();

    $repo = Mockery::mock(ServiceApiKeyRepository::class);
    $repo->shouldReceive('findByApiKey')->once()->with('missing')->andReturn(null);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(ServiceApiKey::class)->andReturn($repo);

    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    expect(fn (): bool => $service->isValid(['key' => 'missing']))
        ->toThrow(Exception::class, 'API key does not exist');
});

test('isActive returns true for active order with future expires_at', function (): void {
    $service = new Service();

    $entity = createEntity(ServiceApiKey::class);
    $entity->id = 7;

    $order = new Order();
    $order->setStatus(Order::STATUS_ACTIVE);
    $order->setExpiresAt(new DateTime(date('Y-m-d H:i:s', time() + 86400)));

    $orderRepo = Mockery::mock(EntityRepository::class);
    $orderRepo->shouldReceive('findOneBy')
        ->once()
        ->with(['serviceId' => 7, 'serviceType' => 'apikey'])
        ->andReturn($order);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepo);

    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    $reflection = new ReflectionMethod($service, 'isActive');
    expect($reflection->invoke($service, $entity))->toBeTrue();
});

test('isActive returns false for expired order', function (): void {
    $service = new Service();

    $entity = createEntity(ServiceApiKey::class);
    $entity->id = 7;

    $order = new Order();
    $order->setStatus(Order::STATUS_ACTIVE);
    $order->setExpiresAt(new DateTime(date('Y-m-d H:i:s', time() - 3600)));

    $orderRepo = Mockery::mock(EntityRepository::class);
    $orderRepo->shouldReceive('findOneBy')->once()->andReturn($order);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepo);

    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    $reflection = new ReflectionMethod($service, 'isActive');
    expect($reflection->invoke($service, $entity))->toBeFalse();
});

test('isActive returns false for inactive order status', function (): void {
    $service = new Service();

    $entity = createEntity(ServiceApiKey::class);
    $entity->id = 9;

    $order = new Order();
    $order->setStatus(Order::STATUS_SUSPENDED);

    $orderRepo = Mockery::mock(EntityRepository::class);
    $orderRepo->shouldReceive('findOneBy')->once()->andReturn($order);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepo);

    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    $reflection = new ReflectionMethod($service, 'isActive');
    expect($reflection->invoke($service, $entity))->toBeFalse();
});

test('isActive returns true for active order with null expires_at', function (): void {
    $service = new Service();

    $entity = createEntity(ServiceApiKey::class);
    $entity->id = 11;

    $order = new Order();
    $order->setStatus(Order::STATUS_ACTIVE);

    $orderRepo = Mockery::mock(EntityRepository::class);
    $orderRepo->shouldReceive('findOneBy')->once()->andReturn($order);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepo);

    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    $reflection = new ReflectionMethod($service, 'isActive');
    expect($reflection->invoke($service, $entity))->toBeTrue();
});

test('isActive throws when no matching order found', function (): void {
    $service = new Service();

    $entity = createEntity(ServiceApiKey::class);
    $entity->id = 15;

    $orderRepo = Mockery::mock(EntityRepository::class);
    $orderRepo->shouldReceive('findOneBy')->once()->andReturn(null);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepo);

    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    $reflection = new ReflectionMethod($service, 'isActive');
    expect(fn (): bool => $reflection->invoke($service, $entity))
        ->toThrow(Exception::class, 'API key does not exist');
});

test('clientSettableConfigKeys returns the apikey allowlist', function (): void {
    $service = new Service();
    $allowed = $service->clientSettableConfigKeys();

    expect($allowed)->toBeArray();
    expect($allowed)->toContain('period');
    expect($allowed)->toContain('quantity');
    // Admin-controlled keys must not appear - the central filter strips them
    // before the merge, preventing client override of key generation params
    // (length, split, split_interval, case, custom_* fields).
    expect($allowed)->not->toContain('length');
    expect($allowed)->not->toContain('split');
    expect($allowed)->not->toContain('split_interval');
    expect($allowed)->not->toContain('case');
});

test('install creates the service_apikey table portably instead of via raw MySQL DDL', function (): void {
    // Regression test: install() used to run raw MySQL-only DDL (backticks, ENGINE=InnoDB)
    // directly, which fails outright on PostgreSQL/SQLite - confirmed here against a real
    // SQLite connection. On MySQL, UpdatePatcher::patch111() already creates service_apikey
    // unconditionally, so this hook was already redundant there; it's only load-bearing on
    // PG/SQLite fresh installs, where nothing else creates the table.
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $em = FOSSBilling\Core\Doctrine\EntityManagerFactory::create($connection);

    expect($connection->createSchemaManager()->tablesExist(['service_apikey']))->toBeFalse();

    $di = new Pimple\Container();
    $di['em'] = $em;

    $service = new Service();
    $service->setDi($di);

    expect($service->install())->toBeTrue();
    expect($connection->createSchemaManager()->tablesExist(['service_apikey']))->toBeTrue();
});
