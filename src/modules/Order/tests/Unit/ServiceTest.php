<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Order\Service;
use Box\Mod\Product\Entity\Product;

use function Tests\Helpers\container;

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

test('counter returns status counts', function (): void {
    $service = new Service();

    $counter = [Model_ClientOrder::STATUS_ACTIVE => 1];
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getAssoc')->atLeast()->once()->andReturn($counter);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->counter();

    expect($result)->toBeArray();
    expect($result)->toHaveKey('total');
    expect($result['total'])->toEqual(array_sum($counter));
    expect($result)->toHaveKey(Model_ClientOrder::STATUS_PENDING_SETUP);
    expect($result)->toHaveKey(Model_ClientOrder::STATUS_FAILED_SETUP);
    expect($result)->toHaveKey(Model_ClientOrder::STATUS_ACTIVE);
    expect($result)->toHaveKey(Model_ClientOrder::STATUS_SUSPENDED);
    expect($result)->toHaveKey(Model_ClientOrder::STATUS_CANCELED);
});

test('onAfterAdminOrderActivate fires template', function (): void {
    $params = ['id' => 1];

    $eventMock = Mockery::mock(Box_Event::class);
    $eventMock->shouldReceive('getParameters')->atLeast()->once()->andReturn($params);

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($order);

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

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($order);

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

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($order);

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

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($order);

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

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($order);

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

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($order);

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

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($order);

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

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($order);

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

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($order);

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

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($order);

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

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($order);

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

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($order);

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

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['db'] = $dbMock;
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

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->never();
    $dbMock->shouldReceive('load')->atLeast()->once()->andReturn($service);

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('to_camel_case')->atLeast()->once()->andReturn('ServiceCustom');

    $di = container();
    $di['db'] = $dbMock;
    $di['tools'] = $toolsMock;

    $svc = new Service();
    $svc->setDi($di);

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->service_id = 1;
    $order->service_type = Box\Mod\Product\Service::CUSTOM;

    $result = $svc->getOrderService($order);

    expect($result)->toBeInstanceOf(Model_ServiceCustom::class);
});

test('getOrderService returns non-core service', function (): void {
    $service = new Model_ServiceCustom();

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->never();
    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn($service);

    $di = container();
    $di['db'] = $dbMock;

    $svc = new Service();
    $svc->setDi($di);

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->service_id = 1;

    $result = $svc->getOrderService($order);

    expect($result)->toBeInstanceOf(Model_ServiceCustom::class);
});

test('getOrderService returns null when service id is not set', function (): void {
    $service = new Model_ServiceCustom();

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->never();
    $dbMock->shouldReceive('findOne')->never();

    $di = container();
    $di['db'] = $dbMock;

    $svc = new Service();
    $svc->setDi($di);

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $result = $svc->getOrderService($order);

    expect($result)->toBeNull();
});

test('getServiceOrder returns order', function (): void {
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn($order);

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('from_camel_case')->atLeast()->once()->andReturn('servicecustom');

    $di = container();
    $di['db'] = $dbMock;
    $di['tools'] = $toolsMock;

    $svc = new Service();
    $svc->setDi($di);

    $service = new Model_ServiceCustom();
    $service->loadBean(new Tests\Helpers\DummyBean());
    $service->id = 1;

    $result = $svc->getServiceOrder($service);

    expect($result)->toBeInstanceOf(Model_ClientOrder::class);
});

test('getConfig returns config', function (): void {
    $svc = new Service();
    $di = container();
    $svc->setDi($di);

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $result = $svc->getConfig($order);

    expect($result)->toBeArray();
});

dataset('productHasOrdersProvider', function (): array {
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    return [
        'order present' => [$order, true],
        'order absent' => [null, false],
    ];
});

test('productHasOrders returns expected result', function (?Model_ClientOrder $order, bool $expectedResult): void {
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn($order);

    $di = container();
    $di['db'] = $dbMock;

    $svc = new Service();
    $svc->setDi($di);

    $product = orderServiceCreateProductEntity(1);

    $result = $svc->productHasOrders($product);

    expect($result)->toEqual($expectedResult);
})->with('productHasOrdersProvider');

test('saveStatusChange records history', function (): void {
    $orderStatus = new Model_ClientOrderStatus();
    $orderStatus->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('dispense')->atLeast()->once()->andReturn($orderStatus);
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;

    $svc = new Service();
    $svc->setDi($di);

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $result = $svc->saveStatusChange($order);

    expect($result)->toBeNull();
});

test('getSoonExpiringActiveOrders executes query', function (): void {
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getAll')->atLeast()->once()->andReturn([[], []]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getSoonExpiringActiveOrdersQuery')->atLeast()->once()->andReturn(['query', []]);

    $di = container();
    $di['db'] = $dbMock;
    $serviceMock->setDi($di);

    $serviceMock->getSoonExpiringActiveOrders();
});

test('getSoonExpiringActiveOrdersQuery builds expected SQL and bindings', function (): void {
    $randId = 1;

    $orderStatus = new Model_ClientOrderStatus();
    $orderStatus->loadBean(new Tests\Helpers\DummyBean());

    $systemService = Mockery::mock(Box\Mod\System\Service::class);
    $systemService->shouldReceive('getParamValue')->atLeast()->once()->andReturn($randId);

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $name): Mockery\MockInterface => match (strtolower($name)) {
        'system' => $systemService,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $svc = new Service();
    $svc->setDi($di);

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

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
        ':client_id' => $randId,
        ':unpaid_invoice_status' => Model_Invoice::STATUS_UNPAID,
        ':pending_item_type' => Model_InvoiceItem::TYPE_ORDER,
        ':pending_item_task' => Model_InvoiceItem::TASK_RENEW,
        ':pending_item_status' => Model_InvoiceItem::STATUS_EXECUTED,
        ':pending_invoice_status' => Model_Invoice::STATUS_PAID,
        ':status' => Model_ClientOrder::STATUS_ACTIVE,
        ':invoice_option' => 'issue-invoice',
        ':days_until_expiration' => $randId,
    ];

    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect($result[0])->toEqual($expectedQuery);
    expect($result[1])->toEqual($expectedBindings);
});

test('getRelatedOrderIdByType returns id', function (): void {
    $id = 1;
    $model = new Model_ClientOrder();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->id = $id;

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->atLeast()->once()->with('ClientOrder', Mockery::any(), Mockery::any())->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->getRelatedOrderIdByType($model, 'domain');

    expect($result)->toBeInt();
    expect($result)->toEqual($id);
});

test('getRelatedOrderIdByType returns null when not found', function (): void {
    $id = 1;
    $model = new Model_ClientOrder();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->id = $id;

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->atLeast()->once()->with('ClientOrder', Mockery::any(), Mockery::any())->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->getRelatedOrderIdByType($model, 'domain');

    expect($result)->toBeNull();
});

test('getLogger returns logger with event items', function (): void {
    $model = new Model_ClientOrder();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->id = 5;
    $model->status = 'active';

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
    $model = new Model_ClientOrder();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->config = '{}';
    $model->price = 10;
    $model->quantity = 1;
    $model->client_id = 1;

    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $supportService = Mockery::mock(Box\Mod\Support\Service::class);
    $supportTicketRepo = Mockery::mock(Box\Mod\Support\Repository\SupportTicketRepository::class);
    $supportTicketRepo->shouldReceive('countActiveTicketsForOrder')->atLeast()->once()->andReturn(1);
    $supportService->shouldReceive('getSupportTicketRepository')->atLeast()->once()->andReturn($supportTicketRepo);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('toArray')->atLeast()->once()->andReturn([]);
    $dbMock->shouldReceive('getAssoc')->atLeast()->once()->andReturn([]);

    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());

    $exceptionError = 'Client not found';
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->with('Client', $model->client_id, $exceptionError)
        ->andReturn($modelClient);

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

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->toApiArray($model, true, new Model_Admin());

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
        [':client_id' => '1'],
    ],
    'invoice_option' => [
        ['invoice_option' => 'issue-invoice'],
        'co.invoice_option = :invoice_option',
        [':invoice_option' => 'issue-invoice'],
    ],
    'id' => [
        ['id' => 1],
        'co.id = :id',
        [':id' => '1'],
    ],
    'status' => [
        ['status' => 'pending_setup'],
        'co.status = :status',
        [':status' => 'pending_setup'],
    ],
    'product_id' => [
        ['product_id' => 1],
        'co.product_id = :product_id',
        [':product_id' => '1'],
    ],
    'type' => [
        ['type' => 'custom'],
        'co.service_type = :service_type',
        [':service_type' => 'custom'],
    ],
    'title' => [
        ['title' => 'titleField'],
        'co.title LIKE :title',
        [':title' => '%titleField%'],
    ],
    'period' => [
        ['period' => '1Y'],
        'co.period = :period',
        [':period' => '1Y'],
    ],
    'hide_addons' => [
        ['hide_addons' => true],
        'co.group_master = 1',
        [],
    ],
    'created_at' => [
        ['created_at' => '2012-12-11'],
        "DATE_FORMAT(co.created_at, '%Y-%m-%d') = :created_at",
        [':created_at' => '2012-12-11'],
    ],
    'date_from' => [
        ['date_from' => '2012-12-11'],
        'UNIX_TIMESTAMP(co.created_at) >= :date_from',
        [':date_from' => strtotime('2012-12-11')],
    ],
    'date_to' => [
        ['date_to' => '2012-12-11'],
        'UNIX_TIMESTAMP(co.created_at) <= :date_to',
        [':date_to' => strtotime('2012-12-11')],
    ],
    'search numeric' => [
        ['search' => 120],
        'co.id = :search',
        [':search' => 120],
    ],
    'search string' => [
        ['search' => 'John'],
        '(c.first_name LIKE :first_name OR c.last_name LIKE :last_name OR co.title LIKE :title)',
        [
            ':first_name' => '%John%',
            ':last_name' => '%John%',
            ':title' => '%John%',
        ],
    ],
    'ids' => [
        ['ids' => [1, 2, 3]],
        'co.id IN (:ids)',
        [':ids' => '1, 2, 3'],
    ],
    'promo_id' => [
        ['promo_id' => 9],
        'co.promo_id = :promo_id',
        [':promo_id' => 9],
    ],
    'meta' => [
        ['meta' => ['param' => 'value']],
        '(meta.name = :meta_name1 AND meta.value LIKE :meta_value1)',
        [
            ':meta_name1' => 'param',
            ':meta_value1' => 'value%',
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
    expect($bindings[':client_id'])->toBe(42);
});

test('createOrder throws when no order currency is set', function (): void {
    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());

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
    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

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
    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

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
    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

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
    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

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

    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('transaction')
        ->once()
        ->andReturnUsing(fn (callable $callback) => $callback());
    $dbMock->shouldReceive('dispense')->atLeast()->once()->with('ClientOrder')->andReturn($clientOrderModel);

    $newId = 1;
    $dbMock->shouldReceive('store')->atLeast()->once()->with($clientOrderModel)->andReturn($newId);
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->with('ClientOrder', $newId, 'Order not found')
        ->andReturn($clientOrderModel);

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
    $di['db'] = $dbMock;
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);
    $di['logger'] = new Box_Log();

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->createOrder($modelClient, $modelProduct, ['period' => '1Y', 'price' => '10', 'notes' => 'test']);

    expect($result)->toEqual($newId);
});

test('createOrder sets form id from product', function (): void {
    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

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

    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('transaction')
        ->once()
        ->andReturnUsing(fn (callable $callback) => $callback());
    $dbMock->shouldReceive('dispense')->atLeast()->once()->with('ClientOrder')->andReturn($clientOrderModel);

    $newId = 1;
    $dbMock->shouldReceive('store')->atLeast()->once()->with($clientOrderModel)->andReturn($newId);
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->with('ClientOrder', $newId, 'Order not found')
        ->andReturn($clientOrderModel);

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
    $di['db'] = $dbMock;
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);
    $di['logger'] = new Box_Log();

    $svc = new Service();
    $svc->setDi($di);

    $svc->createOrder($modelClient, $modelProduct, ['period' => '1Y', 'price' => '10']);

    expect($clientOrderModel->form_id)->toEqual(42);
});

test('createOrder returns success when invoice follow up fails', function (): void {
    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

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

    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->id = 10;

    $invoiceServiceMock = Mockery::mock(Box\Mod\Invoice\Service::class);
    $invoiceServiceMock->shouldReceive('generateForOrder')
        ->once()
        ->with($clientOrderModel)
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

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('transaction')
        ->once()
        ->andReturnUsing(fn (callable $callback) => $callback());
    $dbMock->shouldReceive('dispense')->atLeast()->once()->with('ClientOrder')->andReturn($clientOrderModel);

    $newId = 1;
    $dbMock->shouldReceive('store')->atLeast()->once()->with($clientOrderModel)->andReturn($newId);
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->with('ClientOrder', $newId, 'Order not found')
        ->andReturn($clientOrderModel);

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
    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

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

    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('transaction')
        ->once()
        ->andReturnUsing(fn (callable $callback) => $callback());
    $dbMock->shouldReceive('dispense')->atLeast()->once()->with('ClientOrder')->andReturn($clientOrderModel);

    $newId = 10;
    $dbMock->shouldReceive('store')->atLeast()->once()->with($clientOrderModel)->andReturn($newId);
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->with('ClientOrder', $newId, 'Order not found')
        ->andReturn($clientOrderModel);

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
    $di['db'] = $dbMock;
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
    expect($clientOrderModel->quantity)->toBe(2);
    expect($clientOrderModel->price)->toBe(22.0);
});

test('getMasterOrderForClient returns master order', function (): void {
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());

    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->atLeast()->once()->with('ClientOrder', Mockery::any(), Mockery::any())->andReturn($clientOrderModel);

    $di = container();
    $di['db'] = $dbMock;

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->getMasterOrderForClient($clientModel, 1);

    expect($result)->toBeInstanceOf(Model_ClientOrder::class);
});

test('activateOrder throws for non-pending order', function (): void {
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());
    $clientOrderModel->status = Model_ClientOrder::STATUS_CANCELED;

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('load')->atLeast()->once()->with('ClientOrder', Mockery::any())->andReturn($clientOrderModel);

    $di = container();
    $di['db'] = $dbMock;

    $svc = new Service();
    $svc->setDi($di);

    expect(fn (): bool => $svc->activateOrder($clientOrderModel))
        ->toThrow(FOSSBilling\Exception::class, 'Only pending setup or failed orders can be activated');
});

test('activateOrder activates pending order', function (): void {
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());
    $clientOrderModel->status = Model_ClientOrder::STATUS_PENDING_SETUP;
    $clientOrderModel->group_master = 1;

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('load')->atLeast()->once()->with('ClientOrder', Mockery::any())->andReturn($clientOrderModel);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
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
    $staleOrderModel = new Model_ClientOrder();
    $staleOrderModel->loadBean(new Tests\Helpers\DummyBean());
    $staleOrderModel->status = Model_ClientOrder::STATUS_PENDING_SETUP;

    $activeOrderModel = new Model_ClientOrder();
    $activeOrderModel->loadBean(new Tests\Helpers\DummyBean());
    $activeOrderModel->status = Model_ClientOrder::STATUS_ACTIVE;
    $activeOrderModel->group_master = 1;

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('load')->atLeast()->once()->with('ClientOrder', Mockery::any())->andReturn($activeOrderModel);

    $di = container();
    $di['db'] = $dbMock;

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('createFromOrder')->never();
    $serviceMock->shouldReceive('activateOrderAddons')->never();

    $serviceMock->setDi($di);

    $result = $serviceMock->activateOrder($staleOrderModel);

    expect($result)->toBeTrue();
});

test('activateOrder force re-activates an already active order', function (): void {
    $activeOrderModel = new Model_ClientOrder();
    $activeOrderModel->loadBean(new Tests\Helpers\DummyBean());
    $activeOrderModel->status = Model_ClientOrder::STATUS_ACTIVE;
    $activeOrderModel->group_master = 1;

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('load')->atLeast()->once()->with('ClientOrder', Mockery::any())->andReturn($activeOrderModel);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
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
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('createFromOrder')->atLeast()->once()->andReturn([]);

    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());
    $clientOrderModel->status = Model_ClientOrder::STATUS_PENDING_SETUP;
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
    $modelClientOrder = new Model_ClientOrder();
    $modelClientOrder->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('find')->atLeast()->once()->with('ClientOrder', Mockery::any(), Mockery::any())->andReturn([new Model_ClientOrder()]);

    $di = container();
    $di['db'] = $dbMock;

    $svc = new Service();
    $svc->setDi($di);

    $result = $svc->getOrderAddonsList($modelClientOrder);

    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(Model_ClientOrder::class);
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
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once()->with($clientOrderModel);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['db'] = $dbMock;
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
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());
    $clientOrderModel->group_master = 1;
    $clientOrderModel->status = Model_ClientOrder::STATUS_PENDING_SETUP;

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

    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());
    $clientOrderModel->period = '1Y';
    $clientOrderModel->expires_at = '2026-01-01 00:00:00';

    $expectedExpiration = strtotime('2027-01-01 00:00:00');
    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getExpirationTime')
        ->atLeast()->once()
        ->with(strtotime('2026-01-01 00:00:00'))
        ->andReturn($expectedExpiration);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $serviceMock->shouldReceive('saveStatusChange')
        ->atLeast()->once()
        ->with($clientOrderModel, 'Order renewed');

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => []);
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $serviceMock->renewFromOrder($clientOrderModel);

    expect($clientOrderModel->expires_at)->toEqual('2027-01-01 00:00:00');
    expect($clientOrderModel->status)->toEqual(Model_ClientOrder::STATUS_ACTIVE);
});

test('renewFromOrder extends free first term on first paid renewal', function (): void {
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());
    $clientOrderModel->period = '1Y';
    $clientOrderModel->status = Model_ClientOrder::STATUS_ACTIVE;
    $clientOrderModel->activated_at = '2025-01-01 00:00:00';
    $clientOrderModel->expires_at = '2026-01-01 00:00:00';

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_callOnService')
        ->once()
        ->with(Mockery::on(fn ($order): bool => $order === $clientOrderModel), Model_ClientOrder::ACTION_RENEW);

    $expectedExpiration = strtotime('2027-01-01 00:00:00');
    $periodMock = Mockery::mock(Box_Period::class);
    $periodMock->shouldReceive('getExpirationTime')
        ->once()
        ->with(strtotime('2026-01-01 00:00:00'))
        ->andReturn($expectedExpiration);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $serviceMock->shouldReceive('saveStatusChange')
        ->once()
        ->with($clientOrderModel, 'Order renewed');

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => []);
    $di['period'] = $di->protect(fn (): Mockery\MockInterface => $periodMock);
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $serviceMock->renewFromOrder($clientOrderModel);

    expect($clientOrderModel->expires_at)->toEqual('2027-01-01 00:00:00');
    expect($clientOrderModel->status)->toEqual(Model_ClientOrder::STATUS_ACTIVE);
});

test('suspendFromOrder throws for non-active order', function (): void {
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());
    $clientOrderModel->status = Model_ClientOrder::STATUS_SUSPENDED;

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
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());
    $clientOrderModel->status = Model_ClientOrder::STATUS_ACTIVE;

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once()->with($clientOrderModel);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Box_Log();
    $di['db'] = $dbMock;

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_callOnService')->atLeast()->once();
    $serviceMock->shouldReceive('saveStatusChange')->atLeast()->once();

    $serviceMock->setDi($di);

    $result = $serviceMock->suspendFromOrder($clientOrderModel);

    expect($result)->toBeTrue();
});

test('rmByClient removes all client orders', function (): void {
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());
    $clientModel->id = 100;

    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

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

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('find')
        ->once()
        ->with('ClientOrder', 'client_id = ?', [100])
        ->andReturn([$orderModel]);

    $productServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $productServiceMock->shouldReceive('releaseReservedPromoRedemptionsForOrder')
        ->once()
        ->with($orderModel, 'client_deleted');

    $di = container();
    $di['db'] = $dbMock;
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

    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());

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

    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());

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

    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());

    $result = $svc->updatePeriod($clientOrder, $period);

    expect($result)->toEqual(0);
});

test('updateOrderMeta returns 0 when meta is not an array', function (): void {
    $meta = null;
    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());

    $svc = new Service();

    $result = $svc->updateOrderMeta($clientOrder, $meta);

    expect($result)->toEqual(0);
});

test('updateOrderMeta clears existing meta when empty', function (): void {
    $meta = [];
    $di = container();

    $dBMock = Mockery::mock(Box_Database::class);
    $dBMock->shouldReceive('exec')->atLeast()->once();
    $di['db'] = $dBMock;

    $svc = new Service();
    $svc->setDi($di);

    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());

    $result = $svc->updateOrderMeta($clientOrder, $meta);

    expect($result)->toEqual(1);
});

test('updateOrderMeta stores new meta entries', function (): void {
    $meta = ['key' => 'value'];
    $di = container();

    $dBMock = Mockery::mock(Box_Database::class);
    $dBMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->with('ClientOrderMeta', Mockery::any(), Mockery::any())
        ->andReturn(null);

    $clientOrderMetaModel = new Model_ClientOrderMeta();
    $clientOrderMetaModel->loadBean(new Tests\Helpers\DummyBean());

    $dBMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->with('ClientOrderMeta')
        ->andReturn($clientOrderMetaModel);
    $dBMock->shouldReceive('store')->atLeast()->once();

    $di['db'] = $dBMock;

    $svc = new Service();
    $svc->setDi($di);

    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());

    $result = $svc->updateOrderMeta($clientOrder, $meta);

    expect($result)->toEqual(2);
});

test('updateOrderConfig succeeds when no form id is set', function (): void {
    $di = container();

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->once();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();

    $svc = new Service();
    $svc->setDi($di);

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
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

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
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

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->once();

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($formbuilderServiceMock) {
        if ($serviceName === 'formbuilder') {
            return $formbuilderServiceMock;
        }
    });
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();

    $svc = new Service();
    $svc->setDi($di);

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->form_id = 11;

    $result = $svc->updateOrderConfig($order, ['hostname' => 'myhost.example.com', 'plan' => 'pro', 'addons' => ['backup', 'ssl']]);

    expect($result)->toBeTrue();
});
