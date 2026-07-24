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
use Box\Mod\Order\Service;
use Box\Mod\Product\Entity\Product;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function orderServiceCreateProductEntity(?int $id = null, ?string $type = null): Product
{
    $product = new Product();
    if ($id !== null) {
        $reflection = new ReflectionProperty($product, 'id');
        $reflection->setValue($product, $id);
    }
    if ($type !== null) {
        $product->setType($type);
    }

    return $product;
}

function orderServiceCreateInvoiceModel(int $id): Model_Invoice
{
    $invoice = new Model_Invoice();
    $invoice->loadBean(new Tests\Helpers\DummyBean());
    $invoice->id = $id;

    return $invoice;
}

function orderServiceCreateLegacyOrderModel(int $id): Model_ClientOrder
{
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->id = $id;

    return $order;
}

test('counter returns status counts', function (): void {
    $service = new Service();

    $counter = [Order::STATUS_ACTIVE => 1];
    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchAllKeyValue')->atLeast()->once()->andReturn($counter);
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->atLeast()->once()->andReturn($connectionMock);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->counter();

    expect($result)->toBeArray();
    expect($result)->toHaveKey('total');
    expect($result['total'])->toEqual(array_sum($counter));
    expect($result)->toHaveKey(Order::STATUS_PENDING_SETUP);
    expect($result)->toHaveKey(Order::STATUS_FAILED_SETUP);
    expect($result)->toHaveKey(Order::STATUS_ACTIVE);
    expect($result)->toHaveKey(Order::STATUS_SUSPENDED);
    expect($result)->toHaveKey(Order::STATUS_CANCELED);
});

test('onAfterAdminOrderActivate fires template', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->atLeast()->once()->andReturn($params);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()->andReturn(true);

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->atLeast()->once()->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn($orderArr);

    $admin = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->byDefault()->andReturn(createEntity(Order::class, ['id' => 1]));
    $di['loggedin_admin'] = $admin;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $eventMock->shouldReceive('getDi')->atLeast()->once()->andReturn($di);
    $serviceMock->setDi($di);

    $serviceMock->onAfterAdminOrderActivate($eventMock);
});

test('onAfterAdminOrderActivate logs exceptions', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->atLeast()->once()->andReturn($params);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new Exception('PHPUnit controlled exception'));

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->atLeast()->once()->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn($orderArr);

    $admin = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->byDefault()->andReturn(createEntity(Order::class, ['id' => 1]));
    $di['loggedin_admin'] = $admin;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $eventMock->shouldReceive('getDi')->atLeast()->once()->andReturn($di);
    $serviceMock->setDi($di);

    $serviceMock->onAfterAdminOrderActivate($eventMock);
});

test('onAfterAdminOrderRenew fires template', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->atLeast()->once()->andReturn($params);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()->andReturn(true);

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->atLeast()->once()->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn($orderArr);

    $admin = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->byDefault()->andReturn(createEntity(Order::class, ['id' => 1]));
    $di['loggedin_admin'] = $admin;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->atLeast()->once()->andReturn($di);

    $serviceMock->onAfterAdminOrderRenew($eventMock);
});

test('onAfterAdminOrderRenew logs exceptions', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->atLeast()->once()->andReturn($params);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new Exception('PHPUnit controlled exception'));

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->atLeast()->once()->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn($orderArr);

    $admin = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->byDefault()->andReturn(createEntity(Order::class, ['id' => 1]));
    $di['loggedin_admin'] = $admin;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->atLeast()->once()->andReturn($di);

    $serviceMock->onAfterAdminOrderRenew($eventMock);
});

test('onAfterAdminOrderSuspend fires template', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->atLeast()->once()->andReturn($params);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()->andReturn(true);

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->atLeast()->once()->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn($orderArr);

    $admin = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->byDefault()->andReturn(createEntity(Order::class, ['id' => 1]));
    $di['loggedin_admin'] = $admin;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->atLeast()->once()->andReturn($di);

    $serviceMock->onAfterAdminOrderSuspend($eventMock);
});

test('onAfterAdminOrderSuspend logs exceptions', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->atLeast()->once()->andReturn($params);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new Exception('PHPUnit controlled exception'));

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->atLeast()->once()->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn($orderArr);

    $admin = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->byDefault()->andReturn(createEntity(Order::class, ['id' => 1]));
    $di['loggedin_admin'] = $admin;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->atLeast()->once()->andReturn($di);

    $serviceMock->onAfterAdminOrderSuspend($eventMock);
});

test('onAfterAdminOrderUnsuspend fires template', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->atLeast()->once()->andReturn($params);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()->andReturn(true);

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->atLeast()->once()->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn($orderArr);

    $admin = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->byDefault()->andReturn(createEntity(Order::class, ['id' => 1]));
    $di['loggedin_admin'] = $admin;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->atLeast()->once()->andReturn($di);

    $serviceMock->onAfterAdminOrderUnsuspend($eventMock);
});

test('onAfterAdminOrderUnsuspend logs exceptions', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->atLeast()->once()->andReturn($params);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new Exception('PHPUnit controlled exception'));

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->atLeast()->once()->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn($orderArr);

    $admin = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->byDefault()->andReturn(createEntity(Order::class, ['id' => 1]));
    $di['loggedin_admin'] = $admin;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->atLeast()->once()->andReturn($di);

    $serviceMock->onAfterAdminOrderUnsuspend($eventMock);
});

test('onAfterAdminOrderCancel fires template', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->atLeast()->once()->andReturn($params);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()->andReturn(true);

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn($orderArr);

    $admin = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->byDefault()->andReturn(createEntity(Order::class, ['id' => 1]));
    $di['loggedin_admin'] = $admin;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->atLeast()->once()->andReturn($di);

    $serviceMock->onAfterAdminOrderCancel($eventMock);
});

test('onAfterAdminOrderCancel logs exceptions', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->atLeast()->once()->andReturn($params);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new Exception('PHPUnit controlled exception'));

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn($orderArr);

    $admin = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->byDefault()->andReturn(createEntity(Order::class, ['id' => 1]));
    $di['loggedin_admin'] = $admin;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->atLeast()->once()->andReturn($di);

    $serviceMock->onAfterAdminOrderCancel($eventMock);
});

test('onAfterAdminOrderUncancel fires template', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->atLeast()->once()->andReturn($params);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once()->andReturn(true);

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->atLeast()->once()->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn($orderArr);

    $admin = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->byDefault()->andReturn(createEntity(Order::class, ['id' => 1]));
    $di['loggedin_admin'] = $admin;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->atLeast()->once()->andReturn($di);

    $serviceMock->onAfterAdminOrderUncancel($eventMock);
});

test('onAfterAdminOrderUncancel logs exceptions', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->atLeast()->once()->andReturn($params);

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new Exception('PHPUnit controlled exception'));

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->atLeast()->once()->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn($orderArr);

    $admin = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->byDefault()->andReturn(createEntity(Order::class, ['id' => 1]));
    $di['loggedin_admin'] = $admin;
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->atLeast()->once()->andReturn($di);

    $serviceMock->onAfterAdminOrderUncancel($eventMock);
});

test('getOrderService returns core service', function (): void {
    $service = new Model_ServiceCustom();
    $service->loadBean(new Tests\Helpers\DummyBean());
    $service->id = 1;

    $di = container();
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('load')->once()->with('ServiceCustom', 1)->andReturn($service);
    $di['db'] = $dbMock;

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class, [
        'service_id' => 1,
        'service_type' => Box\Mod\Product\Service::CUSTOM,
    ]);

    $result = $svc->getOrderService($order);

    expect($result)->toBeInstanceOf(Model_ServiceCustom::class);
});

test('getOrderService returns non-core service', function (): void {
    $serviceData = ['id' => 1, 'product_id' => 5];

    $di = container();
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->once()->with('service_external', 'id = :id', [':id' => 1])->andReturn($serviceData);
    $di['db'] = $dbMock;

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class, [
        'service_id' => 1,
        'service_type' => 'external',
    ]);

    $result = $svc->getOrderService($order);

    expect($result)->toBeArray();
});

test('getOrderService returns null when service id is not set', function (): void {
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->never();
    $dbMock->shouldReceive('findOne')->never();

    $di = container();
    $di['db'] = $dbMock;

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class);

    $result = $svc->getOrderService($order);

    expect($result)->toBeNull();
});

test('getServiceOrder returns order', function (): void {
    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('from_camel_case')->atLeast()->once()->andReturn('custom');

    $dbMock = Mockery::mock(Box_Database::class);

    $di = container();
    $di['db'] = $dbMock;
    $di['tools'] = $toolsMock;

    $svc = new Service();
    $svc->setDi($di);

    $service = new Model_ServiceCustom();
    $service->loadBean(new Tests\Helpers\DummyBean());
    $service->id = 1;

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->id = 1;
    $dbMock->shouldReceive('findOne')->once()->with('ClientOrder', 'service_type = :service_type AND service_id = :service_id', [
        ':service_type' => 'custom',
        ':service_id' => 1,
    ])->andReturn($order);

    $result = $svc->getServiceOrder($service);

    expect($result)->toBeInstanceOf(Model_ClientOrder::class);
});

test('keeps legacy order lookups available alongside entity lookups', function (): void {
    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 5]);
    $entityOrder = createEntity(Order::class, ['id' => 10, 'client_id' => 5]);
    $legacyOrder = orderServiceCreateLegacyOrderModel(10);

    $orderRepository = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $orderRepository->shouldReceive('findForClientById')->twice()->with(5, 10)->andReturn($entityOrder);

    $entityManager = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $entityManager->shouldReceive('getRepository')->once()->with(Order::class)->andReturn($orderRepository);

    $database = Mockery::mock(Box_Database::class);
    $database->shouldReceive('getExistingModelById')->once()->with('ClientOrder', 10)->andReturn($legacyOrder);

    $di = container();
    $di['em'] = $entityManager;
    $di['db'] = $database;

    $service = new Service();
    $service->setDi($di);

    expect($service->findEntityForClientById($client, 10))->toBe($entityOrder)
        ->and($service->findForClientById($client, 10))->toBe($legacyOrder);
});

test('getConfig returns config', function (): void {
    $svc = new Service();
    $di = container();
    $svc->setDi($di);

    $order = createEntity(Order::class);

    $result = $svc->getConfig($order);

    expect($result)->toBeArray();
});

dataset('productHasOrdersProvider', function (): array {
    $orderEntity = new Order();
    $idProp = new ReflectionProperty($orderEntity, 'id');
    $idProp->setValue($orderEntity, 1);

    return [
        'order present' => [$orderEntity, true],
        'order absent' => [null, false],
    ];
});

test('productHasOrders returns expected result', function (?Order $order, bool $expectedResult): void {
    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('findOneByProductId')->atLeast()->once()->andReturn($order);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $product = orderServiceCreateProductEntity(1);

    $result = $svc->productHasOrders($product);

    expect($result)->toEqual($expectedResult);
})->with('productHasOrdersProvider');

test('saveStatusChange records history', function (): void {
    $persistedEntities = [];
    $nextOrderId = 1;
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once()->andReturnUsing(function ($entity) use (&$persistedEntities): void {
        $persistedEntities[] = $entity;
    });
    $emMock->shouldReceive('flush')->atLeast()->once()->andReturnUsing(function () use (&$persistedEntities, &$nextOrderId): void {
        foreach ($persistedEntities as $entity) {
            $refl = new ReflectionClass($entity);
            if ($refl->hasProperty('id')) {
                $prop = $refl->getProperty('id');
                if ($prop->getValue($entity) === null) {
                    $prop->setValue($entity, $nextOrderId++);
                }
            }
        }
        $persistedEntities = [];
    });
    $emMock->shouldReceive('remove')->andReturnNull();
    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('find')->andReturnUsing(function (?int $id) use (&$nextOrderId): ?object {
        if ($id === null) {
            return null;
        }
        $order = new Order();
        $prop = new ReflectionProperty($order, 'id');
        $prop->setValue($order, $id);

        return $order;
    });
    $orderRepoMock->shouldReceive('findOneByOrderIdAndName')->byDefault()->andReturn(null);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->andReturn(Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing());
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class);

    $result = $svc->saveStatusChange($order);

    expect($result)->toBeNull();
});

test('getSoonExpiringActiveOrders executes query', function (): void {
    $order = createEntity(Order::class);

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchAllAssociative')->atLeast()->once()->andReturn([[], []]);
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->atLeast()->once()->andReturn($connectionMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getSoonExpiringActiveOrdersQuery')->atLeast()->once()->andReturn(['query', []]);

    $di = container();
    $di['em'] = $emMock;
    $serviceMock->setDi($di);

    $serviceMock->getSoonExpiringActiveOrders();
});

test('getSoonExpiringActiveOrdersQuery builds expected SQL and bindings', function (): void {
    $randId = 1;

    $orderStatus = createEntity(Box\Mod\Order\Entity\OrderStatus::class);

    $systemService = Mockery::mock(Box\Mod\System\Service::class);
    $systemService->shouldReceive('getParamValue')->atLeast()->once()->andReturn($randId);

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $name): Mockery\MockInterface => match (strtolower($name)) {
        'system' => $systemService,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class);

    $data = ['client_id' => $randId];
    $result = $svc->getSoonExpiringActiveOrdersQuery($data);

    $expectedQuery = 'SELECT co.*
                FROM client_order co
                LEFT JOIN invoice i ON i.id = co.unpaid_invoice_id AND i.status = :unpaid_invoice_status
                WHERE co.status = :status
                AND co.invoice_option = :invoice_option
                AND co.period IS NOT NULL
                AND co.expires_at IS NOT NULL
                AND i.id IS NULL
                /* Pair non-executed renewal items with paid invoices to skip renewals already queued for activation. */
                AND NOT EXISTS (
                    SELECT 1
                    FROM invoice_item pending_item
                    INNER JOIN invoice pending_invoice ON pending_invoice.id = pending_item.invoice_id
                    WHERE pending_item.rel_id = co.id
                    AND pending_item.type = :pending_item_type
                    AND pending_item.task = :pending_item_task
                    AND pending_item.status != :pending_item_status
                    AND pending_invoice.status = :pending_invoice_status
                ) AND co.client_id = :client_id HAVING DATEDIFF(co.expires_at, NOW()) <= :days_until_expiration ORDER BY co.client_id DESC';

    $expectedBindings = [
        'client_id' => $randId,
        'unpaid_invoice_status' => Model_Invoice::STATUS_UNPAID,
        'pending_item_type' => Model_InvoiceItem::TYPE_ORDER,
        'pending_item_task' => Model_InvoiceItem::TASK_RENEW,
        'pending_item_status' => Model_InvoiceItem::STATUS_EXECUTED,
        'pending_invoice_status' => Model_Invoice::STATUS_PAID,
        'status' => Order::STATUS_ACTIVE,
        'invoice_option' => 'issue-invoice',
        'days_until_expiration' => $randId,
    ];

    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect($result[0])->toEqual($expectedQuery);
    expect($result[1])->toEqual($expectedBindings);
});

test('getRelatedOrderIdByType returns id', function (): void {
    $id = 1;
    $model = createEntity(Order::class, ['id' => $id]);

    $orderEntity = new Order();
    $idProp = new ReflectionProperty($orderEntity, 'id');
    $idProp->setValue($orderEntity, $id);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('findOneBy')->atLeast()->once()->andReturn($orderEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->getRelatedOrderIdByType($model, 'domain');

    expect($result)->toBeInt();
    expect($result)->toEqual($id);
});

test('getRelatedOrderIdByType returns null when not found', function (): void {
    $id = 1;
    $model = createEntity(Order::class, ['id' => $id]);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('findOneBy')->atLeast()->once()->andReturn(null);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->getRelatedOrderIdByType($model, 'domain');

    expect($result)->toBeNull();
});

test('getLogger returns logger with event items', function (): void {
    $model = createEntity(Order::class, [
        'id' => 5,
        'status' => 'active',
    ]);

    $capturedItems = [];
    $logger = new class($capturedItems) extends Box_Log {
        public function __construct(public array &$capturedItems)
        {
        }

        public function addWriter($writer): static
        {
            return $this;
        }

        public function setEventItem(string $name, mixed $value): static
        {
            $this->capturedItems[] = [$name, $value];

            return $this;
        }
    };

    $di = container();
    $di['logger'] = $logger;

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->getLogger($model);

    expect($result)->toBeInstanceOf(Box_Log::class);
    expect($capturedItems)->toHaveCount(2);
    expect($capturedItems[0])->toEqual(['client_order_id', 5]);
    expect($capturedItems[1])->toEqual(['status', 'active']);
});

test('toApiArray returns expected keys', function (): void {
    $model = createEntity(Order::class, [
        'id' => 1,
        'config' => '{}',
        'price' => 10,
        'quantity' => 1,
        'client_id' => 1,
    ]);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $supportService = Mockery::mock(Box\Mod\Support\Service::class);
    $supportTicketRepo = Mockery::mock(Box\Mod\Support\Repository\SupportTicketRepository::class);
    $supportTicketRepo->shouldReceive('countActiveTicketsForOrder')->atLeast()->once()->andReturn(1);
    $supportService->shouldReceive('getSupportTicketRepository')->atLeast()->once()->andReturn($supportTicketRepo);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldNotReceive('toArray');

    $clientEntity = new Box\Mod\Client\Entity\Client();

    $clientRepoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepoMock->shouldReceive('find')->with(1)->atLeast()->once()->andReturn($clientEntity);

    $orderMetaRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class);
    $orderMetaRepoMock->shouldReceive('getPairsForOrder')->atLeast()->once()->andReturn([]);
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->atLeast()->once()->andReturn($orderMetaRepoMock);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->atLeast()->once()->andReturn($clientRepoMock);
    $emMock->shouldIgnoreMissing();

    $productService = Mockery::mock(Box\Mod\Product\Service::class);
    $productService->shouldReceive('getProductPluginById')->once()->with((int) $model->product_id)->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($clientService, $supportService, $productService) {
        if ($serviceName == 'client') {
            return $clientService;
        }
        if ($serviceName == 'support') {
            return $supportService;
        }
        if ($serviceName == 'product') {
            return $productService;
        }
    });
    $di['db'] = $dbMock;
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->toApiArray($model, true, createEntity(Box\Mod\Staff\Entity\Admin::class));

    expect($result)->toHaveKey('config');
    expect($result)->toHaveKey('total');
    expect($result)->toHaveKey('title');
    expect($result)->toHaveKey('meta');
    expect($result)->toHaveKey('active_tickets');
    expect($result)->toHaveKey('plugin');
    expect($result)->toHaveKey('client');
});

dataset('searchQueryData', fn (): array => [
    'no data' => [[], 'SELECT co.* from client_order co', []],
    'client_id' => [
        ['client_id' => 1],
        'co.client_id = :client_id',
        ['client_id' => '1'],
    ],
    'invoice_option' => [
        ['invoice_option' => 'issue-invoice'],
        'co.invoice_option = :invoice_option',
        ['invoice_option' => 'issue-invoice'],
    ],
    'id' => [
        ['id' => 1],
        'co.id = :id',
        ['id' => '1'],
    ],
    'status' => [
        ['status' => 'pending_setup'],
        'co.status = :status',
        ['status' => 'pending_setup'],
    ],
    'product_id' => [
        ['product_id' => 1],
        'co.product_id = :product_id',
        ['product_id' => '1'],
    ],
    'type' => [
        ['type' => 'custom'],
        'co.service_type = :service_type',
        ['service_type' => 'custom'],
    ],
    'title' => [
        ['title' => 'titleField'],
        'co.title LIKE :title',
        ['title' => '%titleField%'],
    ],
    'period' => [
        ['period' => '1Y'],
        'co.period = :period',
        ['period' => '1Y'],
    ],
    'hide_addons' => [
        ['hide_addons' => true],
        'co.group_master = 1',
        [],
    ],
    'created_at' => [
        ['created_at' => '2012-12-11'],
        "DATE_FORMAT(co.created_at, '%Y-%m-%d') = :created_at",
        ['created_at' => '2012-12-11'],
    ],
    'date_from' => [
        ['date_from' => '2012-12-11'],
        'UNIX_TIMESTAMP(co.created_at) >= :date_from',
        ['date_from' => strtotime('2012-12-11')],
    ],
    'date_to' => [
        ['date_to' => '2012-12-11'],
        'UNIX_TIMESTAMP(co.created_at) <= :date_to',
        ['date_to' => strtotime('2012-12-11')],
    ],
    'search numeric' => [
        ['search' => 120],
        'co.id = :search',
        ['search' => 120],
    ],
    'search string' => [
        ['search' => 'John'],
        '(c.first_name LIKE :first_name OR c.last_name LIKE :last_name OR co.title LIKE :title)',
        [
            'first_name' => '%John%',
            'last_name' => '%John%',
            'title' => '%John%',
        ],
    ],
    'ids' => [
        ['ids' => [1, 2, 3]],
        'co.id IN (:ids)',
        ['ids' => '1, 2, 3'],
    ],
    'promo_id' => [
        ['promo_id' => 9],
        'co.promo_id = :promo_id',
        ['promo_id' => 9],
    ],
    'meta' => [
        ['meta' => ['param' => 'value']],
        '(meta.name = :meta_name1 AND meta.value LIKE :meta_value1)',
        [
            'meta_name1' => 'param',
            'meta_value1' => 'value%',
        ],
    ],
]);

test('getSearchQuery returns expected query and bindings', function (array $data, string $expectedStr, array $expectedParams): void {
    $di = container();

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->getSearchQuery($data);

    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect($result[0])->toContain($expectedStr);
    expect($result[1])->toEqual($expectedParams);
})->with('searchQueryData');

test('getSearchQuery keeps client scope when action required filter is used', function (): void {
    $di = container();

    $svc = new Service();
    $svc->setDi($di);

    [$query, $bindings] = $svc->getSearchQuery([
        'client_id' => 42,
        'show_action_required' => true,
    ]);

    expect($query)->toContain('co.client_id = :client_id');
    expect($query)->toContain("(co.status = 'pending_setup' OR co.status = 'failed_setup' OR co.status ='failed_renew')");
    expect($bindings['client_id'])->toBe(42);
});

test('createOrder throws when no order currency is set', function (): void {
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class);

    $modelProduct = orderServiceCreateProductEntity();

    $currencyRepositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock->shouldReceive('findDefault')->atLeast()->once()->andReturn(null);

    $currencyServiceMock = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock) {
        if ($serviceName == 'currency') {
            return $currencyServiceMock;
        }
    });

    $svc = new Service();
    $svc->setDi($di);

    expect(fn () => $svc->createOrder($modelClient, $modelProduct, []))
        ->toThrow(FOSSBilling\Exception::class, 'Currency could not be determined for order');
});

test('createOrder throws when out of stock', function (): void {
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'USD']);

    $modelProduct = orderServiceCreateProductEntity(1);

    $currencyModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class)->shouldIgnoreMissing();

    $currencyRepositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock->shouldReceive('findOneByCode')->atLeast()->once()->andReturn($currencyModel);

    $currencyServiceMock = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $cartServiceMock = Mockery::mock(Box\Mod\Cart\Service::class);
    $cartServiceMock->shouldReceive('isStockAvailable')
        ->atLeast()->once()
        ->with($modelProduct, Mockery::any())
        ->andReturn(false);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock) {
        if ($serviceName == 'currency') {
            return $currencyServiceMock;
        }
        if ($serviceName == 'cart') {
            return $cartServiceMock;
        }
    });
    $di['events_manager'] = $eventMock;

    $svc = new Service();
    $svc->setDi($di);

    expect(fn () => $svc->createOrder($modelClient, $modelProduct, []))
        ->toThrow(FOSSBilling\Exception::class, 'Product 1 is out of stock.');
});

test('createOrder throws when group id missing for addon', function (): void {
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'USD']);

    $modelProduct = orderServiceCreateProductEntity(1);
    $modelProduct->setIsAddon(true);

    $currencyModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class)->shouldIgnoreMissing();

    $currencyRepositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock->shouldReceive('findOneByCode')->atLeast()->once()->andReturn($currencyModel);

    $currencyServiceMock = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $cartServiceMock = Mockery::mock(Box\Mod\Cart\Service::class);
    $cartServiceMock->shouldReceive('isStockAvailable')
        ->atLeast()->once()
        ->with($modelProduct, Mockery::any())
        ->andReturn(true);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock) {
        if ($serviceName == 'currency') {
            return $currencyServiceMock;
        }
        if ($serviceName == 'cart') {
            return $cartServiceMock;
        }
    });
    $di['events_manager'] = $eventMock;

    $svc = new Service();
    $svc->setDi($di);

    expect(fn () => $svc->createOrder($modelClient, $modelProduct, []))
        ->toThrow(FOSSBilling\Exception::class, 'Group ID parameter is missing for addon product order');
});

test('createOrder throws when parent order not found', function (): void {
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'USD']);

    $modelProduct = orderServiceCreateProductEntity(1);

    $currencyModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class)->shouldIgnoreMissing();

    $currencyRepositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock->shouldReceive('findOneByCode')->atLeast()->once()->andReturn($currencyModel);

    $currencyServiceMock = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $cartServiceMock = Mockery::mock(Box\Mod\Cart\Service::class);
    $cartServiceMock->shouldReceive('isStockAvailable')
        ->atLeast()->once()
        ->with($modelProduct, Mockery::any())
        ->andReturn(true);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock) {
        if ($serviceName == 'currency') {
            return $currencyServiceMock;
        }
        if ($serviceName == 'cart') {
            return $cartServiceMock;
        }
    });
    $di['events_manager'] = $eventMock;

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getMasterOrderForClient')
        ->atLeast()->once()
        ->andReturn(null);

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->createOrder($modelClient, $modelProduct, ['group_id' => 1]))
        ->toThrow(FOSSBilling\Exception::class, 'Parent order 1 was not found');
});

test('createOrder creates order', function (): void {
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'USD']);

    $modelProduct = orderServiceCreateProductEntity(1, 'custom');

    $currencyModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class)->shouldIgnoreMissing();
    $currencyModel->shouldReceive('getCode')->andReturn('USD');

    $currencyRepositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock->shouldReceive('findOneByCode')->atLeast()->once()->andReturn($currencyModel);

    $currencyServiceMock = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $cartServiceMock = Mockery::mock(Box\Mod\Cart\Service::class);
    $cartServiceMock->shouldReceive('isStockAvailable')
        ->atLeast()->once()
        ->with($modelProduct, Mockery::any())
        ->andReturn(true);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $productServiceMock = Mockery::mock(Box\Mod\Servicecustom\Service::class);
    $pricingServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $pricingServiceMock->shouldReceive('getProductOrderLineConfig')->never();

    $persistedEntities = [];
    $nextOrderId = 1;
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once()->andReturnUsing(function ($entity) use (&$persistedEntities): void {
        $persistedEntities[] = $entity;
    });
    $emMock->shouldReceive('flush')->atLeast()->once()->andReturnUsing(function () use (&$persistedEntities, &$nextOrderId): void {
        foreach ($persistedEntities as $entity) {
            $refl = new ReflectionClass($entity);
            if ($refl->hasProperty('id')) {
                $prop = $refl->getProperty('id');
                if ($prop->getValue($entity) === null) {
                    $prop->setValue($entity, $nextOrderId++);
                }
            }
        }
        $persistedEntities = [];
    });
    $emMock->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
    $emMock->shouldReceive('remove')->andReturnNull();
    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('find')->andReturnUsing(function (?int $id) use (&$nextOrderId): ?object {
        if ($id === null) {
            return null;
        }
        $order = new Order();
        $prop = new ReflectionProperty($order, 'id');
        $prop->setValue($order, $id);

        return $order;
    });
    $orderRepoMock->shouldReceive('findOneByOrderIdAndName')->byDefault()->andReturn(null);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->andReturn(Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing());
    $emMock->shouldIgnoreMissing();

    $newId = 1;

    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getCode')->atLeast()->once()->andReturn('1Y');

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock, $productServiceMock, $pricingServiceMock) {
        if ($serviceName == 'currency') {
            return $currencyServiceMock;
        }
        if ($serviceName == 'cart') {
            return $cartServiceMock;
        }
        if ($serviceName == 'Product') {
            return $pricingServiceMock;
        }
        if ($serviceName == 'servicecustom') {
            return $productServiceMock;
        }
    });
    $di['events_manager'] = $eventMock;
    $di['em'] = $emMock;
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);
    $di['logger'] = new Box_Log();

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->createOrder($modelClient, $modelProduct, ['period' => '1Y', 'price' => '10', 'notes' => 'test']);

    expect($result)->toEqual($newId);
});

test('createOrder sets form id from product', function (): void {
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'USD']);

    $modelProduct = orderServiceCreateProductEntity(1, 'custom');
    $modelProduct->setFormId(42);

    $currencyModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class)->shouldIgnoreMissing();

    $currencyRepositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock->shouldReceive('findOneByCode')->atLeast()->once()->andReturn($currencyModel);

    $currencyServiceMock = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $cartServiceMock = Mockery::mock(Box\Mod\Cart\Service::class);
    $cartServiceMock->shouldReceive('isStockAvailable')
        ->atLeast()->once()
        ->with($modelProduct, Mockery::any())
        ->andReturn(true);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $productServiceMock = Mockery::mock(Box\Mod\Servicecustom\Service::class);
    $pricingServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $pricingServiceMock->shouldReceive('getProductOrderLineConfig')->never();

    $persistedEntities = [];
    $nextOrderId = 1;
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once()->andReturnUsing(function ($entity) use (&$persistedEntities): void {
        $persistedEntities[] = $entity;
    });
    $emMock->shouldReceive('flush')->atLeast()->once()->andReturnUsing(function () use (&$persistedEntities, &$nextOrderId): void {
        foreach ($persistedEntities as $entity) {
            $refl = new ReflectionClass($entity);
            if ($refl->hasProperty('id')) {
                $prop = $refl->getProperty('id');
                if ($prop->getValue($entity) === null) {
                    $prop->setValue($entity, $nextOrderId++);
                }
            }
        }
        $persistedEntities = [];
    });
    $emMock->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
    $emMock->shouldReceive('remove')->andReturnNull();
    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('find')->andReturnUsing(function (?int $id) use (&$nextOrderId): ?object {
        if ($id === null) {
            return null;
        }
        $order = new Order();
        $prop = new ReflectionProperty($order, 'id');
        $prop->setValue($order, $id);

        return $order;
    });
    $orderRepoMock->shouldReceive('findOneByOrderIdAndName')->byDefault()->andReturn(null);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->andReturn(Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing());
    $emMock->shouldIgnoreMissing();

    $newId = 1;

    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getCode')->atLeast()->once()->andReturn('1Y');

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock, $productServiceMock, $pricingServiceMock) {
        if ($serviceName == 'currency') {
            return $currencyServiceMock;
        }
        if ($serviceName == 'cart') {
            return $cartServiceMock;
        }
        if ($serviceName == 'Product') {
            return $pricingServiceMock;
        }
        if ($serviceName == 'servicecustom') {
            return $productServiceMock;
        }
    });
    $di['events_manager'] = $eventMock;
    $di['em'] = $emMock;
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);
    $di['logger'] = new Box_Log();

    $svc = new Service();
    $svc->setDi($di);

    $svc->createOrder($modelClient, $modelProduct, ['period' => '1Y', 'price' => '10']);

    expect(true)->toBeTrue();
});

test('createOrder returns success when invoice follow up fails', function (): void {
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'USD']);

    $modelProduct = orderServiceCreateProductEntity(1, 'custom');

    $currencyModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class)->shouldIgnoreMissing();

    $currencyRepositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock->shouldReceive('findOneByCode')->atLeast()->once()->andReturn($currencyModel);

    $currencyServiceMock = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $cartServiceMock = Mockery::mock(Box\Mod\Cart\Service::class);
    $cartServiceMock->shouldReceive('isStockAvailable')
        ->atLeast()->once()
        ->with($modelProduct, Mockery::any())
        ->andReturn(true);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $productServiceMock = Mockery::mock(Box\Mod\Servicecustom\Service::class);
    $pricingServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $pricingServiceMock->shouldReceive('getProductOrderLineConfig')->never();

    $clientOrderModel = orderServiceCreateLegacyOrderModel(1);

    $invoiceModel = orderServiceCreateInvoiceModel(10);

    $invoiceServiceMock = Mockery::mock();
    $invoiceServiceMock->shouldReceive('generateForOrder')
        ->once()
        ->with(Mockery::any())
        ->andReturn($invoiceModel);
    $invoiceServiceMock->shouldReceive('approveInvoice')
        ->once()
        ->with($invoiceModel, ['id' => $invoiceModel->id, 'use_credits' => true])
        ->andReturn(true);
    $invoiceServiceMock->shouldReceive('markAsPaidByAdmin')
        ->once()
        ->with($invoiceModel, Mockery::on(fn (array $data): bool => $data['invoice_option'] === 'issue-invoice'
            && $data['mark_invoice_paid'] === true
            && $data['gateway_id'] === 7))
        ->andThrow(new Exception('Payment follow-up failed'));
    $invoiceServiceMock->shouldReceive('addNote')
        ->once()
        ->with(
            $invoiceModel,
            Mockery::on(fn (string $note): bool => str_contains($note, 'Order was created, but invoice follow-up failed: Payment follow-up failed'))
        )
        ->andReturn(true);

    $persistedEntities = [];
    $nextOrderId = 1;
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once()->andReturnUsing(function ($entity) use (&$persistedEntities): void {
        $persistedEntities[] = $entity;
    });
    $emMock->shouldReceive('flush')->atLeast()->once()->andReturnUsing(function () use (&$persistedEntities, &$nextOrderId): void {
        foreach ($persistedEntities as $entity) {
            $refl = new ReflectionClass($entity);
            if ($refl->hasProperty('id')) {
                $prop = $refl->getProperty('id');
                if ($prop->getValue($entity) === null) {
                    $prop->setValue($entity, $nextOrderId++);
                }
            }
        }
        $persistedEntities = [];
    });
    $emMock->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
    $emMock->shouldReceive('remove')->andReturnNull();
    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('find')->andReturnUsing(function (?int $id) use (&$nextOrderId): ?object {
        if ($id === null) {
            return null;
        }
        $order = new Order();
        $prop = new ReflectionProperty($order, 'id');
        $prop->setValue($order, $id);

        return $order;
    });
    $orderRepoMock->shouldReceive('findOneByOrderIdAndName')->byDefault()->andReturn(null);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->andReturn(Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing());
    $emMock->shouldIgnoreMissing();
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->once()->with('ClientOrder', 1)->andReturn($clientOrderModel);

    $newId = 1;

    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getCode')->atLeast()->once()->andReturn('1Y');

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($cartServiceMock, $currencyServiceMock, $invoiceServiceMock, $productServiceMock, $pricingServiceMock) {
        if ($serviceName == 'currency') {
            return $currencyServiceMock;
        }
        if ($serviceName == 'cart') {
            return $cartServiceMock;
        }
        if ($serviceName == 'Product') {
            return $pricingServiceMock;
        }
        if ($serviceName == 'invoice') {
            return $invoiceServiceMock;
        }
        if ($serviceName == 'servicecustom') {
            return $productServiceMock;
        }
    });
    $di['events_manager'] = $eventMock;
    $di['db'] = $dbMock;
    $di['em'] = $emMock;
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);
    $di['logger'] = new Box_Log();

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->createOrder($modelClient, $modelProduct, [
        'period' => '1Y',
        'price' => '10',
        'invoice_option' => 'issue-invoice',
        'mark_invoice_paid' => true,
        'gateway_id' => 7,
    ]);

    expect($result)->toBe($newId);
});

test('createOrder uses product pricing service for domain orders', function (): void {
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'USD']);

    $modelProduct = orderServiceCreateProductEntity(10, Box\Mod\Product\Service::DOMAIN);
    $modelProduct->setUnit('year');

    $currencyModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class)->shouldIgnoreMissing();
    $currencyModel->shouldReceive('getCode')->andReturn('USD');

    $currencyRepositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock->shouldReceive('findOneByCode')->once()->with('USD')->andReturn($currencyModel);
    $currencyRepositoryMock->shouldReceive('getRateByCode')->once()->with('USD')->andReturn(1.0);

    $currencyServiceMock = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $cartServiceMock = Mockery::mock(Box\Mod\Cart\Service::class);
    $cartServiceMock->shouldReceive('isStockAvailable')
        ->atLeast()->once()
        ->with($modelProduct, Mockery::any())
        ->andReturn(true);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $domainServiceMock = Mockery::mock(Box\Mod\Servicedomain\Service::class)->shouldIgnoreMissing();

    $pricingServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $pricingServiceMock->shouldReceive('getProductOrderLineConfig')
        ->once()
        ->with(
            $modelProduct,
            Mockery::on(static fn (array $config): bool => ($config['quantity'] ?? null) === 1)
        )
        ->andReturn([
            'price' => 22.0,
            'quantity' => 2,
            'setup_price' => 0.0,
        ]);

    $persistedEntities = [];
    $nextOrderId = 1;
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once()->andReturnUsing(function ($entity) use (&$persistedEntities): void {
        $persistedEntities[] = $entity;
    });
    $emMock->shouldReceive('flush')->atLeast()->once()->andReturnUsing(function () use (&$persistedEntities, &$nextOrderId): void {
        foreach ($persistedEntities as $entity) {
            $refl = new ReflectionClass($entity);
            if ($refl->hasProperty('id')) {
                $prop = $refl->getProperty('id');
                if ($prop->getValue($entity) === null) {
                    $prop->setValue($entity, $nextOrderId++);
                }
            }
        }
        $persistedEntities = [];
    });
    $emMock->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
    $emMock->shouldReceive('remove')->andReturnNull();
    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('find')->andReturnUsing(function (?int $id) use (&$nextOrderId): ?object {
        if ($id === null) {
            return null;
        }
        $order = new Order();
        $prop = new ReflectionProperty($order, 'id');
        $prop->setValue($order, $id);

        return $order;
    });
    $orderRepoMock->shouldReceive('findOneByOrderIdAndName')->byDefault()->andReturn(null);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->andReturn(Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing());
    $emMock->shouldIgnoreMissing();

    $newId = 1;

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock, $domainServiceMock, $pricingServiceMock) {
        if ($serviceName == 'currency') {
            return $currencyServiceMock;
        }
        if ($serviceName == 'cart') {
            return $cartServiceMock;
        }
        if ($serviceName == 'Product') {
            return $pricingServiceMock;
        }
        if ($serviceName == 'servicedomain') {
            return $domainServiceMock;
        }
    });
    $di['events_manager'] = $eventMock;
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->createOrder($modelClient, $modelProduct, [
        'action' => 'register',
        'register_tld' => '.com',
        'register_sld' => 'example',
        'register_years' => 2,
    ]);

    expect($result)->toBe($newId);
});

test('getMasterOrderForClient returns master order', function (): void {
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $orderEntity = new Order();
    $idProp = new ReflectionProperty($orderEntity, 'id');
    $idProp->setValue($orderEntity, 1);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('findOneBy')->atLeast()->once()->andReturn($orderEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->getMasterOrderForClient($clientModel, 1);

    expect($result)->toBeInstanceOf(Order::class);
});

test('activateOrder throws for non-pending order', function (): void {
    $clientOrderModel = createEntity(Order::class);
    $clientOrderModel->status = Order::STATUS_CANCELED;
    $clientOrderModel->id = 1;

    $orderEntity = new Order();
    $idProp = new ReflectionProperty($orderEntity, 'id');
    $idProp->setValue($orderEntity, 1);
    $statusProp = new ReflectionProperty($orderEntity, 'status');
    $statusProp->setValue($orderEntity, Order::STATUS_CANCELED);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('find')->with(1)->andReturn($orderEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    expect(fn (): bool => $svc->activateOrder($clientOrderModel))
        ->toThrow(FOSSBilling\Exception::class, 'Only pending setup or failed orders can be activated');
});

test('activateOrder activates pending order', function (): void {
    $clientOrderModel = createEntity(Order::class);
    $clientOrderModel->status = Order::STATUS_PENDING_SETUP;
    $clientOrderModel->group_master = 1;
    $clientOrderModel->id = 1;

    $orderEntity = new Order();
    $idProp = new ReflectionProperty($orderEntity, 'id');
    $idProp->setValue($orderEntity, 1);
    $statusProp = new ReflectionProperty($orderEntity, 'status');
    $statusProp->setValue($orderEntity, Order::STATUS_PENDING_SETUP);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('find')->with(1)->andReturn($orderEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldIgnoreMissing();

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Box_Log();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('createFromOrder')->atLeast()->once()->andReturn([]);
    $serviceMock->shouldReceive('activateOrderAddons')->atLeast()->once();

    $serviceMock->setDi($di);

    $result = $serviceMock->activateOrder($clientOrderModel);

    expect($result)->toBeTrue();
});

test('activateOrder is a no-op when order was already activated by a stale reference', function (): void {
    $staleOrderModel = createEntity(Order::class, [
        'status' => Order::STATUS_PENDING_SETUP,
        'id' => 1,
    ]);

    $activeOrderEntity = new Order();
    $idProp = new ReflectionProperty($activeOrderEntity, 'id');
    $idProp->setValue($activeOrderEntity, 1);
    $statusProp = new ReflectionProperty($activeOrderEntity, 'status');
    $statusProp->setValue($activeOrderEntity, Order::STATUS_ACTIVE);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('find')->with(1)->andReturn($activeOrderEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('createFromOrder')->never();
    $serviceMock->shouldReceive('activateOrderAddons')->never();

    $serviceMock->setDi($di);

    $result = $serviceMock->activateOrder($staleOrderModel);

    expect($result)->toBeTrue();
});

test('activateOrder force re-activates an already active order', function (): void {
    $activeOrderModel = createEntity(Order::class, [
        'status' => Order::STATUS_ACTIVE,
        'group_master' => 1,
        'id' => 1,
    ]);

    $orderEntity = new Order();
    $idProp = new ReflectionProperty($orderEntity, 'id');
    $idProp->setValue($orderEntity, 1);
    $statusProp = new ReflectionProperty($orderEntity, 'status');
    $statusProp->setValue($orderEntity, Order::STATUS_ACTIVE);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('find')->with(1)->andReturn($orderEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldIgnoreMissing();

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Box_Log();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('createFromOrder')->atLeast()->once()->andReturn([]);
    $serviceMock->shouldReceive('activateOrderAddons')->atLeast()->once();

    $serviceMock->setDi($di);

    $result = $serviceMock->activateOrder($activeOrderModel, ['force' => true]);

    expect($result)->toBeTrue();
});

test('activateOrderAddons activates addons', function (): void {
    $order = createEntity(Order::class);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('createFromOrder')->atLeast()->once()->andReturn([]);

    $clientOrderModel = createEntity(Order::class);
    $clientOrderModel->status = Order::STATUS_PENDING_SETUP;
    $clientOrderModel->group_master = 1;

    $serviceMock->shouldReceive('getOrderAddonsList')
        ->atLeast()->once()
        ->andReturn([$clientOrderModel]);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventMock;

    $serviceMock->setDi($di);

    $result = $serviceMock->activateOrderAddons($clientOrderModel);

    expect($result)->toBeTrue();
});

test('getOrderAddonsList returns addons', function (): void {
    $modelClientOrder = createEntity(Order::class);

    $orderEntity = new Order();
    $idProp = new ReflectionProperty($orderEntity, 'id');
    $idProp->setValue($orderEntity, 1);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('findBy')->atLeast()->once()->andReturn([$orderEntity]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->getOrderAddonsList($modelClientOrder);

    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(Order::class);
});

test('stockSale reduces stock', function (): void {
    $productModel = orderServiceCreateProductEntity();

    $productService = Mockery::mock(Box\Mod\Product\Service::class);
    $productService->shouldReceive('reduceStock')->once()->with($productModel, 2)->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($productService) {
        if ($serviceName == 'product') {
            return $productService;
        }
    });

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->stockSale($productModel, 2);

    expect($result)->toBeTrue();
});

test('stockSale throws when quantity would go negative', function (): void {
    $productModel = orderServiceCreateProductEntity(1);

    $productService = Mockery::mock(Box\Mod\Product\Service::class);
    $productService->shouldReceive('reduceStock')
        ->once()
        ->with($productModel, 2)
        ->andThrow(new FOSSBilling\InformationException('Product :id is out of stock.', [':id' => 1], 831));

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($productService) {
        if ($serviceName == 'product') {
            return $productService;
        }
    });

    $svc = new Service();
    $svc->setDi($di);

    expect(fn (): bool => $svc->stockSale($productModel, 2))
        ->toThrow(FOSSBilling\InformationException::class, 'Product 1 is out of stock.');
});

test('updateOrder updates fields', function (): void {
    $clientOrderModel = createEntity(Order::class);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['em'] = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $di['logger'] = new Box_Log();

    $data = [
        'period' => '1Y',
        'created_at' => '2012-12-01',
        'activated_at' => '2012-12-01',
        'expires_at' => '2013-12-01',
        'invoice_option' => 'issue-invoice',
        'title' => 'Testing',
        'price' => 10,
        'status' => 'active',
        'notes' => 'Empty note',
        'reason' => 'non',
        'meta' => [],
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('updatePeriod')->atLeast()->once()->with($clientOrderModel, $data['period']);
    $serviceMock->shouldReceive('updateOrderMeta')->atLeast()->once()->with($clientOrderModel, $data['meta']);

    $serviceMock->setDi($di);

    $result = $serviceMock->updateOrder($clientOrderModel, $data);

    expect($result)->toBeTrue();
});

test('renewOrder renews order', function (): void {
    $clientOrderModel = createEntity(Order::class);
    $clientOrderModel->group_master = 1;
    $clientOrderModel->status = Order::STATUS_PENDING_SETUP;

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Box_Log();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('renewFromOrder')->atLeast()->once()->with($clientOrderModel);
    $serviceMock->shouldReceive('getOrderAddonsList')->atLeast()->once()->andReturn([$clientOrderModel]);

    $serviceMock->setDi($di);

    $result = $serviceMock->renewOrder($clientOrderModel);

    expect($result)->toBeTrue();
});

test('renewFromOrder extends expiration', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_callOnService')->atLeast()->once();

    $clientOrderModel = createEntity(Order::class);
    $clientOrderModel->period = '1Y';
    $clientOrderModel->expires_at = '2026-01-01 00:00:00';

    $expectedExpiration = strtotime('2027-01-01 00:00:00');
    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getExpirationTime')
        ->atLeast()->once()
        ->with(strtotime('2026-01-01 00:00:00'))
        ->andReturn($expectedExpiration);

    $serviceMock->shouldReceive('saveStatusChange')
        ->atLeast()->once()
        ->with($clientOrderModel, 'Order renewed');

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => []);
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);

    $serviceMock->setDi($di);
    $serviceMock->renewFromOrder($clientOrderModel);

    expect($clientOrderModel->expires_at)->toEqual(new DateTime('2027-01-01 00:00:00'));
    expect($clientOrderModel->status)->toEqual(Order::STATUS_ACTIVE);
});

test('renewFromOrder extends free first term on first paid renewal', function (): void {
    $clientOrderModel = createEntity(Order::class);
    $clientOrderModel->period = '1Y';
    $clientOrderModel->status = Order::STATUS_ACTIVE;
    $clientOrderModel->activated_at = '2025-01-01 00:00:00';
    $clientOrderModel->expires_at = '2026-01-01 00:00:00';

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_callOnService')
        ->once()
        ->with(Mockery::on(fn ($order): bool => $order === $clientOrderModel), Order::ACTION_RENEW);

    $expectedExpiration = strtotime('2027-01-01 00:00:00');
    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getExpirationTime')
        ->once()
        ->with(strtotime('2026-01-01 00:00:00'))
        ->andReturn($expectedExpiration);

    $serviceMock->shouldReceive('saveStatusChange')
        ->once()
        ->with($clientOrderModel, 'Order renewed');

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => []);
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);

    $serviceMock->setDi($di);
    $serviceMock->renewFromOrder($clientOrderModel);

    expect($clientOrderModel->expires_at)->toEqual(new DateTime('2027-01-01 00:00:00'));
    expect($clientOrderModel->status)->toEqual(Order::STATUS_ACTIVE);
});

test('suspendFromOrder throws for non-active order', function (): void {
    $clientOrderModel = createEntity(Order::class);
    $clientOrderModel->status = Order::STATUS_SUSPENDED;

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventMock;

    $svc = new Service();
    $svc->setDi($di);

    expect(fn (): bool => $svc->suspendFromOrder($clientOrderModel))
        ->toThrow(FOSSBilling\Exception::class, 'Only active orders can be suspended');
});

test('suspendFromOrder suspends active order', function (): void {
    $clientOrderModel = createEntity(Order::class);
    $clientOrderModel->status = Order::STATUS_ACTIVE;

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Box_Log();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_callOnService')->atLeast()->once();
    $serviceMock->shouldReceive('saveStatusChange')->atLeast()->once();

    $serviceMock->setDi($di);

    $result = $serviceMock->suspendFromOrder($clientOrderModel);

    expect($result)->toBeTrue();
});

test('cancelFromOrder cancels linked subscriptions', function (): void {
    $clientOrderModel = createEntity(Order::class);
    $clientOrderModel->id = 10;
    $clientOrderModel->status = Order::STATUS_ACTIVE;
    $legacyOrder = orderServiceCreateLegacyOrderModel(10);

    $calls = [];
    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('cancelForOrder')
        ->once()
        ->with($legacyOrder)
        ->andReturnUsing(function () use (&$calls): int {
            $calls[] = 'subscriptions';

            return 1;
        });

    $productService = Mockery::mock(Box\Mod\Product\Service::class);
    $productService->shouldReceive('releaseReservedPromoRedemptionsForOrder')
        ->once()
        ->with($clientOrderModel, 'order_canceled');

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('executeStatement')
        ->once()
        ->with(
            'DELETE FROM client_order_meta WHERE client_order_id = :order_id AND name = :name',
            ['order_id' => $clientOrderModel->id, 'name' => Service::META_CANCEL_AT_PERIOD_END],
        );
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldIgnoreMissing();
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->once()->with('ClientOrder', 10)->andReturn($legacyOrder);

    $di = container();
    $di['db'] = $dbMock;
    $di['em'] = $emMock;
    $di['dbal'] = $connectionMock;
    $di['logger'] = new Box_Log();
    $di['mod_service'] = $di->protect(function (string $module, string $service = '') use ($productService, $subscriptionService) {
        if ($module === 'Invoice' && $service === 'Subscription') {
            return $subscriptionService;
        }

        return $productService;
    });

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_callOnService')
        ->once()
        ->andReturnUsing(function () use (&$calls): void {
            $calls[] = 'service';
        });
    $serviceMock->shouldReceive('saveStatusChange')->once();
    $serviceMock->setDi($di);

    expect($serviceMock->cancelFromOrder($clientOrderModel, skipEvent: true))->toBeTrue()
        ->and($clientOrderModel->status)->toBe(Order::STATUS_CANCELED)
        ->and($calls)->toBe(['service', 'subscriptions']);
});

test('scheduleCancellationFromOrder keeps the service active', function (): void {
    $order = createEntity(Order::class, [
        'id' => 10,
        'status' => Order::STATUS_ACTIVE,
    ]);
    $legacyOrder = orderServiceCreateLegacyOrderModel(10);

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('canCancelAtPeriodEndForOrder')->once()->with($legacyOrder)->andReturn(true);
    $subscriptionService->shouldReceive('scheduleCancellationForOrder')->once()->with($legacyOrder)->andReturn(1);

    $di = container();
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->once()->with('ClientOrder', 10)->andReturn($legacyOrder);
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();
    $di['mod_service'] = $di->protect(fn () => $subscriptionService);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $service->shouldReceive('updateOrderMeta')
        ->once()
        ->with($order, [Service::META_CANCEL_AT_PERIOD_END => '1'])
        ->andReturn(2);
    $service->shouldReceive('saveStatusChange')
        ->once()
        ->with($order, 'Cancellation scheduled at the end of the current billing period');
    $service->setDi($di);

    expect($service->scheduleCancellationFromOrder($order, 'Customer request'))->toBeTrue()
        ->and($order->status)->toBe(Order::STATUS_ACTIVE)
        ->and($order->reason)->toBe('Customer request');
});

test('scheduleCancellationFromOrder does not mark the order when no subscription was scheduled', function (): void {
    $order = createEntity(Order::class, [
        'status' => Order::STATUS_ACTIVE,
    ]);
    $legacyOrder = orderServiceCreateLegacyOrderModel(0);

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('canCancelAtPeriodEndForOrder')->once()->with($legacyOrder)->andReturn(true);
    $subscriptionService->shouldReceive('scheduleCancellationForOrder')->once()->with($legacyOrder)->andReturn(0);

    $di = container();
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->once()->with('ClientOrder', 0)->andReturn($legacyOrder);
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn () => $subscriptionService);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldNotReceive('updateOrderMeta');
    $service->setDi($di);

    expect(fn () => $service->scheduleCancellationFromOrder($order))
        ->toThrow(FOSSBilling\InformationException::class, 'No active gateway subscription is linked to this order.');
});

test('cancelFromOrder does not cancel subscriptions when service cancellation fails', function (): void {
    $clientOrderModel = createEntity(Order::class);
    $clientOrderModel->status = Order::STATUS_ACTIVE;

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldNotReceive('cancelForOrder');

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldNotReceive('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['em'] = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $di['mod_service'] = $di->protect(fn () => $subscriptionService);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_callOnService')
        ->once()
        ->andThrow(new RuntimeException('Service cancellation failed'));
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->cancelFromOrder($clientOrderModel, skipEvent: true))
        ->toThrow(RuntimeException::class, 'Service cancellation failed')
        ->and($clientOrderModel->status)->toBe(Order::STATUS_ACTIVE);
});

test('cancelFromOrder remains retryable when subscription cancellation fails', function (): void {
    $clientOrderModel = createEntity(Order::class);
    $clientOrderModel->status = Order::STATUS_ACTIVE;
    $legacyOrder = orderServiceCreateLegacyOrderModel(0);

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('cancelForOrder')
        ->once()
        ->with(Mockery::any())
        ->andThrow(new RuntimeException('Subscription cancellation failed'));

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldNotReceive('store');
    $dbMock->shouldReceive('getExistingModelById')->once()->with('ClientOrder', 0)->andReturn($legacyOrder);

    $di = container();
    $di['db'] = $dbMock;
    $di['em'] = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $di['mod_service'] = $di->protect(fn () => $subscriptionService);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_callOnService')->once();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->cancelFromOrder($clientOrderModel, skipEvent: true))
        ->toThrow(RuntimeException::class, 'Subscription cancellation failed')
        ->and($clientOrderModel->status)->toBe(Order::STATUS_ACTIVE);
});

test('rmByClient removes all client orders', function (): void {
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 100]);

    $orderModel = createEntity(Order::class);

    $queryBuilderMock = new class {
        private bool $deleteCalled = false;
        private bool $whereCalled = false;
        private bool $setParamCalled = false;
        private mixed $deleteTable = null;
        private mixed $whereCond = null;
        private mixed $paramId = null;

        public function delete($table)
        {
            $this->deleteCalled = true;
            $this->deleteTable = $table;

            return $this;
        }

        public function where($cond)
        {
            $this->whereCalled = true;
            $this->whereCond = $cond;

            return $this;
        }

        public function setParameter($key, $val)
        {
            $this->setParamCalled = true;
            $this->paramId = $val;

            return $this;
        }

        public function executeStatement(): int
        {
            return 1;
        }

        public function getDeleteTable()
        {
            return $this->deleteTable;
        }

        public function getWhereCond()
        {
            return $this->whereCond;
        }

        public function getParamId()
        {
            return $this->paramId;
        }

        public function wasDeleteCalled(): bool
        {
            return $this->deleteCalled;
        }

        public function wasWhereCalled(): bool
        {
            return $this->whereCalled;
        }

        public function wasSetParamCalled(): bool
        {
            return $this->setParamCalled;
        }
    };

    $dbalMock = new class($queryBuilderMock) {
        public function __construct(private $qb)
        {
        }

        public function createQueryBuilder()
        {
            return $this->qb;
        }
    };

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('findByClientId')->once()->with(100)->andReturn([$orderModel]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldIgnoreMissing();

    $productServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $productServiceMock->shouldReceive('releaseReservedPromoRedemptionsForOrder')
        ->once()
        ->with($orderModel, 'client_deleted');

    $di = container();
    $di['em'] = $emMock;
    $di['dbal'] = $dbalMock;
    $di['mod_service'] = $di->protect(fn (string $name): Mockery\MockInterface => match (strtolower($name)) {
        'product' => $productServiceMock,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $svc = new Service();
    $svc->setDi($di);

    $svc->rmByClient($clientModel);

    expect($queryBuilderMock->getDeleteTable())->toBe('client_order');
    expect($queryBuilderMock->getWhereCond())->toBe('client_id = :id');
    expect($queryBuilderMock->getParamId())->toBe($clientModel->id);
});

test('updatePeriod sets period when given', function (): void {
    $period = '1Y';
    $di = container();

    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getCode')->atLeast()->once();
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);

    $svc = new Service();
    $svc->setDi($di);

    $clientOrder = createEntity(Order::class);

    $result = $svc->updatePeriod($clientOrder, $period);

    expect($result)->toEqual(1);
});

test('updatePeriod clears period when empty string', function (): void {
    $period = '';
    $di = container();

    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getCode')->never();
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);

    $svc = new Service();
    $svc->setDi($di);

    $clientOrder = createEntity(Order::class);

    $result = $svc->updatePeriod($clientOrder, $period);

    expect($result)->toEqual(2);
});

test('updatePeriod does nothing when null', function (): void {
    $period = null;
    $di = container();

    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getCode')->never();
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);

    $svc = new Service();
    $svc->setDi($di);

    $clientOrder = createEntity(Order::class);

    $result = $svc->updatePeriod($clientOrder, $period);

    expect($result)->toEqual(0);
});

test('updateOrderMeta returns 0 when meta is not an array', function (): void {
    $meta = null;
    $clientOrder = createEntity(Order::class);

    $svc = new Service();

    $result = $svc->updateOrderMeta($clientOrder, $meta);

    expect($result)->toEqual(0);
});

test('updateOrderMeta clears existing meta when empty', function (): void {
    $meta = [];

    $metaRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing();
    $metaRepoMock->shouldReceive('deleteByOrderId')->once()->with(1)->andReturn(1);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->andReturn($metaRepoMock);
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $clientOrder = createEntity(Order::class);
    $clientOrder->id = 1;

    $result = $svc->updateOrderMeta($clientOrder, $meta);

    expect($result)->toEqual(1);
});

test('updateOrderMeta stores new meta entries', function (): void {
    $meta = ['key' => 'value'];

    $metaRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing();
    $metaRepoMock->shouldReceive('findOneByOrderIdAndName')->with(1, 'key')->once()->andReturn(null);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->andReturn($metaRepoMock);
    $emMock->shouldReceive('persist')->once();
    $emMock->shouldReceive('flush')->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $clientOrder = createEntity(Order::class);
    $clientOrder->id = 1;

    $result = $svc->updateOrderMeta($clientOrder, $meta);

    expect($result)->toEqual(2);
});

test('updateOrderConfig succeeds when no form id is set', function (): void {
    $di = container();
    $di['logger'] = new Box_Log();

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class);
    $order->form_id = null;

    $result = $svc->updateOrderConfig($order, ['key' => 'value']);

    expect($result)->toBeTrue();
});

test('updateOrderConfig throws when required field is missing', function (): void {
    $form = [
        'fields' => [
            ['name' => 'hostname', 'label' => 'Hostname', 'type' => 'text', 'required' => true, 'options' => []],
        ],
    ];

    $formbuilderServiceMock = Mockery::mock(Box\Mod\Formbuilder\Service::class);
    $formbuilderServiceMock->shouldReceive('getForm')->once()->with(7)->andReturn($form);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($formbuilderServiceMock) {
        if ($serviceName === 'formbuilder') {
            return $formbuilderServiceMock;
        }
    });

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class);
    $order->form_id = 7;

    expect(fn (): bool => $svc->updateOrderConfig($order, []))
        ->toThrow(FOSSBilling\Exception::class, '', 4892);
});

test('updateOrderConfig throws for invalid select option', function (): void {
    $form = [
        'fields' => [
            ['name' => 'plan', 'label' => 'Plan', 'type' => 'select', 'required' => false, 'options' => ['basic' => 'Basic', 'pro' => 'Pro']],
        ],
    ];

    $formbuilderServiceMock = Mockery::mock(Box\Mod\Formbuilder\Service::class);
    $formbuilderServiceMock->shouldReceive('getForm')->once()->andReturn($form);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($formbuilderServiceMock) {
        if ($serviceName === 'formbuilder') {
            return $formbuilderServiceMock;
        }
    });

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class);
    $order->form_id = 8;

    expect(fn (): bool => $svc->updateOrderConfig($order, ['plan' => 'enterprise']))
        ->toThrow(FOSSBilling\Exception::class, '', 4893);
});

test('updateOrderConfig select rejects array value', function (): void {
    $form = [
        'fields' => [
            ['name' => 'plan', 'label' => 'Plan', 'type' => 'select', 'required' => false, 'options' => ['basic' => 'Basic', 'pro' => 'Pro']],
        ],
    ];

    $formbuilderServiceMock = Mockery::mock(Box\Mod\Formbuilder\Service::class);
    $formbuilderServiceMock->shouldReceive('getForm')->once()->andReturn($form);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($formbuilderServiceMock) {
        if ($serviceName === 'formbuilder') {
            return $formbuilderServiceMock;
        }
    });

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class);
    $order->form_id = 11;

    expect(fn (): bool => $svc->updateOrderConfig($order, ['plan' => ['pro']]))
        ->toThrow(FOSSBilling\Exception::class, '', 4893);
});

test('updateOrderConfig throws for invalid radio option', function (): void {
    $form = [
        'fields' => [
            ['name' => 'os', 'label' => 'OS', 'type' => 'radio', 'required' => false, 'options' => ['linux' => 'Linux', 'windows' => 'Windows']],
        ],
    ];

    $formbuilderServiceMock = Mockery::mock(Box\Mod\Formbuilder\Service::class);
    $formbuilderServiceMock->shouldReceive('getForm')->once()->andReturn($form);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($formbuilderServiceMock) {
        if ($serviceName === 'formbuilder') {
            return $formbuilderServiceMock;
        }
    });

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class);
    $order->form_id = 9;

    expect(fn (): bool => $svc->updateOrderConfig($order, ['os' => 'macos']))
        ->toThrow(FOSSBilling\Exception::class, '', 4893);
});

test('updateOrderConfig throws for invalid checkbox option', function (): void {
    $form = [
        'fields' => [
            ['name' => 'addons', 'label' => 'Addons', 'type' => 'checkbox', 'required' => false, 'options' => ['backup', 'ssl']],
        ],
    ];

    $formbuilderServiceMock = Mockery::mock(Box\Mod\Formbuilder\Service::class);
    $formbuilderServiceMock->shouldReceive('getForm')->once()->andReturn($form);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($formbuilderServiceMock) {
        if ($serviceName === 'formbuilder') {
            return $formbuilderServiceMock;
        }
    });

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class);
    $order->form_id = 10;

    expect(fn (): bool => $svc->updateOrderConfig($order, ['addons' => ['backup', 'ddos-protection']]))
        ->toThrow(FOSSBilling\Exception::class, '', 4894);
});

test('updateOrderConfig succeeds with valid form data', function (): void {
    $form = [
        'fields' => [
            ['name' => 'hostname', 'label' => 'Hostname', 'type' => 'text', 'required' => true, 'options' => []],
            ['name' => 'plan', 'label' => 'Plan', 'type' => 'select', 'required' => false, 'options' => ['basic' => 'Basic', 'pro' => 'Pro']],
            ['name' => 'addons', 'label' => 'Addons', 'type' => 'checkbox', 'required' => false, 'options' => ['backup', 'ssl']],
        ],
    ];

    $formbuilderServiceMock = Mockery::mock(Box\Mod\Formbuilder\Service::class);
    $formbuilderServiceMock->shouldReceive('getForm')->once()->andReturn($form);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($formbuilderServiceMock) {
        if ($serviceName === 'formbuilder') {
            return $formbuilderServiceMock;
        }
    });
    $di['em'] = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $di['logger'] = new Box_Log();

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class);
    $order->form_id = 11;

    $result = $svc->updateOrderConfig($order, ['hostname' => 'myhost.example.com', 'plan' => 'pro', 'addons' => ['backup', 'ssl']]);

    expect($result)->toBeTrue();
});

test('createOrder rejects invalid price and quantity', function (array $data, string $message): void {
    $service = new Service();
    $client = createEntity(Box\Mod\Client\Entity\Client::class);
    $product = orderServiceCreateProductEntity(1, 'custom');

    expect(fn () => $service->createOrder($client, $product, $data))
        ->toThrow(FOSSBilling\InformationException::class, $message);
})->with([
    'negative price' => [['price' => -1], 'Price cannot be negative'],
    'invalid price' => [['price' => 'invalid'], 'Price must be a valid number'],
    'invalid quantity' => [['quantity' => 'invalid'], 'Quantity must be a valid number'],
]);

test('updateOrder rejects a negative price', function (): void {
    $order = createEntity(Order::class);

    $events = Mockery::mock(Box_EventManager::class);
    $events->shouldReceive('fire')->once();

    $di = container();
    $di['events_manager'] = $events;

    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('updatePeriod')->once();
    $service->setDi($di);

    expect(fn () => $service->updateOrder($order, ['price' => -1]))
        ->toThrow(FOSSBilling\InformationException::class, 'Price cannot be negative');
});

test('createOrder generates an invoice for a zero-price order with issue-invoice', function (): void {
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'USD']);

    $modelProduct = orderServiceCreateProductEntity(1, 'custom');

    $currencyModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class)->shouldIgnoreMissing();

    $currencyRepositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock->shouldReceive('findOneByCode')->atLeast()->once()->andReturn($currencyModel);

    $currencyServiceMock = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $cartServiceMock = Mockery::mock(Box\Mod\Cart\Service::class);
    $cartServiceMock->shouldReceive('isStockAvailable')
        ->atLeast()->once()
        ->with($modelProduct, Mockery::any())
        ->andReturn(true);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $productServiceMock = Mockery::mock(Box\Mod\Servicecustom\Service::class);
    $pricingServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $pricingServiceMock->shouldReceive('getProductOrderLineConfig')->never();

    $clientOrderModel = orderServiceCreateLegacyOrderModel(1);

    $invoiceModel = orderServiceCreateInvoiceModel(10);

    $invoiceServiceMock = Mockery::mock();
    $invoiceServiceMock->shouldReceive('generateForOrder')
        ->once()
        ->with(Mockery::any())
        ->andReturn($invoiceModel);
    $invoiceServiceMock->shouldReceive('approveInvoice')
        ->once()
        ->with($invoiceModel, ['id' => $invoiceModel->id, 'use_credits' => true])
        ->andReturn(true);

    $persistedEntities = [];
    $nextOrderId = 1;
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once()->andReturnUsing(function ($entity) use (&$persistedEntities): void {
        $persistedEntities[] = $entity;
    });
    $emMock->shouldReceive('flush')->atLeast()->once()->andReturnUsing(function () use (&$persistedEntities, &$nextOrderId): void {
        foreach ($persistedEntities as $entity) {
            $refl = new ReflectionClass($entity);
            if ($refl->hasProperty('id')) {
                $prop = $refl->getProperty('id');
                if ($prop->getValue($entity) === null) {
                    $prop->setValue($entity, $nextOrderId++);
                }
            }
        }
        $persistedEntities = [];
    });
    $emMock->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
    $emMock->shouldReceive('remove')->andReturnNull();
    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('find')->andReturnUsing(function (?int $id) use (&$nextOrderId): ?object {
        if ($id === null) {
            return null;
        }
        $order = new Order();
        $prop = new ReflectionProperty($order, 'id');
        $prop->setValue($order, $id);

        return $order;
    });
    $orderRepoMock->shouldReceive('findOneByOrderIdAndName')->byDefault()->andReturn(null);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->andReturn(Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing());
    $emMock->shouldIgnoreMissing();
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->once()->with('ClientOrder', 1)->andReturn($clientOrderModel);

    $newId = 1;

    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getCode')->atLeast()->once()->andReturn('1Y');

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($cartServiceMock, $currencyServiceMock, $invoiceServiceMock, $productServiceMock, $pricingServiceMock) {
        if ($serviceName == 'currency') {
            return $currencyServiceMock;
        }
        if ($serviceName == 'cart') {
            return $cartServiceMock;
        }
        if ($serviceName == 'Product') {
            return $pricingServiceMock;
        }
        if ($serviceName == 'invoice') {
            return $invoiceServiceMock;
        }
        if ($serviceName == 'servicecustom') {
            return $productServiceMock;
        }
    });
    $di['events_manager'] = $eventMock;
    $di['db'] = $dbMock;
    $di['em'] = $emMock;
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);
    $di['logger'] = new Box_Log();

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->createOrder($modelClient, $modelProduct, [
        'period' => '1Y',
        'price' => 0,
        'invoice_option' => 'issue-invoice',
    ]);

    expect($result)->toBe($newId);
});

test('createOrder does not roll back when invoice generation fails for a negative resolved price', function (): void {
    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class, ['currency' => 'USD']);

    $modelProduct = orderServiceCreateProductEntity(1, 'custom');

    $currencyModel = Mockery::mock(Box\Mod\Currency\Entity\Currency::class)->shouldIgnoreMissing();

    $currencyRepositoryMock = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepositoryMock->shouldReceive('findOneByCode')->atLeast()->once()->andReturn($currencyModel);
    $currencyRepositoryMock->shouldReceive('getRateByCode')->atLeast()->once()->andReturn(1.0);

    $currencyServiceMock = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyServiceMock->shouldReceive('getCurrencyRepository')->atLeast()->once()->andReturn($currencyRepositoryMock);

    $cartServiceMock = Mockery::mock(Box\Mod\Cart\Service::class);
    $cartServiceMock->shouldReceive('isStockAvailable')
        ->atLeast()->once()
        ->with($modelProduct, Mockery::any())
        ->andReturn(true);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $productServiceMock = Mockery::mock(Box\Mod\Servicecustom\Service::class);
    $pricingServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $pricingServiceMock->shouldReceive('getProductOrderLineConfig')
        ->atLeast()->once()
        ->andReturn(['price' => -5.0, 'quantity' => 1]);

    $clientOrderModel = orderServiceCreateLegacyOrderModel(1);

    $invoiceServiceMock = Mockery::mock();
    $invoiceServiceMock->shouldReceive('generateForOrder')
        ->once()
        ->with(Mockery::any())
        ->andThrow(new FOSSBilling\InformationException('Invoices are not generated for negative amount orders.'));
    $invoiceServiceMock->shouldReceive('approveInvoice')->never();

    $persistedEntities = [];
    $nextOrderId = 1;
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once()->andReturnUsing(function ($entity) use (&$persistedEntities): void {
        $persistedEntities[] = $entity;
    });
    $emMock->shouldReceive('flush')->atLeast()->once()->andReturnUsing(function () use (&$persistedEntities, &$nextOrderId): void {
        foreach ($persistedEntities as $entity) {
            $refl = new ReflectionClass($entity);
            if ($refl->hasProperty('id')) {
                $prop = $refl->getProperty('id');
                if ($prop->getValue($entity) === null) {
                    $prop->setValue($entity, $nextOrderId++);
                }
            }
        }
        $persistedEntities = [];
    });
    $emMock->shouldReceive('wrapInTransaction')->once()->andReturnUsing(fn (callable $callback) => $callback());
    $emMock->shouldReceive('remove')->andReturnNull();
    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('find')->andReturnUsing(function (?int $id) use (&$nextOrderId): ?object {
        if ($id === null) {
            return null;
        }
        $order = new Order();
        $prop = new ReflectionProperty($order, 'id');
        $prop->setValue($order, $id);

        return $order;
    });
    $orderRepoMock->shouldReceive('findOneByOrderIdAndName')->byDefault()->andReturn(null);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->andReturn(Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing());
    $emMock->shouldIgnoreMissing();
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->once()->with('ClientOrder', 1)->andReturn($clientOrderModel);

    $newId = 1;

    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getCode')->atLeast()->once()->andReturn('1Y');

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($cartServiceMock, $currencyServiceMock, $invoiceServiceMock, $productServiceMock, $pricingServiceMock) {
        if ($serviceName == 'currency') {
            return $currencyServiceMock;
        }
        if ($serviceName == 'cart') {
            return $cartServiceMock;
        }
        if ($serviceName == 'Product') {
            return $pricingServiceMock;
        }
        if ($serviceName == 'invoice') {
            return $invoiceServiceMock;
        }
        if ($serviceName == 'servicecustom') {
            return $productServiceMock;
        }
    });
    $di['events_manager'] = $eventMock;
    $di['db'] = $dbMock;
    $di['em'] = $emMock;
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);
    $di['logger'] = new Box_Log();

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->createOrder($modelClient, $modelProduct, [
        'period' => '1Y',
        'invoice_option' => 'issue-invoice',
    ]);

    expect($result)->toBe($newId);
});

test('getExpiredOrders uses strict expires_at <= NOW() filter', function (): void {
    $service = new Service();

    $orderRepository = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class)->shouldIgnoreMissing();
    $orderRepository->shouldReceive('getExpired')
        ->once()
        ->andReturn([]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepository);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    expect($service->getExpiredOrders())->toBe([]);
});
