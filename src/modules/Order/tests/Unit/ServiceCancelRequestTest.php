<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Order\Entity\ClientOrderCancelRequest;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Repository\ClientOrderCancelRequestRepository;
use Box\Mod\Order\ServiceCancelRequest;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function cancelRequestService(?object $em = null): ServiceCancelRequest
{
    $service = new ServiceCancelRequest();
    $di = container();
    $em ??= Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('getRepository')->byDefault()->with(ClientOrderCancelRequest::class)->andReturn(
        Mockery::mock(ClientOrderCancelRequestRepository::class)->shouldIgnoreMissing()
    );
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('isTransactionActive')->andReturn(false);
    $connection->shouldReceive('fetchOne')->byDefault()->andReturn(Order::STATUS_ACTIVE);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(new Doctrine\DBAL\Platforms\SQLitePlatform());
    $em->shouldReceive('getConnection')->byDefault()->andReturn($connection);
    $em->shouldReceive('refresh')->byDefault();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $orders = Mockery::mock(Box\Mod\Order\Service::class);
    $orders->shouldReceive('withLifecycleLock')->andReturnUsing(fn ($order, Closure $operation): mixed => $operation());
    $orders->shouldReceive('assertOrderNotDeleting')->andReturnNull();
    $metadata = Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class);
    $metadata->shouldReceive('getPairsForOrder')->andReturn([]);
    $orders->shouldReceive('getOrderMetaRepository')->andReturn($metadata);
    $di['mod_service'] = $di->protect(fn (string $module): object => $module === 'Order' ? $orders : Mockery::mock()->shouldIgnoreMissing());
    $service->setDi($di);

    return $service;
}

function cancelRequestOrder(array $properties = []): Order
{
    return createEntity(Order::class, array_merge(['id' => 11, 'client_id' => 7, 'status' => Order::STATUS_ACTIVE, 'expires_at' => new DateTimeImmutable('+30 days')], $properties));
}

test('create stores a pending request', function (): void {
    $order = cancelRequestOrder();
    $client = createEntity(Client::class, ['id' => 7]);

    $requestRepo = Mockery::mock(ClientOrderCancelRequestRepository::class);
    $requestRepo->shouldReceive('findPendingByOrderId')->once()->with(11)->andReturn(null);

    $clientRepo = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepo->shouldReceive('find')->once()->with(7)->andReturn($client);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(ClientOrderCancelRequest::class)->andReturn($requestRepo);
    $em->shouldReceive('getRepository')->with(Client::class)->andReturn($clientRepo);
    $em->shouldReceive('persist')->once();
    $em->shouldReceive('flush')->once();

    $service = cancelRequestService($em);

    $request = $service->create($order, ClientOrderCancelRequest::TYPE_IMMEDIATE, 'Too expensive');

    expect($request->getStatus())->toBe(ClientOrderCancelRequest::STATUS_PENDING)
        ->and($request->getType())->toBe(ClientOrderCancelRequest::TYPE_IMMEDIATE);
});

test('create validates its input', function (): void {
    $service = cancelRequestService();

    expect(fn () => $service->create(cancelRequestOrder(), ClientOrderCancelRequest::TYPE_IMMEDIATE, '  '))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Please tell us why');
    expect(fn () => $service->create(cancelRequestOrder(), 'someday', 'reason'))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Invalid cancellation type');
    expect(fn () => $service->create(cancelRequestOrder(['status' => Order::STATUS_SUSPENDED]), ClientOrderCancelRequest::TYPE_IMMEDIATE, 'reason'))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Only an active service');
    expect(fn () => $service->create(cancelRequestOrder(['expires_at' => null]), ClientOrderCancelRequest::TYPE_END_OF_PERIOD, 'reason'))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'cancelled at the end of its period');
});

test('create refuses a second waiting request', function (): void {
    $order = cancelRequestOrder();

    $requestRepo = Mockery::mock(ClientOrderCancelRequestRepository::class);
    $requestRepo->shouldReceive('findPendingByOrderId')->once()->andReturn(createEntity(ClientOrderCancelRequest::class));

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(ClientOrderCancelRequest::class)->andReturn($requestRepo);

    expect(fn () => cancelRequestService($em)->create($order, ClientOrderCancelRequest::TYPE_IMMEDIATE, 'reason'))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'already received');
});

test('withdraw refuses another client’s request', function (): void {
    $order = cancelRequestOrder();
    $owner = createEntity(Client::class, ['id' => 7]);
    $other = createEntity(Client::class, ['id' => 9]);
    $request = createEntity(ClientOrderCancelRequest::class, ['status' => ClientOrderCancelRequest::STATUS_PENDING]);
    $request->setClient($owner)->setOrder($order);

    $requestRepo = Mockery::mock(ClientOrderCancelRequestRepository::class);
    $requestRepo->shouldReceive('find')->once()->with(3)->andReturn($request);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(ClientOrderCancelRequest::class)->andReturn($requestRepo);

    expect(fn () => cancelRequestService($em)->withdraw($order, $other, 3))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'no cancellation request waiting');
});

function cancelRequestAutoProcessService(object $em, array $config, ?string $lastDay, array &$params): ServiceCancelRequest
{
    $orderModule = Mockery::mock();
    $orderModule->shouldReceive('getConfig')->once()->andReturn($config);

    $systemService = Mockery::mock(Box\Mod\System\Service::class);
    $systemService->shouldReceive('getParamValue')->once()->with('cancel_requests_last_day')->andReturn($lastDay);
    if ($lastDay !== date('Y-m-d')) {
        $systemService->shouldReceive('setParamValue')->once()->with('cancel_requests_last_day', date('Y-m-d'), true)->andReturnUsing(function (string $key, string $value) use (&$params): bool {
            $params[$key] = $value;

            return true;
        });
    } else {
        $systemService->shouldNotReceive('setParamValue');
    }

    $service = cancelRequestService($em);
    $service->getDi()['mod'] = $service->getDi()->protect(fn (): object => $orderModule);
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn (): object => $systemService);

    return $service;
}

test('auto-process does nothing when disabled', function (): void {
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $params = [];

    $orderModule = Mockery::mock();
    $orderModule->shouldReceive('getConfig')->once()->andReturn([]);

    $service = cancelRequestService($em);
    $service->getDi()['mod'] = $service->getDi()->protect(fn (): object => $orderModule);

    expect($service->runAutoProcess())->toBeFalse();
    expect($params)->toBe([]);
});

test('auto-process skips when already ran today', function (): void {
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $params = [];
    $service = cancelRequestAutoProcessService($em, ['cancel_requests_auto' => 1], date('Y-m-d'), $params);

    expect($service->runAutoProcess())->toBeTrue();
    expect($params)->toBe([]);
});

test('accept leaves an unsupported gateway subscription for staff to cancel', function (): void {
    $order = cancelRequestOrder();
    $request = createEntity(ClientOrderCancelRequest::class, ['id' => 6, 'type' => ClientOrderCancelRequest::TYPE_END_OF_PERIOD, 'status' => ClientOrderCancelRequest::STATUS_PENDING]);
    $request->setOrder($order);
    $request->setClient(createEntity(Client::class, ['id' => 7]));
    $requestRepo = Mockery::mock(ClientOrderCancelRequestRepository::class);
    $requestRepo->shouldReceive('findOneBy')->once()->andReturn($request);
    $orders = Mockery::mock(Box\Mod\Order\Service::class);
    $orders->shouldReceive('findByClientIdAndOrderId')->once()->andReturn($order);
    $orders->shouldNotReceive('updateOrderMeta');
    $orders->shouldReceive('scheduleCancellationFromOrder')->once()->with($order, 'Cancellation request #6', true, 6)->andThrow(new FOSSBilling\Core\Exception\InformationException('Cancel the subscription at the payment provider.'));
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(ClientOrderCancelRequest::class)->andReturn($requestRepo);
    $em->shouldNotReceive('createQueryBuilder');
    $service = cancelRequestService($em);
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn (string $name): object => strtolower($name) === 'order' ? $orders : $subscriptions);

    expect(fn () => $service->accept(6))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'payment provider');
});
