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

function cancelRequestService(?object $em = null, ?object $email = null): ServiceCancelRequest
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
    $orders->shouldReceive('withCancellationScheduleLock')->byDefault()->andReturnUsing(fn ($order, Closure $operation, int $timeout): mixed => $operation());
    $orders->shouldReceive('withLifecycleLock')->andReturnUsing(fn ($order, Closure $operation): mixed => $operation());
    $orders->shouldReceive('assertOrderNotDeleting')->andReturnNull();
    $metadata = Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class);
    $metadata->shouldReceive('getPairsForOrder')->andReturn([]);
    $orders->shouldReceive('getOrderMetaRepository')->andReturn($metadata);
    $di['mod_service'] = $di->protect(fn (string $module): object => $module === 'Order' ? $orders : ($email ?? Mockery::mock()->shouldIgnoreMissing()));
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

    $email = Mockery::mock();
    $email->shouldReceive('sendTemplate')->once()->with(Mockery::on(fn (array $data): bool => $data['code'] === 'mod_order_cancel_request' && $data['to_client'] === 7 && $data['order']['id'] === 11));
    $email->shouldReceive('sendTemplate')->once()->with(Mockery::on(fn (array $data): bool => $data['code'] === 'mod_staff_order_cancel_request' && $data['to_staff'] === true && $data['order']['id'] === 11));
    $service = cancelRequestService($em, $email);
    $service->getDi()['url'] = Mockery::mock()->shouldReceive('adminLink')->once()->with('order/manage/11')->andReturn('/admin/order/manage/11')->getMock();

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

test('automatic cancellation recovers a failed manager before later requests and cron tasks', function (string $failure): void {
    $db = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $em = FOSSBilling\Core\Doctrine\EntityManagerFactory::create($db);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [
        Client::class, Order::class, ClientOrderCancelRequest::class, Box\Mod\Order\Entity\OrderMeta::class, Box\Mod\Order\Entity\OrderStatus::class, Box\Mod\System\Entity\Setting::class,
    ]));
    $db->executeStatement('CREATE TABLE session (lifetime INTEGER, created_at INTEGER)');
    $client = (new Client())->setEmail('cancel@example.com');
    $em->persist($client);
    $em->persist((new Box\Mod\System\Entity\Setting())->setParam('cancel_requests_last_day')->setValue('2000-01-01'));
    $em->flush();
    $requestIds = [];
    $orderIds = [];
    $requestCount = $failure === 'only request' ? 1 : 2;
    for ($i = 0; $i < $requestCount; ++$i) {
        $order = (new Order())->setClientId($client->getId())->setStatus(Order::STATUS_ACTIVE)->setExpiresAt(new DateTimeImmutable('+1 month'));
        $em->persist($order);
        $em->flush();
        $request = (new ClientOrderCancelRequest())->setOrder($order)->setClient($client)->setType(ClientOrderCancelRequest::TYPE_END_OF_PERIOD)->setStatus(ClientOrderCancelRequest::STATUS_PENDING);
        $em->persist($request);
        $em->flush();
        $requestIds[] = $request->getId();
        $orderIds[] = $order->getId();
    }
    if ($failure === 'daily setting') {
        $db->executeStatement("CREATE TRIGGER fail_day BEFORE UPDATE OF value ON setting WHEN NEW.param = 'cancel_requests_last_day' BEGIN SELECT RAISE(ABORT, 'daily setting flush failed'); END");
    } else {
        $db->executeStatement("CREATE TRIGGER fail_cancel BEFORE INSERT ON client_order_meta WHEN NEW.client_order_id = {$orderIds[0]} BEGIN SELECT RAISE(ABORT, 'cancellation metadata flush failed'); END");
    }
    $di = container();
    $di['em'] = fn (): Doctrine\ORM\EntityManagerInterface => $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod'] = $di->protect(fn (): object => new class {
        public function getConfig(): array
        {
            return ['cancel_requests_auto' => 1];
        }
    });
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptions->shouldReceive('hasActiveSubscriptionForOrder')->andReturnFalse();
    $subscriptions->shouldReceive('hasUncanceledSubscriptionForOrder')->andReturnFalse();
    $defaults = Tests\Helpers\moduleService(['invoice:subscription' => $subscriptions]);
    $di['mod_service'] = $di->protect(function (string $module, string $sub = '') use ($di, $defaults): object {
        if ($sub === '' && in_array(strtolower($module), ['order', 'system'], true)) {
            $service = strtolower($module) === 'order' ? new Box\Mod\Order\Service() : new Box\Mod\System\Service();
            $service->setDi($di);

            return $service;
        }

        return $defaults($module, $sub);
    });
    $service = new ServiceCancelRequest();
    $service->setDi($di);
    $oldRepository = $service->getCancelRequestRepository();
    $listenerManagers = [];
    $di['event_dispatcher'] = new FOSSBilling\Core\Events\EventDispatcher(fn (): array => ['invoice'], function () use ($di, &$listenerManagers): object {
        $manager = $di['em'];
        $listenerManagers[] = $manager;
        $listener = Mockery::mock(Box\Mod\Invoice\Service::class)->shouldIgnoreMissing();
        $listener->shouldReceive('healMissingJournalEntries')->zeroOrMoreTimes()->andReturnUsing(function () use ($manager): void {
            expect($manager->isOpen())->toBeTrue();
            $manager->persist((new Box\Mod\System\Entity\Setting())->setParam('after_cron_listener')->setValue('healthy'));
            $manager->flush();
        });

        return $listener;
    });
    $di['api_system'] = new readonly class($service, $di) {
        public function __construct(private ServiceCancelRequest $service, private Pimple\Container $di)
        {
        }

        public function __call(string $method, array $args): void
        {
            if ($method === 'order_batch_process_cancel_requests') {
                $this->service->runAutoProcess();
            } elseif (in_array($method, ['support_batch_ticket_auto_close', 'client_batch_expire_password_reminders', 'cart_batch_expire', 'email_batch_sendmail'], true)) {
                $manager = $this->di['em'];
                expect($manager->isOpen())->toBeTrue();
                $manager->persist((new Box\Mod\System\Entity\Setting())->setParam($method)->setValue('healthy'));
                $manager->flush();
            }
        }
    };
    $finalization = Mockery::mock();
    $finalization->shouldReceive('withRuntimeLock')->andReturnUsing(fn (callable $work): mixed => $work());
    $finalization->shouldReceive('isRequired')->andReturnFalse();
    $finalization->shouldReceive('healSchemaDrift')->andReturnNull();
    $di['update_finalization'] = $finalization;
    $cron = new Box\Mod\Cron\Service();
    $cron->setDi($di);
    ob_start();

    try {
        expect($cron->runCrons())->toBe($failure !== 'daily setting')
            ->and($em->isOpen())->toBeFalse()
            ->and($di['em']->isOpen())->toBeTrue()
            ->and($di['em'])->not->toBe($em)
            ->and($di['em']->getConnection())->toBe($db)
            ->and($service->getCancelRequestRepository())->not->toBe($oldRepository)
            ->and($service->getCancelRequestRepository())->toBe($di['em']->getRepository(ClientOrderCancelRequest::class))
            ->and($listenerManagers)->toHaveCount(2)
            ->and($listenerManagers[1])->toBe($di['em']);
        foreach ($requestIds as $i => $requestId) {
            $failed = $i === 0 && $failure !== 'daily setting';
            expect($db->fetchOne('SELECT status FROM client_order_cancel_request WHERE id = ?', [$requestId]))->toBe($failed ? 'pending' : 'accepted')
                ->and($db->fetchOne('SELECT value FROM client_order_meta WHERE client_order_id = ?', [$orderIds[$i]]))->toBe($failed ? false : '1');
        }
        expect($db->fetchOne("SELECT value FROM setting WHERE param = 'cancel_requests_last_day'"))->toBe($failure === 'daily setting' ? '2000-01-01' : date('Y-m-d'));
        foreach (['support_batch_ticket_auto_close', 'client_batch_expire_password_reminders', 'cart_batch_expire', 'email_batch_sendmail', 'after_cron_listener', 'last_cron_exec'] as $param) {
            expect($db->fetchOne('SELECT value FROM setting WHERE param = ?', [$param]))->not->toBeFalse();
        }
    } finally {
        ob_end_clean();
        $db->close();
    }
})->with(['first request', 'only request', 'daily setting']);
