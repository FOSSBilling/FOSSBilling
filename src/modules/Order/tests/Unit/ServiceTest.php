<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Repository\OrderRepository;
use Box\Mod\Order\Service;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicecustom\Entity\ServiceCustom;

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

function orderServiceCreateInvoiceModel(int $id): Invoice
{
    $invoice = createEntity(Invoice::class);

    $invoice->id = $id;

    return $invoice;
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

test('onAfterAdminOrderRenew fires template without an admin session', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->once()->andReturn($params);

    $order = createEntity(Order::class, ['id' => 1]);
    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->once()->with([
        'to_client' => 1,
        'code' => 'mod_servicedomain_renewed',
        'service' => [],
        'order' => $orderArr,
    ])->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->once()->with($order, null)->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->once()->with($order, true, null)->andReturn($orderArr);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->once()->with(1)->andReturn($order);
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->once()->andReturn($di);

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

test('onAfterAdminOrderSuspend fires template without an admin session', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->once()->andReturn($params);

    $order = createEntity(Order::class, ['id' => 1]);
    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->once()->with([
        'to_client' => 1,
        'code' => 'mod_servicedomain_suspended',
        'service' => [],
        'order' => $orderArr,
    ])->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->once()->with($order, null)->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->once()->with($order, true, null)->andReturn($orderArr);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->once()->with(1)->andReturn($order);
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->once()->andReturn($di);

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

test('onAfterAdminOrderUnsuspend fires template without an admin session', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->once()->andReturn($params);

    $order = createEntity(Order::class, ['id' => 1]);
    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->once()->with([
        'to_client' => 1,
        'code' => 'mod_servicedomain_unsuspended',
        'service' => [],
        'order' => $orderArr,
    ])->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->once()->with($order, null)->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->once()->with($order, true, null)->andReturn($orderArr);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->once()->with(1)->andReturn($order);
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->once()->andReturn($di);

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

test('onAfterAdminOrderCancel fires template without an admin session', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->once()->andReturn($params);

    $order = createEntity(Order::class, ['id' => 1]);
    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->once()->with([
        'to_client' => 1,
        'code' => 'mod_servicedomain_canceled',
        'order' => $orderArr,
    ])->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('toApiArray')->once()->with($order, true, null)->andReturn($orderArr);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->once()->with(1)->andReturn($order);
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->once()->andReturn($di);

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

test('onAfterAdminOrderUncancel fires template without an admin session', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->once()->andReturn($params);

    $order = createEntity(Order::class, ['id' => 1]);
    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->once()->with([
        'to_client' => 1,
        'code' => 'mod_servicedomain_renewed',
        'order' => $orderArr,
        'service' => [],
    ])->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderServiceData')->once()->with($order, null)->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')->once()->with($order, true, null)->andReturn($orderArr);

    $di = container();
    $di['em']->getRepository(Order::class)->shouldReceive('find')->once()->with(1)->andReturn($order);
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'order') {
            return $serviceMock;
        }
    });

    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')->once()->andReturn($di);

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
    $serviceEntity = new ServiceCustom();

    $serviceRepo = Mockery::mock(Doctrine\ORM\EntityRepository::class);
    $serviceRepo->shouldReceive('find')->once()->with(1)->andReturn($serviceEntity);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')
        ->once()
        ->with(ServiceCustom::class)
        ->andReturn($serviceRepo);

    $di = container();
    $di['em'] = $em;

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class, [
        'service_id' => 1,
        'service_type' => Box\Mod\Product\Service::CUSTOM,
    ]);

    $result = $svc->getOrderService($order);

    expect($result)->toBeInstanceOf(ServiceCustom::class);
});

test('getOrderService returns non-core service', function (): void {
    $serviceData = ['id' => 1, 'product_id' => 5];

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAssociative')->once()->with('SELECT * FROM service_external WHERE id = :id', ['id' => 1])->andReturn($serviceData);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);

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
    $di = container();
    $di['em']->shouldReceive('getConnection')->never();

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class);

    $result = $svc->getOrderService($order);

    expect($result)->toBeNull();
});

test('_callOnService dispatches to a third-party module with the DBAL row array', function (): void {
    $serviceData = ['id' => 1, 'product_id' => 5];

    $order = createEntity(Order::class, [
        'id' => 10,
        'service_id' => 1,
        'service_type' => 'external',
    ]);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAssociative')
        ->once()
        ->with('SELECT * FROM service_external WHERE id = :id', ['id' => 1])
        ->andReturn($serviceData);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);

    $module = new class {
        public array $calls = [];

        public function activate($order, $service)
        {
            $this->calls[] = [$order, $service];

            return 'activated';
        }
    };

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(function (string $name) use ($module) {
        return match ($name) {
            'serviceexternal' => $module,
            default => throw new LogicException('Unexpected service: ' . $name),
        };
    });

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->setDi($di);

    $result = $serviceMock->_callOnService($order, Order::ACTION_ACTIVATE);

    expect($result)->toBe('activated')
        ->and($module->calls)->toBe([[$order, $serviceData]]);
});

test('_callOnService dispatches to a third-party module with null when no service exists', function (): void {
    $order = createEntity(Order::class, [
        'id' => 10,
        'service_type' => 'external',
    ]);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->never();

    $module = new class {
        public array $calls = [];

        public function activate($order, $service)
        {
            $this->calls[] = [$order, $service];

            return true;
        }
    };

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(function (string $name) use ($module) {
        return match ($name) {
            'serviceexternal' => $module,
            default => throw new LogicException('Unexpected service: ' . $name),
        };
    });

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->setDi($di);

    $result = $serviceMock->_callOnService($order, Order::ACTION_ACTIVATE);

    expect($result)->toBeTrue()
        ->and($module->calls)->toBe([[$order, null]]);
});

test('_callOnService dispatches to a third-party module with false when the service row is stale', function (): void {
    $order = createEntity(Order::class, [
        'id' => 10,
        'service_id' => 1,
        'service_type' => 'external',
    ]);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAssociative')
        ->once()
        ->with('SELECT * FROM service_external WHERE id = :id', ['id' => 1])
        ->andReturn(false);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);

    $module = new class {
        public array $calls = [];

        public function activate($order, $service)
        {
            $this->calls[] = [$order, $service];

            return true;
        }
    };

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(function (string $name) use ($module) {
        return match ($name) {
            'serviceexternal' => $module,
            default => throw new LogicException('Unexpected service: ' . $name),
        };
    });

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->setDi($di);

    $result = $serviceMock->_callOnService($order, Order::ACTION_ACTIVATE);

    expect($result)->toBeTrue()
        ->and($module->calls)->toBe([[$order, false]]);
});

test('getOrderServiceData returns null for a third-party service type', function (): void {
    $order = createEntity(Order::class, [
        'id' => 10,
        'service_id' => 1,
        'service_type' => 'external',
    ]);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAssociative')
        ->once()
        ->with('SELECT * FROM service_external WHERE id = :id', ['id' => 1])
        ->andReturn(['id' => 1]);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);

    $di = container();
    $logger = $di['logger'];
    $di['em'] = $em;

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->getOrderServiceData($order);

    expect($result)->toBeNull()
        ->and($logger->calls)->toContain(['method' => 'info', 'params' => ['Order #10 has no active service.', []]]);
});

test('getOrderServiceData returns module data for a built-in service type', function (): void {
    $order = createEntity(Order::class, [
        'id' => 10,
        'service_id' => 1,
        'service_type' => Box\Mod\Product\Service::CUSTOM,
    ]);

    $service = createEntity(ServiceCustom::class, ['id' => 1]);

    $serviceRepo = Mockery::mock(Doctrine\ORM\EntityRepository::class);
    $serviceRepo->shouldReceive('find')->once()->with(1)->andReturn($service);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->once()->with(ServiceCustom::class)->andReturn($serviceRepo);

    $module = new class {
        public array $calls = [];

        public function toApiArray($service, $deep, $identity)
        {
            $this->calls[] = [$service, $deep, $identity];

            return ['username' => 'adam'];
        }
    };

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(function (string $name) use ($module) {
        return match ($name) {
            'servicecustom' => $module,
            default => throw new LogicException('Unexpected service: ' . $name),
        };
    });

    $svc = new Service();
    $svc->setDi($di);

    $identity = new stdClass();
    $result = $svc->getOrderServiceData($order, $identity);

    expect($result)->toBe(['username' => 'adam'])
        ->and($module->calls)->toBe([[$service, true, $identity]]);
});

test('getServiceOrder returns order', function (): void {
    $orderEntity = new Order();
    $idProp = new ReflectionProperty($orderEntity, 'id');
    $idProp->setValue($orderEntity, 1);

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')->once()->with([
        'serviceType' => Box\Mod\Product\Service::CUSTOM,
        'serviceId' => 1,
    ])->andReturn($orderEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->once()->with(Order::class)->andReturn($orderRepoMock);

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $service = createEntity(ServiceCustom::class, [
        'id' => 1,
    ]);

    $result = $svc->getServiceOrder($service);

    expect($result)->toBeInstanceOf(Order::class);
});

test('finds order for client by id', function (): void {
    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 5]);
    $entityOrder = createEntity(Order::class, ['id' => 10, 'client_id' => 5]);

    $orderRepository = Mockery::mock(OrderRepository::class);
    $orderRepository->shouldReceive('findForClientById')->twice()->with(5, 10)->andReturn($entityOrder);

    $entityManager = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $entityManager->shouldReceive('getRepository')->once()->with(Order::class)->andReturn($orderRepository);

    $di = container();
    $di['em'] = $entityManager;

    $service = new Service();
    $service->setDi($di);

    expect($service->findForClientById($client, 10))->toBe($entityOrder)
        ->and($service->findForClientById($client, 10))->toBe($entityOrder);
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
    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
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
    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
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

test('saveStatusChange persists status with order details', function (): void {
    $persisted = [];
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->once()->andReturnUsing(function ($entity) use (&$persisted): void {
        $persisted[] = $entity;
    });
    $emMock->shouldReceive('flush')->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class, ['id' => 7, 'status' => Order::STATUS_ACTIVE]);

    $svc->saveStatusChange($order, 'notes here');

    expect($persisted)->toHaveCount(1);
    $status = $persisted[0];
    expect($status)->toBeInstanceOf(Box\Mod\Order\Entity\OrderStatus::class);
    expect($status->getClientOrderId())->toBe(7);
    expect($status->getStatus())->toBe(Order::STATUS_ACTIVE);
    expect($status->getNotes())->toBe('notes here');
});

test('orderStatusAdd records status history', function (): void {
    $persisted = [];
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->once()->andReturnUsing(function ($entity) use (&$persisted): void {
        $persisted[] = $entity;
        if ($entity instanceof Box\Mod\Order\Entity\OrderStatus) {
            $entity->setId(7);
        }
    });
    $emMock->shouldReceive('flush')->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class, ['id' => 7]);

    $result = $svc->orderStatusAdd($order, Order::STATUS_ACTIVE, 'notes here');

    expect($result)->toBeTrue();
    expect($persisted)->toHaveCount(1);
    $status = $persisted[0];
    expect($status)->toBeInstanceOf(Box\Mod\Order\Entity\OrderStatus::class);
    expect($status->getClientOrderId())->toBe(7);
    expect($status->getStatus())->toBe(Order::STATUS_ACTIVE);
    expect($status->getNotes())->toBe('notes here');
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
        'unpaid_invoice_status' => Invoice::STATUS_UNPAID,
        'pending_item_type' => Box\Mod\Invoice\Entity\InvoiceItem::TYPE_ORDER,
        'pending_item_task' => Box\Mod\Invoice\Entity\InvoiceItem::TASK_RENEW,
        'pending_item_status' => Box\Mod\Invoice\Entity\InvoiceItem::STATUS_EXECUTED,
        'pending_invoice_status' => Invoice::STATUS_PAID,
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

    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('findOneByGroupIdAndServiceType')->atLeast()->once()->andReturn($orderEntity);

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

    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('findOneByGroupIdAndServiceType')->atLeast()->once()->andReturn(null);

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
    $model->setProductId(1);

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $supportService = Mockery::mock(Box\Mod\Support\Service::class);
    $supportTicketRepo = Mockery::mock(Box\Mod\Support\Repository\SupportTicketRepository::class);
    $supportTicketRepo->shouldReceive('countActiveTicketsForOrder')->atLeast()->once()->andReturn(1);
    $supportService->shouldReceive('getSupportTicketRepository')->atLeast()->once()->andReturn($supportTicketRepo);

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
    $productService->shouldReceive('getProductPluginById')->once()->with(1)->andReturn(null);
    $productRepository = Mockery::mock(Box\Mod\Product\Repository\ProductRepository::class);
    $productRepository->shouldReceive('find')->once()->with(1)->andReturn(null);
    $productService->shouldReceive('getProductRepository')->once()->andReturn($productRepository);

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
    expect($result['product_suspension_grace_days'])->toBeNull();
    expect($result)->toHaveKey('client');
});

test('toApiArray reads meta through the repository', function (): void {
    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $supportService = Mockery::mock(Box\Mod\Support\Service::class);
    $supportTicketRepo = Mockery::mock(Box\Mod\Support\Repository\SupportTicketRepository::class);
    $supportTicketRepo->shouldReceive('countActiveTicketsForOrder')->atLeast()->once()->andReturn(1);
    $supportService->shouldReceive('getSupportTicketRepository')->atLeast()->once()->andReturn($supportTicketRepo);

    $clientEntity = new Box\Mod\Client\Entity\Client();

    $clientRepoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepoMock->shouldReceive('find')->with(1)->atLeast()->once()->andReturn($clientEntity);

    $orderMetaRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class);
    $orderMetaRepoMock->shouldReceive('getPairsForOrder')->with(7)->atLeast()->once()->andReturn(['key' => 'value']);
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->atLeast()->once()->andReturn($orderMetaRepoMock);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->atLeast()->once()->andReturn($clientRepoMock);
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($clientService, $supportService) {
        if ($serviceName == 'client') {
            return $clientService;
        }
        if ($serviceName == 'support') {
            return $supportService;
        }
    });
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class, [
        'id' => 7,
        'config' => '{}',
        'price' => 10,
        'quantity' => 1,
        'client_id' => 1,
    ]);

    $result = $svc->toApiArray($order, false);

    expect($result['meta'])->toBe(['key' => 'value']);
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
    $pricingServiceMock->shouldReceive('reserveStockForOrder')->once();

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
    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
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
    $pricingServiceMock->shouldReceive('reserveStockForOrder')->once();

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
    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
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
    $pricingServiceMock->shouldReceive('reserveStockForOrder')->once();

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
    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
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
    $pricingServiceMock->shouldReceive('reserveStockForOrder')->once();

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
    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
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

    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('findMasterByGroupAndClient')->atLeast()->once()->andReturn($orderEntity);

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

test('createFromOrder activates the order after successful provisioning', function (): void {
    $order = createEntity(Order::class, [
        'id' => 1,
        'period' => '1Y',
        'productId' => 7,
        'quantity' => 2,
        'serviceType' => 'hosting',
    ]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn(new stdClass());
    $serviceMock->shouldReceive('_callOnService')
        ->once()
        ->with($order, Order::ACTION_ACTIVATE)
        ->andReturn(['username' => 'created']);
    $serviceMock->shouldReceive('saveStatusChange')->once()->with($order, 'Order activated');

    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getExpirationTime')->once()->andReturn(strtotime('2027-01-01 00:00:00'));

    // Stock is reserved atomically at order-creation time (see
    // Product\Service::reserveStockForOrder()), not here at activation, so createFromOrder()
    // must not touch the product service at all.
    $di = container();
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);
    $di['mod_service'] = $di->protect(function (): never {
        throw new LogicException('createFromOrder() must not reach into any module service for stock handling');
    });

    $serviceMock->setDi($di);

    $result = $serviceMock->createFromOrder($order);

    expect($result)->toBe(['username' => 'created'])
        ->and($order->getStatus())->toBe(Order::STATUS_ACTIVE);
});

test('createFromOrder marks the order failed_setup when provisioning succeeds but activation bookkeeping fails', function (): void {
    // Regression test: the remote account is created successfully by
    // _callOnService(), but computing the new expiry date afterwards throws.
    // The order must be recorded as failed_setup instead of being left in
    // pending_setup - otherwise a retry would call _callOnService() again
    // against a service that already exists on the remote server.
    $order = createEntity(Order::class, [
        'id' => 1,
        'period' => '1Y',
        'serviceType' => 'hosting',
    ]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn(new stdClass());
    $serviceMock->shouldReceive('_callOnService')
        ->once()
        ->with($order, Order::ACTION_ACTIVATE)
        ->andReturn(['username' => 'created-before-the-failure']);
    $serviceMock->shouldReceive('saveStatusChange')
        ->once()
        ->with($order, 'Simulated post-provisioning failure');

    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('find')->with(1)->andReturn($order);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldReceive('persist')->once()->with($order);
    $emMock->shouldReceive('flush')->once();

    $di = container();
    $di['em'] = $emMock;
    $di['period'] = $di->protect(function (): never {
        throw new FOSSBilling\Exception('Simulated post-provisioning failure');
    });
    $serviceMock->setDi($di);

    expect(fn (): mixed => $serviceMock->createFromOrder($order))
        ->toThrow(FOSSBilling\Exception::class, 'Simulated post-provisioning failure');

    // Confirm persistOrder() actually stored the failure - not just that the
    // in-memory $order object was mutated - by reloading it through the
    // repository.
    $reloadedOrder = $di['em']->getRepository(Order::class)->find(1);
    expect($reloadedOrder->getStatus())->toBe(Order::STATUS_FAILED_SETUP);
});

test('createFromOrder marks the order failed_setup when activation bookkeeping raises a TypeError', function (): void {
    // Same regression as above, but for the wider \Throwable hierarchy: an
    // \Error/\TypeError after a successful provisioning call must also be
    // caught, otherwise the order is left in pending_setup with the remote
    // account already created and a retry would call the provisioning
    // action again against a service that already exists.
    $order = createEntity(Order::class, [
        'id' => 1,
        'period' => '1Y',
        'serviceType' => 'hosting',
    ]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn(new stdClass());
    $serviceMock->shouldReceive('_callOnService')
        ->once()
        ->with($order, Order::ACTION_ACTIVATE)
        ->andReturn(['username' => 'created-before-the-failure']);
    $serviceMock->shouldReceive('saveStatusChange')
        ->once()
        ->with($order, 'Simulated TypeError after provisioning');

    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('find')->with(1)->andReturn($order);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldReceive('persist')->once()->with($order);
    $emMock->shouldReceive('flush')->once();

    $di = container();
    $di['em'] = $emMock;
    $di['period'] = $di->protect(function (): never {
        throw new TypeError('Simulated TypeError after provisioning');
    });
    $serviceMock->setDi($di);

    expect(fn (): mixed => $serviceMock->createFromOrder($order))
        ->toThrow(TypeError::class, 'Simulated TypeError after provisioning');

    // Confirm persistOrder() actually stored the failure - not just that the
    // in-memory $order object was mutated - by reloading it through the
    // repository.
    $reloadedOrder = $di['em']->getRepository(Order::class)->find(1);
    expect($reloadedOrder->getStatus())->toBe(Order::STATUS_FAILED_SETUP);
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

    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
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

    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
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

    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
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

    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
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
    $modelClientOrder = createEntity(Order::class, ['id' => 7, 'clientId' => 5, 'groupId' => '68a3f1c2d4e5a']);

    $orderEntity = new Order();
    $idProp = new ReflectionProperty($orderEntity, 'id');
    $idProp->setValue($orderEntity, 1);

    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('findAddonsExcluding')->with('68a3f1c2d4e5a', 5, 7)->atLeast()->once()->andReturn([$orderEntity]);

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
        'suspension_grace_days' => 3,
        'meta' => [],
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('updatePeriod')->atLeast()->once()->with($clientOrderModel, $data['period']);
    $serviceMock->shouldReceive('updateOrderMeta')->atLeast()->once()->with($clientOrderModel, $data['meta']);

    $serviceMock->setDi($di);

    $result = $serviceMock->updateOrder($clientOrderModel, $data);

    expect($result)->toBeTrue()
        ->and($clientOrderModel->getSuspensionGraceDays())->toBe(3);
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

test('renewFromOrder treats a missing Doctrine expiration as now', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_callOnService')->once();
    $serviceMock->shouldReceive('saveStatusChange')->once()->with(Mockery::type(Order::class), 'Order renewed');

    $order = createEntity(Order::class, ['period' => '1Y']);
    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getExpirationTime')
        ->once()
        ->with(Mockery::on(static fn (int $from): bool => abs(time() - $from) <= 1))
        ->andReturn(strtotime('2027-01-01 00:00:00'));

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => ['order_renewal_logic' => 'from_greater']);
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);

    $serviceMock->setDi($di);
    $serviceMock->renewFromOrder($order);

    expect($order->getExpiresAt())->toEqual(new DateTime('2027-01-01 00:00:00'))
        ->and($order->getStatus())->toBe(Order::STATUS_ACTIVE);
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
    $clientOrderModel = createEntity(Order::class, [
        'id' => 10,
        'status' => Order::STATUS_ACTIVE,
    ]);

    $calls = [];
    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('cancelForOrder')
        ->once()
        ->with($clientOrderModel)
        ->andReturnUsing(function () use (&$calls): int {
            $calls[] = 'subscriptions';

            return 1;
        });

    $productService = Mockery::mock(Box\Mod\Product\Service::class);
    $productService->shouldReceive('releaseReservedPromoRedemptionsForOrder')
        ->once()
        ->with($clientOrderModel, 'order_canceled');
    $productService->shouldReceive('releaseReservedStockForOrder')
        ->once()
        ->with($clientOrderModel, 'order_canceled');

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('executeStatement')
        ->once()
        ->with(
            'DELETE FROM client_order_meta WHERE client_order_id = :order_id AND name = :name',
            ['order_id' => $clientOrderModel->getId(), 'name' => Service::META_CANCEL_AT_PERIOD_END],
        );
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldIgnoreMissing();

    $di = container();
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
        ->and($clientOrderModel->getStatus())->toBe(Order::STATUS_CANCELED)
        ->and($calls)->toBe(['service', 'subscriptions']);
});

test('scheduleCancellationFromOrder keeps the service active', function (): void {
    $order = createEntity(Order::class, [
        'id' => 10,
        'status' => Order::STATUS_ACTIVE,
    ]);

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('canCancelAtPeriodEndForOrder')->once()->with($order)->andReturn(true);
    $subscriptionService->shouldReceive('scheduleCancellationForOrder')->once()->with($order)->andReturn(1);

    $di = container();
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
        ->and($order->getStatus())->toBe(Order::STATUS_ACTIVE)
        ->and($order->getReason())->toBe('Customer request');
});

test('scheduleCancellationFromOrder does not mark the order when no subscription was scheduled', function (): void {
    $order = createEntity(Order::class, [
        'status' => Order::STATUS_ACTIVE,
    ]);

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('canCancelAtPeriodEndForOrder')->once()->with($order)->andReturn(true);
    $subscriptionService->shouldReceive('scheduleCancellationForOrder')->once()->with($order)->andReturn(0);

    $di = container();
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

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldNotReceive('flush');

    $di = container();
    $di['em'] = $emMock;
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
    $clientOrderModel = createEntity(Order::class, [
        'status' => Order::STATUS_ACTIVE,
    ]);

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('cancelForOrder')
        ->once()
        ->with(Mockery::any())
        ->andThrow(new RuntimeException('Subscription cancellation failed'));

    $di = container();
    $di['em'] = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $di['mod_service'] = $di->protect(fn () => $subscriptionService);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_callOnService')->once();
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->cancelFromOrder($clientOrderModel, skipEvent: true))
        ->toThrow(RuntimeException::class, 'Subscription cancellation failed')
        ->and($clientOrderModel->getStatus())->toBe(Order::STATUS_ACTIVE);
});

test('rmByClient removes all client orders', function (): void {
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 100]);

    $orderModel = createEntity(Order::class, ['id' => 1]);

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

    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
    $orderRepoMock->shouldReceive('findByClientId')->once()->with(100)->andReturn([$orderModel]);

    $metaRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing();
    $metaRepoMock->shouldReceive('deleteByOrderId')->once()->with(1);

    $statusRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderStatusRepository::class)->shouldIgnoreMissing();
    $statusRepoMock->shouldReceive('rmByOrderId')->once()->with(1);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->andReturn($metaRepoMock);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderStatus::class)->andReturn($statusRepoMock);
    $emMock->shouldIgnoreMissing();

    $productServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $productServiceMock->shouldReceive('releaseReservedPromoRedemptionsForOrder')
        ->once()
        ->with($orderModel, 'client_deleted');
    $productServiceMock->shouldReceive('releaseReservedStockForOrder')
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

    $clientOrder = createEntity(Order::class, ['id' => 1]);

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

    $clientOrder = createEntity(Order::class, ['id' => 1]);

    $result = $svc->updateOrderMeta($clientOrder, $meta);

    expect($result)->toEqual(2);
});

test('updateOrderMeta persists new meta entries with order details', function (): void {
    $meta = ['key' => 'value'];

    $metaRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing();
    $metaRepoMock->shouldReceive('findOneByOrderIdAndName')->with(7, 'key')->once()->andReturn(null);

    $persisted = [];
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->andReturn($metaRepoMock);
    $emMock->shouldReceive('persist')->once()->andReturnUsing(function ($entity) use (&$persisted): void {
        $persisted[] = $entity;
    });
    $emMock->shouldReceive('flush')->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class, ['id' => 7]);

    $result = $svc->updateOrderMeta($order, $meta);

    expect($result)->toEqual(2);
    expect($persisted)->toHaveCount(1);
    $metaEntity = $persisted[0];
    expect($metaEntity)->toBeInstanceOf(Box\Mod\Order\Entity\OrderMeta::class);
    expect($metaEntity->getClientOrderId())->toBe(7);
    expect($metaEntity->getName())->toBe('key');
    expect($metaEntity->getValue())->toBe('value');
});

test('updateOrderMeta updates existing meta', function (): void {
    $existing = new Box\Mod\Order\Entity\OrderMeta();
    $existing->setClientOrderId(7);
    $existing->setName('key');
    $existing->setValue('old value');

    $metaRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing();
    $metaRepoMock->shouldReceive('findOneByOrderIdAndName')->with(7, 'key')->once()->andReturn($existing);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->andReturn($metaRepoMock);
    $emMock->shouldReceive('persist')->once()->andReturnUsing(function ($entity) use ($existing): void {
        expect($entity)->toBe($existing);
    });
    $emMock->shouldReceive('flush')->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class, ['id' => 7]);

    $result = $svc->updateOrderMeta($order, ['key' => 'new value']);

    expect($result)->toEqual(2);
    expect($existing->getValue())->toBe('new value');
});

test('updateOrderMeta clears existing meta', function (): void {
    $metaRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderMetaRepository::class)->shouldIgnoreMissing();
    $metaRepoMock->shouldReceive('deleteByOrderId')->once()->with(7)->andReturn(1);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Order\Entity\OrderMeta::class)->andReturn($metaRepoMock);
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;

    $svc = new Service();
    $svc->setDi($di);

    $order = createEntity(Order::class, ['id' => 7]);

    $result = $svc->updateOrderMeta($order, []);

    expect($result)->toEqual(1);
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
    $pricingServiceMock->shouldReceive('reserveStockForOrder')->once();

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
    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
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
    $pricingServiceMock->shouldReceive('reserveStockForOrder')->once();

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
    $orderRepoMock = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
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

test('getExpiredOrders delegates grace-aware selection to the repository', function (): void {
    $service = new Service();

    $orderRepository = Mockery::mock(OrderRepository::class)->shouldIgnoreMissing();
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

test('batchSendSuspensionWarnings claims and queues each warning once', function (): void {
    $order = createEntity(Order::class, ['id' => 8, 'client_id' => 12]);
    $repository = Mockery::mock(OrderRepository::class);
    $repository->shouldReceive('getDueSuspensionWarnings')->twice()->andReturn([
        ['id' => 8, 'suspension_at' => '2026-08-01 12:00:00'],
    ]);
    $repository->shouldReceive('find')->twice()->with(8)->andReturn($order);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('transactional')->twice()->andReturnUsing(fn (callable $callback): mixed => $callback());
    $connection->shouldReceive('fetchOne')->twice()->with(
        'SELECT id FROM client_order WHERE id = :id FOR UPDATE',
        ['id' => 8]
    )->andReturn(8);
    $connection->shouldReceive('fetchAssociative')->twice()->andReturn(
        false,
        ['id' => 14, 'value' => '2026-08-01 12:00:00']
    );
    $connection->shouldReceive('insert')->once()->with('client_order_meta', Mockery::on(
        fn (array $data): bool => $data['client_order_id'] === 8
            && $data['name'] === 'suspension_warning_for'
            && $data['value'] === '2026-08-01 12:00:00'
    ));

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Order::class)->once()->andReturn($repository);
    $em->shouldReceive('getConnection')->twice()->andReturn($connection);

    $emailService = Mockery::mock(Box\Mod\Email\Service::class);
    $emailService->shouldReceive('sendTemplate')->once()->with(Mockery::on(
        fn (array $email): bool => $email['to_client'] === 12
            && $email['code'] === 'mod_order_suspension_warning'
            && $email['order']['suspension_at'] === '2026-08-01 12:00:00'
    ))->andReturn(true);

    $events = Mockery::mock(Box_EventManager::class);
    $events->shouldReceive('fire')->times(4);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('toApiArray')->once()->with($order, false)->andReturn(['id' => 8]);

    $di = container();
    $di['em'] = $em;
    $di['events_manager'] = $events;
    $di['logger'] = new Box_Log();
    $di['mod_service'] = $di->protect(fn (string $name): Box\Mod\Email\Service => $emailService);
    $service->setDi($di);

    expect($service->batchSendSuspensionWarnings())->toBeTrue()
        ->and($service->batchSendSuspensionWarnings())->toBeTrue();
});

test('batchSendSuspensionWarnings releases a failed claim so the warning can be retried', function (): void {
    $order = createEntity(Order::class, ['id' => 8, 'client_id' => 12]);
    $candidate = ['id' => 8, 'suspension_at' => '2026-08-01 12:00:00'];
    $repository = Mockery::mock(OrderRepository::class);
    $repository->shouldReceive('getDueSuspensionWarnings')->twice()->andReturn([$candidate]);
    $repository->shouldReceive('find')->twice()->with(8)->andReturn($order);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('transactional')->twice()->andReturnUsing(fn (callable $callback): mixed => $callback());
    $connection->shouldReceive('fetchOne')->twice()->andReturn(8);
    $connection->shouldReceive('fetchAssociative')->twice()->andReturn(false);
    $connection->shouldReceive('insert')->twice();
    $connection->shouldReceive('delete')->once()->with('client_order_meta', [
        'client_order_id' => 8,
        'name' => 'suspension_warning_for',
        'value' => $candidate['suspension_at'],
    ]);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Order::class)->once()->andReturn($repository);
    $em->shouldReceive('getConnection')->times(3)->andReturn($connection);

    $attempts = 0;
    $emailService = Mockery::mock(Box\Mod\Email\Service::class);
    $emailService->shouldReceive('sendTemplate')->twice()->andReturnUsing(function () use (&$attempts): bool {
        if (++$attempts === 1) {
            throw new RuntimeException('Queue unavailable');
        }

        return true;
    });

    $events = Mockery::mock(Box_EventManager::class);
    $events->shouldReceive('fire')->times(4);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('toApiArray')->twice()->with($order, false)->andReturn(['id' => 8]);

    $di = container();
    $di['em'] = $em;
    $di['events_manager'] = $events;
    $di['logger'] = new Box_Log();
    $di['mod_service'] = $di->protect(fn (string $name): Box\Mod\Email\Service => $emailService);
    $service->setDi($di);

    expect($service->batchSendSuspensionWarnings())->toBeTrue()
        ->and($service->batchSendSuspensionWarnings())->toBeTrue()
        ->and($attempts)->toBe(2);
});

test('exportCSV strips config from numeric-array headers', function (): void {
    $service = new Service();

    $capturedHeaders = null;
    $factoryMock = Mockery::mock();
    $factoryMock->shouldReceive('create')
        ->once()
        ->andReturnUsing(function (string $table, string $name, array $headers) use (&$capturedHeaders): Symfony\Component\HttpFoundation\Response {
            $capturedHeaders = $headers;

            return new Symfony\Component\HttpFoundation\Response();
        });

    $di = container();
    $di['csv_response_factory'] = $factoryMock;
    $service->setDi($di);

    $service->exportCSV(['config', 'id', 'title']);

    expect($capturedHeaders)->not->toContain('config')
        ->and($capturedHeaders)->toContain('id')
        ->and($capturedHeaders)->toContain('title');
});

test('exportCSV falls back to defaults when only config is requested', function (): void {
    $service = new Service();

    $capturedHeaders = null;
    $factoryMock = Mockery::mock();
    $factoryMock->shouldReceive('create')
        ->once()
        ->andReturnUsing(function (string $table, string $name, array $headers) use (&$capturedHeaders): Symfony\Component\HttpFoundation\Response {
            $capturedHeaders = $headers;

            return new Symfony\Component\HttpFoundation\Response();
        });

    $di = container();
    $di['csv_response_factory'] = $factoryMock;
    $service->setDi($di);

    $service->exportCSV(['config']);

    expect($capturedHeaders)->toContain('id')
        ->and($capturedHeaders)->toContain('title')
        ->and($capturedHeaders)->not->toContain('config');
});
