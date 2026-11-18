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
    $em->shouldReceive('getConnection')->byDefault()->andReturn(Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
    $em->shouldReceive('refresh')->byDefault();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
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

test('withdraw takes back the client’s own waiting request', function (): void {
    $order = cancelRequestOrder();
    $client = createEntity(Client::class, ['id' => 7]);
    $request = createEntity(ClientOrderCancelRequest::class, ['id' => 3, 'status' => ClientOrderCancelRequest::STATUS_PENDING]);
    $request->setClient($client);

    $requestRepo = Mockery::mock(ClientOrderCancelRequestRepository::class);
    $request->setOrder($order);
    $requestRepo->shouldReceive('findPendingByOrderId')->once()->with(11)->andReturn($request);
    $requestRepo->shouldReceive('findOneBy')->once()->with(['id' => 3, 'status' => ClientOrderCancelRequest::STATUS_PENDING])->andReturn($request);

    $query = Mockery::mock(Doctrine\ORM\Query::class);
    $query->shouldReceive('execute')->once()->andReturn(1);
    $qb = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $qb->shouldReceive('update')->once()->andReturn($qb);
    $qb->shouldReceive('set')->twice()->andReturn($qb);
    $qb->shouldReceive('where')->once()->andReturn($qb);
    $qb->shouldReceive('andWhere')->once()->andReturn($qb);
    $qb->shouldReceive('setParameter')->times(4)->andReturn($qb);
    $qb->shouldReceive('getQuery')->once()->andReturn($query);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(ClientOrderCancelRequest::class)->andReturn($requestRepo);
    $em->shouldReceive('createQueryBuilder')->once()->andReturn($qb);

    $service = cancelRequestService($em);
    $service->withdraw($order, $client);

    expect(true)->toBeTrue();
});

test('withdraw refuses another client’s request', function (): void {
    $order = cancelRequestOrder();
    $owner = createEntity(Client::class, ['id' => 7]);
    $other = createEntity(Client::class, ['id' => 9]);
    $request = createEntity(ClientOrderCancelRequest::class, ['status' => ClientOrderCancelRequest::STATUS_PENDING]);
    $request->setClient($owner);

    $requestRepo = Mockery::mock(ClientOrderCancelRequestRepository::class);
    $requestRepo->shouldReceive('findPendingByOrderId')->once()->andReturn($request);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(ClientOrderCancelRequest::class)->andReturn($requestRepo);

    expect(fn () => cancelRequestService($em)->withdraw($order, $other))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'no cancellation request waiting');
});

test('accept cancels immediately for an immediate request', function (): void {
    $order = cancelRequestOrder();
    $request = createEntity(ClientOrderCancelRequest::class, ['id' => 5, 'type' => ClientOrderCancelRequest::TYPE_IMMEDIATE, 'status' => ClientOrderCancelRequest::STATUS_PENDING]);
    $request->setOrder($order);
    $request->setClient(createEntity(Client::class, ['id' => 7]));

    $requestRepo = Mockery::mock(ClientOrderCancelRequestRepository::class);
    $requestRepo->shouldReceive('findOneBy')->once()->andReturn($request);

    $orderService = Mockery::mock(Box\Mod\Order\Service::class);
    $orderService->shouldReceive('findByClientIdAndOrderId')->once()->with(7, 11)->andReturn($order);
    $orderService->shouldReceive('cancelFromOrder')->once()->andReturn(true);

    $query = Mockery::mock(Doctrine\ORM\Query::class);
    $query->shouldReceive('execute')->once()->andReturn(1);
    $qb = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $qb->shouldReceive('update')->once()->andReturn($qb);
    $qb->shouldReceive('set')->twice()->andReturn($qb);
    $qb->shouldReceive('where')->once()->andReturn($qb);
    $qb->shouldReceive('andWhere')->once()->andReturn($qb);
    $qb->shouldReceive('setParameter')->times(4)->andReturn($qb);
    $qb->shouldReceive('getQuery')->once()->andReturn($query);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(ClientOrderCancelRequest::class)->andReturn($requestRepo);
    $em->shouldReceive('createQueryBuilder')->once()->andReturn($qb);

    $service = cancelRequestService($em);
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn (): object => $orderService);

    $service->accept(5);

    expect(true)->toBeTrue();
});

test('accept schedules end-of-period cancellation without a subscription', function (): void {
    $order = cancelRequestOrder();
    $request = createEntity(ClientOrderCancelRequest::class, ['id' => 6, 'type' => ClientOrderCancelRequest::TYPE_END_OF_PERIOD, 'status' => ClientOrderCancelRequest::STATUS_PENDING]);
    $request->setOrder($order);
    $request->setClient(createEntity(Client::class, ['id' => 7]));

    $requestRepo = Mockery::mock(ClientOrderCancelRequestRepository::class);
    $requestRepo->shouldReceive('findOneBy')->once()->andReturn($request);

    $orderService = Mockery::mock(Box\Mod\Order\Service::class);
    $orderService->shouldReceive('findByClientIdAndOrderId')->once()->andReturn($order);
    $orderService->shouldReceive('updateOrderMeta')->once()->with($order, [Box\Mod\Order\Service::META_CANCEL_AT_PERIOD_END => '1'])->andReturn(1);
    $orderService->shouldNotReceive('cancelFromOrder');

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('hasActiveSubscriptionForOrder')->once()->with($order)->andReturn(false);

    $query = Mockery::mock(Doctrine\ORM\Query::class);
    $query->shouldReceive('execute')->once()->andReturn(1);
    $qb = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $qb->shouldReceive('update')->once()->andReturn($qb);
    $qb->shouldReceive('set')->twice()->andReturn($qb);
    $qb->shouldReceive('where')->once()->andReturn($qb);
    $qb->shouldReceive('andWhere')->once()->andReturn($qb);
    $qb->shouldReceive('setParameter')->times(4)->andReturn($qb);
    $qb->shouldReceive('getQuery')->once()->andReturn($query);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(ClientOrderCancelRequest::class)->andReturn($requestRepo);
    $em->shouldReceive('createQueryBuilder')->once()->andReturn($qb);

    $service = cancelRequestService($em);
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn (string $name): object => match (strtolower($name)) {
        'order' => $orderService,
        default => $subscriptionService,
    });

    $service->accept(6);

    expect(true)->toBeTrue();
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

test('auto-process accepts waiting requests once a day', function (): void {
    $makeRequest = static function (int $id): ClientOrderCancelRequest {
        $request = createEntity(ClientOrderCancelRequest::class, ['id' => $id, 'status' => ClientOrderCancelRequest::STATUS_PENDING]);
        $request->setOrder(createEntity(Order::class, ['id' => 10 + $id]));
        $request->setClient(createEntity(Client::class, ['id' => 7]));

        return $request;
    };

    $requestRepo = Mockery::mock(ClientOrderCancelRequestRepository::class);
    $requestRepo->shouldReceive('getPendingList')->once()->andReturn([['id' => 3], ['id' => 4]]);
    $requestRepo->shouldReceive('findOneBy')->twice()->andReturnUsing(fn (array $criteria): ?ClientOrderCancelRequest => $makeRequest((int) $criteria['id']));

    $orderService = Mockery::mock(Box\Mod\Order\Service::class);
    $orderService->shouldReceive('findByClientIdAndOrderId')->twice()->andThrow(new FOSSBilling\Core\Exception\InformationException('gone'));

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(ClientOrderCancelRequest::class)->andReturn($requestRepo);

    $params = [];
    $orderModule = Mockery::mock();
    $orderModule->shouldReceive('getConfig')->once()->andReturn(['cancel_requests_auto' => 1]);
    $systemService = Mockery::mock(Box\Mod\System\Service::class);
    $systemService->shouldReceive('getParamValue')->once()->with('cancel_requests_last_day')->andReturn('2000-01-01');
    $systemService->shouldReceive('setParamValue')->once()->with('cancel_requests_last_day', date('Y-m-d'), true)->andReturnUsing(function (string $key, string $value) use (&$params): bool {
        $params[$key] = $value;

        return true;
    });
    $service = cancelRequestService($em);
    $service->getDi()['mod'] = $service->getDi()->protect(fn (): object => $orderModule);
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn (string $name): object => match (strtolower($name)) {
        'order' => $orderService,
        'system' => $systemService,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    expect($service->runAutoProcess())->toBeTrue();
    expect($params)->toBe(['cancel_requests_last_day' => date('Y-m-d')]);
});

afterEach(function (): void {
    if (isset($this->cancellationRaceCleanup)) {
        ($this->cancellationRaceCleanup)();
    }
});

function cancellationRaceContext(string $type): array
{
    $path = (new Symfony\Component\Filesystem\Filesystem())->tempnam(sys_get_temp_dir(), 'cancel-race-');
    $db = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
    $em = FOSSBilling\Core\Doctrine\EntityManagerFactory::create($db);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Client::class, Order::class, ClientOrderCancelRequest::class]));
    $client = (new Client())->setEmail('cancel@example.com');
    $em->persist($client);
    $em->flush();
    $order = (new Order())->setClientId($client->getId())->setStatus(Order::STATUS_ACTIVE)->setExpiresAt(new DateTimeImmutable('+1 month'));
    $em->persist($order);
    $em->flush();
    $request = (new ClientOrderCancelRequest())->setOrder($order)->setClient($client)->setType($type)->setStatus(ClientOrderCancelRequest::STATUS_PENDING);
    $em->persist($request);
    $em->flush();
    $otherDb = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
    $otherEm = new Doctrine\ORM\EntityManager($otherDb, $em->getConfiguration());
    test()->cancellationRaceCleanup = static function () use ($db, $otherDb, $path): void {
        $otherDb->close();
        $db->close();
        (new Symfony\Component\Filesystem\Filesystem())->remove($path);
    };
    $otherEm->find(ClientOrderCancelRequest::class, $request->getId());
    $orders = Mockery::mock(Box\Mod\Order\Service::class);
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptions->shouldReceive('hasActiveSubscriptionForOrder')->byDefault()->andReturn(false);
    $services = [];
    foreach ([$em, $otherEm] as $manager) {
        $di = container();
        $di['em'] = $manager;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['order' => $orders, 'invoice:subscription' => $subscriptions]));
        $service = new ServiceCancelRequest();
        $service->setDi($di);
        $services[] = $service;
    }

    return compact('db', 'em', 'otherEm', 'order', 'client', 'request', 'orders', 'services');
}

test('acceptance excludes withdrawal and stopping until its destructive action is complete', function (string $operation, string $type): void {
    $c = cancellationRaceContext($type);
    $blocked = false;
    $c['orders']->shouldReceive('findByClientIdAndOrderId')->once()->andReturnUsing(function () use ($c, $operation, &$blocked): Order {
        try {
            if ($operation === 'withdraw') {
                $c['services'][1]->withdraw($c['order'], $c['client']);
            } else {
                $c['services'][1]->stop($c['request']->getId());
            }
        } catch (FOSSBilling\Core\Exception\InformationException) {
            $blocked = true;
        }

        return $c['order'];
    });
    $c['orders']->shouldReceive($type === ClientOrderCancelRequest::TYPE_IMMEDIATE ? 'cancelFromOrder' : 'updateOrderMeta')->once()->andReturn($type === ClientOrderCancelRequest::TYPE_IMMEDIATE ? true : 1);
    $c['services'][0]->accept($c['request']->getId());
    expect($blocked)->toBeTrue()
        ->and($c['db']->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe(ClientOrderCancelRequest::STATUS_ACCEPTED);
    $c['db']->close();
})->with(['withdraw', 'stop'])->with([ClientOrderCancelRequest::TYPE_IMMEDIATE, ClientOrderCancelRequest::TYPE_END_OF_PERIOD]);

test('withdrawal or stopping committed before acceptance prevents any order action', function (string $operation): void {
    $c = cancellationRaceContext(ClientOrderCancelRequest::TYPE_IMMEDIATE);
    $c['orders']->shouldNotReceive('findByClientIdAndOrderId');
    $c['orders']->shouldNotReceive('cancelFromOrder');
    if ($operation === 'withdraw') {
        $c['services'][1]->withdraw($c['order'], $c['client']);
    } else {
        $c['services'][1]->stop($c['request']->getId());
    }
    expect(fn () => $c['services'][0]->accept($c['request']->getId()))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'no longer waiting');
    expect($c['db']->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe($operation === 'withdraw' ? ClientOrderCancelRequest::STATUS_WITHDRAWN : ClientOrderCancelRequest::STATUS_STOPPED);
    $c['db']->close();
})->with(['withdraw', 'stop']);

test('failed acceptance releases its request lock and leaves the request pending', function (): void {
    $c = cancellationRaceContext(ClientOrderCancelRequest::TYPE_IMMEDIATE);
    $c['orders']->shouldReceive('findByClientIdAndOrderId')->once()->andReturn($c['order']);
    $c['orders']->shouldReceive('cancelFromOrder')->once()->andThrow(new RuntimeException('Provider failure'));
    expect(fn () => $c['services'][0]->accept($c['request']->getId()))->toThrow(RuntimeException::class, 'Provider failure');
    expect($c['db']->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe(ClientOrderCancelRequest::STATUS_PENDING);
    $c['services'][1]->stop($c['request']->getId());
    expect($c['db']->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe(ClientOrderCancelRequest::STATUS_STOPPED);
    $c['db']->close();
});

test('cancellation decisions reject an outer transaction before acting on the order', function (string $operation): void {
    $c = cancellationRaceContext(ClientOrderCancelRequest::TYPE_IMMEDIATE);
    $c['orders']->shouldNotReceive('findByClientIdAndOrderId');
    $c['orders']->shouldNotReceive('cancelFromOrder');
    $c['db']->beginTransaction();

    try {
        expect(function () use ($c, $operation): void {
            if ($operation === 'withdraw') {
                $c['services'][0]->withdraw($c['order'], $c['client']);
            } else {
                $c['services'][0]->$operation($c['request']->getId());
            }
        })->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'inside a database transaction');
    } finally {
        $c['db']->rollBack();
    }
    expect($c['db']->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe(ClientOrderCancelRequest::STATUS_PENDING);
})->with(['accept', 'stop', 'withdraw']);
