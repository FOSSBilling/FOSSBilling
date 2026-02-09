<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use Box\Mod\Order\Service;

class OrderPdoMock extends \PDO
{
    public function __construct()
    {
    }
}

class OrderPdoStatementsMock extends \PDOStatement
{
    public function __construct()
    {
    }
}

beforeEach(function () {
    $this->service = new Service();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

test('counts orders by status', function () {
    $counter = [
        \Model_ClientOrder::STATUS_ACTIVE => 1,
    ];
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn($counter);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);

    $result = $this->service->counter();
    expect($result)->toBeArray();
    expect($result)->toHaveKey('total');
    expect($result['total'])->toBe(array_sum($counter));
    expect($result)->toHaveKey(\Model_ClientOrder::STATUS_PENDING_SETUP);
    expect($result)->toHaveKey(\Model_ClientOrder::STATUS_FAILED_SETUP);
    expect($result)->toHaveKey(\Model_ClientOrder::STATUS_ACTIVE);
    expect($result)->toHaveKey(\Model_ClientOrder::STATUS_SUSPENDED);
    expect($result)->toHaveKey(\Model_ClientOrder::STATUS_CANCELED);
});

test('fires event after admin activates order', function () {
    $params = ['id' => 1];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($order);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andReturn(true);

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getOrderServiceData')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($orderArr);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

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

    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $serviceMock->setDi($di);

    $serviceMock->onAfterAdminOrderActivate($eventMock);
});

test('logs exception when email fails on activate', function () {
    $params = ['id' => 1];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($order);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new \Exception('Email send failure'));

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getOrderServiceData')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($orderArr);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

    $loggerMock = new \Tests\Helpers\TestLogger();

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
    $di['logger'] = $loggerMock;

    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $serviceMock->setDi($di);

    $serviceMock->onAfterAdminOrderActivate($eventMock);

    expect($loggerMock->calls)->toHaveCount(1);
    expect($loggerMock->calls[0]['method'])->toBe('err');
    expect($loggerMock->calls[0]['params'][0])->toBe('Email send failure');
});

test('fires event after admin renews order', function () {
    $params = ['id' => 1];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($order);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andReturn(true);

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getOrderServiceData')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($orderArr);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

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
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $serviceMock->onAfterAdminOrderRenew($eventMock);
});

test('logs exception when email fails on renew', function () {
    $params = ['id' => 1];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($order);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new \Exception('Email send failure'));

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getOrderServiceData')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($orderArr);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

    $loggerMock = new \Tests\Helpers\TestLogger();

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
    $di['logger'] = $loggerMock;
    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $serviceMock->onAfterAdminOrderRenew($eventMock);

    expect($loggerMock->calls)->toHaveCount(1);
    expect($loggerMock->calls[0]['method'])->toBe('err');
    expect($loggerMock->calls[0]['params'][0])->toBe('Email send failure');
});

test('fires event after admin suspends order', function () {
    $params = ['id' => 1];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($order);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andReturn(true);

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getOrderServiceData')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($orderArr);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

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
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $serviceMock->onAfterAdminOrderSuspend($eventMock);
});

test('logs exception when email fails on suspend', function () {
    $params = ['id' => 1];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($order);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new \Exception('Email send failure'));

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getOrderServiceData')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($orderArr);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

    $loggerMock = new \Tests\Helpers\TestLogger();

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
    $di['logger'] = $loggerMock;
    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $serviceMock->onAfterAdminOrderSuspend($eventMock);

    expect($loggerMock->calls)->toHaveCount(1);
    expect($loggerMock->calls[0]['method'])->toBe('err');
    expect($loggerMock->calls[0]['params'][0])->toBe('Email send failure');
});

test('fires event after admin unsuspends order', function () {
    $params = ['id' => 1];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($order);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andReturn(true);

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getOrderServiceData')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($orderArr);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

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
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $serviceMock->onAfterAdminOrderUnsuspend($eventMock);
});

test('logs exception when email fails on unsuspend', function () {
    $params = ['id' => 1];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($order);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new \Exception('Email send failure'));

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getOrderServiceData')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($orderArr);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

    $loggerMock = new \Tests\Helpers\TestLogger();

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
    $di['logger'] = $loggerMock;
    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $serviceMock->onAfterAdminOrderUnsuspend($eventMock);

    expect($loggerMock->calls)->toHaveCount(1);
    expect($loggerMock->calls[0]['method'])->toBe('err');
    expect($loggerMock->calls[0]['params'][0])->toBe('Email send failure');
});

test('fires event after admin cancels order', function () {
    $params = ['id' => 1];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($order);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andReturn(true);

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($orderArr);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

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
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $serviceMock->onAfterAdminOrderCancel($eventMock);
});

test('logs exception when email fails on cancel', function () {
    $params = ['id' => 1];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($order);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new \Exception('Email send failure'));

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($orderArr);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

    $loggerMock = new \Tests\Helpers\TestLogger();

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
    $di['logger'] = $loggerMock;
    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $serviceMock->onAfterAdminOrderCancel($eventMock);

    expect($loggerMock->calls)->toHaveCount(1);
    expect($loggerMock->calls[0]['method'])->toBe('err');
    expect($loggerMock->calls[0]['params'][0])->toBe('Email send failure');
});

test('fires event after admin uncancels order', function () {
    $params = ['id' => 1];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($order);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andReturn(true);

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getOrderServiceData')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($orderArr);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

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
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $serviceMock->onAfterAdminOrderUncancel($eventMock);
});

test('logs exception when email fails on uncancel', function () {
    $params = ['id' => 1];

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn($params);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($order);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->andThrow(new \Exception('Email send failure'));

    $orderArr = [
        'id' => 1,
        'client' => ['id' => 1],
        'service_type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getOrderServiceData')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($orderArr);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

    $loggerMock = new \Tests\Helpers\TestLogger();

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
    $di['logger'] = $loggerMock;
    $serviceMock->setDi($di);
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $serviceMock->onAfterAdminOrderUncancel($eventMock);

    expect($loggerMock->calls)->toHaveCount(1);
    expect($loggerMock->calls[0]['method'])->toBe('err');
    expect($loggerMock->calls[0]['params'][0])->toBe('Email send failure');
});

test('gets order core service', function () {
    $service = new \Model_ServiceCustom();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->never();
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($service);

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('to_camel_case')
        ->atLeast()->once()
        ->andReturn('ServiceCustom');

    $di = container();
    $di['db'] = $dbMock;
    $di['tools'] = $toolsMock;
    $this->service->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->service_id = 1;
    $order->service_type = \Model_ProductTable::CUSTOM;

    $result = $this->service->getOrderService($order);
    expect($result)->toBeInstanceOf('Model_ServiceCustom');
});

test('gets order non-core service', function () {
    $service = new \Model_ServiceCustom();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->never()
        ->andReturn($service);
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($service);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->service_id = 1;

    $result = $this->service->getOrderService($order);
    expect($result)->toBeInstanceOf('Model_ServiceCustom');
});

test('returns null when service id not set', function () {
    $service = new \Model_ServiceCustom();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->never()
        ->andReturn($service);
    $dbMock->shouldReceive('findOne')
        ->never()
        ->andReturn($service);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->getOrderService($order);
    expect($result)->toBeNull();
});

test('gets service order', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($order);

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('from_camel_case')
        ->atLeast()->once()
        ->andReturn('servicecustom');

    $di = container();
    $di['db'] = $dbMock;
    $di['tools'] = $toolsMock;
    $this->service->setDi($di);

    $service = new \Model_ServiceCustom();
    $service->loadBean(new \Tests\Helpers\DummyBean());
    $service->id = 1;

    $result = $this->service->getServiceOrder($service);
    expect($result)->toBeInstanceOf('Model_ClientOrder');
});

test('gets order config', function () {
    $di = container();
    $this->service->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->getConfig($order);
    expect($result)->toBeArray();
});

test('checks if product has orders', function ($order, $expectedResult) {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($order);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $product = new \Model_Product();
    $product->loadBean(new \Tests\Helpers\DummyBean());
    $product->id = 1;

    $result = $this->service->productHasOrders($product);
    expect($result)->toBe($expectedResult);
})->with([
    [new \Model_ClientOrder(), true],
    [null, false],
]);

test('saves status change', function () {
    $orderStatus = new \Model_ClientOrderStatus();
    $orderStatus->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($orderStatus);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->saveStatusChange($order);
    expect($result)->toBeNull();
});

test('gets soon expiring active orders', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn([[], []]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getSoonExpiringActiveOrdersQuery')
        ->atLeast()->once()
        ->andReturn(['query', []]);

    $di = container();
    $di['db'] = $dbMock;
    $serviceMock->setDi($di);

    $serviceMock->getSoonExpiringActiveOrders();
});

test('gets soon expiring active orders query', function () {
    $randId = 1;

    $orderStatus = new \Model_ClientOrderStatus();
    $orderStatus->loadBean(new \Tests\Helpers\DummyBean());

    $systemService = Mockery::mock(\Box\Mod\System\Service::class);
    $systemService->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn($randId);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemService);

    $this->service->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $data = ['client_id' => $randId];
    $result = $this->service->getSoonExpiringActiveOrdersQuery($data);

    $expectedQuery = 'SELECT *
                FROM client_order
                WHERE status = :status
                AND invoice_option = :invoice_option
                AND period IS NOT NULL
                AND expires_at IS NOT NULL
                AND unpaid_invoice_id IS NULL AND client_id = :client_id HAVING DATEDIFF(expires_at, NOW()) <= :days_until_expiration ORDER BY client_id DESC';

    $expectedBindings = [
        ':client_id' => $randId,
        ':status' => 'active',
        ':invoice_option' => 'issue-invoice',
        ':days_until_expiration' => $randId,
    ];

    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect($result[0])->toBe($expectedQuery);
    expect($result[1])->toBe($expectedBindings);
});

test('gets related order id by type', function () {
    $id = 1;
    $model = new \Model_ClientOrder();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = $id;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->with('ClientOrder', 'group_id = :group_id AND service_type = :service_type', \Mockery::type('array'))
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getRelatedOrderIdByType($model, 'domain');
    expect($result)->toBeInt();
    expect($result)->toBe($id);
});

test('returns null when related order not found', function () {
    $model = new \Model_ClientOrder();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->with('ClientOrder', 'group_id = :group_id AND service_type = :service_type', \Mockery::type('array'))
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getRelatedOrderIdByType($model, 'domain');
    expect($result)->toBeNull();
});

test('gets logger', function () {
    $model = new \Model_ClientOrder();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $result = $this->service->getLogger($model);
    expect($result)->toBeInstanceOf('\Box_Log');
});

test('converts order to api array', function () {
    $model = new \Model_ClientOrder();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->config = '{}';
    $model->price = 10;
    $model->quantity = 1;
    $model->client_id = 1;

    $clientService = Mockery::mock(\Box\Mod\Client\Service::class);
    $clientService->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $supportService = Mockery::mock(\Box\Mod\Support\Service::class);
    $supportService->shouldReceive('getActiveTicketsCountForOrder')
        ->atLeast()->once()
        ->andReturn(1);

    $dbMock = Mockery::mock(\Box_Database::class);
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);
    $dbMock->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn([]);

    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());
    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturnUsing(function (...$args): \Model_Product {
            return match ($args[0]) {
                'Product' => new \Model_Product(),
            };
        });

    $dbMock->shouldReceive('getExistingModelById')
        ->with('Client', $model->client_id, 'Client not found')
        ->atLeast()->once()
        ->andReturn($modelClient);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($clientService, $supportService) {
        if ($serviceName == 'client') {
            return $clientService;
        }
        if ($serviceName == 'support') {
            return $supportService;
        }
    });
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->toApiArray($model, true, new \Model_Admin());

    expect($result)->toHaveKey('config');
    expect($result)->toHaveKey('total');
    expect($result)->toHaveKey('title');
    expect($result)->toHaveKey('meta');
    expect($result)->toHaveKey('active_tickets');
    expect($result)->toHaveKey('plugin');
    expect($result)->toHaveKey('client');
});

test('gets search query with various filters', function (array $data, string $expectedStr, array $expectedParams) {
    $di = container();

    $this->service->setDi($di);

    $result = $this->service->getSearchQuery($data);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect(str_contains($result[0], $expectedStr))->toBeTrue($result[0]);
    expect(array_diff_key($result[1], $expectedParams))->toBe([]);
})->with([
    [[], 'SELECT co.* from client_order co', []],
    [
        ['client_id' => 1],
        'co.client_id = :client_id',
        [':client_id' => '1'],
    ],
    [
        ['invoice_option' => 'issue-invoice'],
        'co.invoice_option = :invoice_option',
        [':invoice_option' => 'issue-invoice'],
    ],
    [
        ['id' => 1],
        'co.id = :id',
        [':id' => '1'],
    ],
    [
        ['status' => 'pending_setup'],
        'co.status = :status',
        [':status' => 'pending_setup'],
    ],
    [
        ['product_id' => 1],
        'co.product_id = :product_id',
        [':product_id' => '1'],
    ],
    [
        ['type' => 'custom'],
        'co.service_type = :service_type',
        [':service_type' => 'custom'],
    ],
    [
        ['title' => 'titleField'],
        'co.title LIKE :title',
        [':title' => '%titleField%'],
    ],
    [
        ['period' => '1Y'],
        'co.period = :period',
        [':period' => '1Y'],
    ],
    [
        ['hide_addons' => true],
        'co.group_master = 1',
        [],
    ],
    [
        ['created_at' => '2012-12-11'],
        "DATE_FORMAT(co.created_at, '%Y-%m-%d') = :created_at",
        [':created_at' => '2012-12-11'],
    ],
    [
        ['date_from' => '2012-12-11'],
        'UNIX_TIMESTAMP(co.created_at) >= :date_from',
        [':date_from' => strtotime('2012-12-11')],
    ],
    [
        ['date_to' => '2012-12-11'],
        'UNIX_TIMESTAMP(co.created_at) <= :date_to',
        [':date_to' => strtotime('2012-12-11')],
    ],
    [
        ['search' => 120],
        'co.id = :search',
        [':search' => 120],
    ],
    [
        ['search' => 'John'],
        '(c.first_name LIKE :first_name OR c.last_name LIKE :last_name OR co.title LIKE :title)',
        [
            ':first_name' => '%John%',
            ':last_name' => '%John%',
            ':title' => '%John%',
        ],
    ],
    [
        ['ids' => [1, 2, 3]],
        'co.id IN (:ids)',
        [':ids' => [1, 2, 3]],
    ],
    [
        ['meta' => ['param' => 'value']],
        '(meta.name = :meta_name1 AND meta.value LIKE :meta_value1)',
        [
            ':meta_name1' => 'param',
            ':meta_value1' => 'value%',
        ],
    ],
]);

test('throws exception when creating order with missing currency', function () {
    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());

    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());

    $currencyRepositoryMock = Mockery::mock('\Box\Mod\Currency\Repository\CurrencyRepository');
    $currencyRepositoryMock->shouldReceive('findDefault')
        ->atLeast()->once()
        ->andReturn(null);

    $currencyServiceMock = Mockery::mock('\Box\Mod\Currency\Service');
    $currencyServiceMock->shouldReceive('getCurrencyRepository')
        ->atLeast()->once()
        ->andReturn($currencyRepositoryMock);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock) {
        if ($serviceName == 'currency') {
            return $currencyServiceMock;
        }
    });

    $this->service->setDi($di);

    expect(fn () => $this->service->createOrder($modelClient, $modelProduct, []))
        ->toThrow(\FOSSBilling\Exception::class, 'Currency could not be determined for order');
});

test('throws exception when creating order for out of stock product', function () {
    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());
    $modelProduct->id = 1;

    $currencyModel = Mockery::mock('\Box\Mod\Currency\Entity\Currency');

    $currencyRepositoryMock = Mockery::mock('\Box\Mod\Currency\Repository\CurrencyRepository');
    $currencyRepositoryMock->shouldReceive('findOneByCode')
        ->atLeast()->once()
        ->andReturn($currencyModel);

    $currencyServiceMock = Mockery::mock('\Box\Mod\Currency\Service');
    $currencyServiceMock->shouldReceive('getCurrencyRepository')
        ->atLeast()->once()
        ->andReturn($currencyRepositoryMock);

    $cartServiceMock = Mockery::mock('\Box\Mod\Cart\Service');
    $cartServiceMock->shouldReceive('isStockAvailable')
        ->with($modelProduct, 1)
        ->atLeast()->once()
        ->andReturn(false);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire');

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

    $this->service->setDi($di);

    expect(fn () => $this->service->createOrder($modelClient, $modelProduct, []))
        ->toThrow(\FOSSBilling\InformationException::class, 'Product 1 is out of stock.');
});

test('throws exception when creating addon order with missing group id', function () {
    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());
    $modelProduct->id = 1;
    $modelProduct->is_addon = 1;

    $currencyModel = Mockery::mock('\Box\Mod\Currency\Entity\Currency');

    $currencyRepositoryMock = Mockery::mock('\Box\Mod\Currency\Repository\CurrencyRepository');
    $currencyRepositoryMock->shouldReceive('findOneByCode')
        ->atLeast()->once()
        ->andReturn($currencyModel);

    $currencyServiceMock = Mockery::mock('\Box\Mod\Currency\Service');
    $currencyServiceMock->shouldReceive('getCurrencyRepository')
        ->atLeast()->once()
        ->andReturn($currencyRepositoryMock);

    $cartServiceMock = Mockery::mock('\Box\Mod\Cart\Service');
    $cartServiceMock->shouldReceive('isStockAvailable')
        ->with($modelProduct, 1)
        ->atLeast()->once()
        ->andReturn(true);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire');

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

    $this->service->setDi($di);

    expect(fn () => $this->service->createOrder($modelClient, $modelProduct, []))
        ->toThrow(\FOSSBilling\Exception::class, 'Group ID parameter is missing for addon product order');
});

test('throws exception when creating order with parent not found', function () {
    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());
    $modelProduct->id = 1;

    $currencyModel = Mockery::mock('\Box\Mod\Currency\Entity\Currency');

    $currencyRepositoryMock = Mockery::mock('\Box\Mod\Currency\Repository\CurrencyRepository');
    $currencyRepositoryMock->shouldReceive('findOneByCode')
        ->atLeast()->once()
        ->andReturn($currencyModel);

    $currencyServiceMock = Mockery::mock('\Box\Mod\Currency\Service');
    $currencyServiceMock->shouldReceive('getCurrencyRepository')
        ->atLeast()->once()
        ->andReturn($currencyRepositoryMock);

    $cartServiceMock = Mockery::mock('\Box\Mod\Cart\Service');
    $cartServiceMock->shouldReceive('isStockAvailable')
        ->with($modelProduct, 1)
        ->atLeast()->once()
        ->andReturn(true);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire');

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

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getMasterOrderForClient')
        ->atLeast()->once()
        ->andReturn(null);

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->createOrder($modelClient, $modelProduct, ['group_id' => 1]))
        ->toThrow(\FOSSBilling\Exception::class, 'Parent order 1 was not found');
});

test('creates order', function () {
    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());
    $modelClient->currency = 'USD';

    $modelProduct = new \Model_Product();
    $modelProduct->loadBean(new \Tests\Helpers\DummyBean());
    $modelProduct->id = 1;
    $modelProduct->type = 'custom';

    $currencyModel = Mockery::mock('\Box\Mod\Currency\Entity\Currency');
    $currencyModel->shouldReceive('getCode')
        ->atLeast()->once()
        ->andReturn('USD');
    $currencyRepositoryMock = Mockery::mock('\Box\Mod\Currency\Repository\CurrencyRepository');
    $currencyRepositoryMock->shouldReceive('findOneByCode')
        ->atLeast()->once()
        ->andReturn($currencyModel);
    $currencyServiceMock = Mockery::mock('\Box\Mod\Currency\Service');
    $currencyServiceMock->shouldReceive('getCurrencyRepository')
        ->atLeast()->once()
        ->andReturn($currencyRepositoryMock);

    $cartServiceMock = Mockery::mock('\Box\Mod\Cart\Service');
    $cartServiceMock->shouldReceive('isStockAvailable')
        ->with($modelProduct, 1)
        ->atLeast()->once()
        ->andReturn(true);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire');
    $productServiceMock = Mockery::mock(\Box\Mod\Servicecustom\Service::class);

    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->with('ClientOrder')
        ->atLeast()->once()
        ->andReturn($clientOrderModel);
    $newId = 1;
    $dbMock->shouldReceive('store')
        ->with($clientOrderModel)
        ->atLeast()->once()
        ->andReturn($newId);

    $periodMock = Mockery::mock('\Box_Period');
    $periodMock->shouldReceive('getCode')
        ->atLeast()->once()
        ->andReturn('1Y');

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock, $productServiceMock) {
        if ($serviceName == 'currency') {
            return $currencyServiceMock;
        }
        if ($serviceName == 'cart') {
            return $cartServiceMock;
        }
        if ($serviceName == 'servicecustom') {
            return $productServiceMock;
        }
    });
    $di['events_manager'] = $eventMock;
    $di['db'] = $dbMock;
    $di['period'] = $di->protect(fn (): \Mockery\MockInterface => $periodMock);
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);
    $result = $this->service->createOrder($modelClient, $modelProduct, ['period' => '1Y', 'price' => '10', 'notes' => 'test']);
    expect($result)->toBe($newId);
});

test('gets master order for client', function () {
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->with('ClientOrder', 'group_id = :group_id AND group_master = 1 AND client_id = :client_id', \Mockery::type('array'))
        ->atLeast()->once()
        ->andReturn($clientOrderModel);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);
    $result = $this->service->getMasterOrderForClient($clientModel, 1);
    expect($result)->toBeInstanceOf('Model_ClientOrder');
});

test('throws exception when activating non-pending order', function () {
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientOrderModel->status = \Model_ClientOrder::STATUS_CANCELED;

    expect(fn () => $this->service->activateOrder($clientOrderModel))
        ->toThrow(\FOSSBilling\Exception::class, 'Only pending setup or failed orders can be activated');
});

test('activates order', function () {
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientOrderModel->status = \Model_ClientOrder::STATUS_PENDING_SETUP;
    $clientOrderModel->group_master = 1;

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire');

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('createFromOrder')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('activateOrderAddons');

    $serviceMock->setDi($di);
    $result = $serviceMock->activateOrder($clientOrderModel);
    expect($result)->toBeTrue();
});

test('activates order addons', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('createFromOrder')
        ->atLeast()->once()
        ->andReturn([]);

    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientOrderModel->status = \Model_ClientOrder::STATUS_PENDING_SETUP;
    $clientOrderModel->group_master = 1;
    $serviceMock->shouldReceive('getOrderAddonsList')
        ->atLeast()->once()
        ->andReturn([$clientOrderModel]);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire');

    $di = container();
    $di['events_manager'] = $eventMock;

    $serviceMock->setDi($di);
    $result = $serviceMock->activateOrderAddons($clientOrderModel);
    expect($result)->toBeTrue();
});

test('gets order addons list', function () {
    $modelClientOrder = new \Model_ClientOrder();
    $modelClientOrder->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->with('ClientOrder', 'group_id = :group_id AND client_id = :client_id and (group_master = 0 OR group_master IS NULL)', \Mockery::type('array'))
        ->atLeast()->once()
        ->andReturn([new \Model_ClientOrder()]);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getOrderAddonsList($modelClientOrder);
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf('\Model_ClientOrder');
});

test('handles stock sale', function () {
    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->stock_control = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->with($productModel);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->stockSale($productModel, 2);
    expect($result)->toBeTrue();
});

test('updates order', function () {
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->with($clientOrderModel);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

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
    $serviceMock->shouldReceive('updatePeriod')
        ->with($clientOrderModel, $data['period']);
    $serviceMock->shouldReceive('updateOrderMeta')
        ->with($clientOrderModel, $data['meta']);

    $serviceMock->setDi($di);
    $result = $serviceMock->updateOrder($clientOrderModel, $data);
    expect($result)->toBeTrue();
});

test('renews order', function () {
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientOrderModel->group_master = 1;
    $clientOrderModel->status = \Model_ClientOrder::STATUS_PENDING_SETUP;

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire');

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('renewFromOrder')
        ->with($clientOrderModel);
    $serviceMock->shouldReceive('getOrderAddonsList')
        ->atLeast()->once()
        ->andReturn([$clientOrderModel]);

    $serviceMock->setDi($di);
    $result = $serviceMock->renewOrder($clientOrderModel);
    expect($result)->toBeTrue();
});

test('renews from order', function () {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_callOnService')
        ->atLeast()->once();
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientOrderModel->period = '1Y';

    $periodMock = Mockery::mock('\Box_Period');

    $dbMock = Mockery::mock('Box_Database');

    $invoiceServiceMock = Mockery::mock(\Box\Mod\Invoice\Service::class);
    $invoiceServiceMock->shouldReceive('findPaidInvoicesForOrder');

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => []);
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $invoiceServiceMock);
    $di['period'] = $di->protect(fn (): \Mockery\MockInterface => $periodMock);
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $serviceMock->renewFromOrder($clientOrderModel);
});

test('throws exception when suspending non-active order', function () {
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientOrderModel->status = \Model_ClientOrder::STATUS_SUSPENDED;

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire');

    $di = container();
    $di['events_manager'] = $eventMock;

    $this->service->setDi($di);

    expect(fn () => $this->service->suspendFromOrder($clientOrderModel))
        ->toThrow(\FOSSBilling\Exception::class, 'Only active orders can be suspended');
});

test('suspends from order', function () {
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientOrderModel->status = \Model_ClientOrder::STATUS_ACTIVE;

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->with($clientOrderModel);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_callOnService');
    $serviceMock->shouldReceive('saveStatusChange');

    $serviceMock->setDi($di);
    $result = $serviceMock->suspendFromOrder($clientOrderModel);
    expect($result)->toBeTrue();
});

test('removes orders by client', function () {
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

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

    $di = new \Pimple\Container();
    $di['dbal'] = $dbalMock;
    $this->service->setDi($di);
    $this->service->rmByClient($clientModel);

    expect($queryBuilderMock->getDeleteTable())->toBe('client_order');
    expect($queryBuilderMock->getWhereCond())->toBe('client_id = :id');
    expect($queryBuilderMock->getParamId())->toBe($clientModel->id);
});

test('updates period', function () {
    $period = '1Y';
    $di = container();

    $periodMock = Mockery::mock('\Box_Period');
    $periodMock->shouldReceive('getCode');
    $di['period'] = $di->protect(fn (): \Mockery\MockInterface => $periodMock);

    $this->service->setDi($di);
    $clientOrder = new \Model_ClientOrder();
    $clientOrder->loadBean(new \Tests\Helpers\DummyBean());
    $result = $this->service->updatePeriod($clientOrder, $period);
    expect($result)->toBe(1);
});

test('updates period when empty', function () {
    $period = '';
    $di = container();
    $periodMock = Mockery::mock('\Box_Period');
    $periodMock->shouldReceive('getCode')
        ->never();
    $di['period'] = $di->protect(fn (): \Mockery\MockInterface => $periodMock);

    $this->service->setDi($di);
    $clientOrder = new \Model_ClientOrder();
    $clientOrder->loadBean(new \Tests\Helpers\DummyBean());
    $result = $this->service->updatePeriod($clientOrder, $period);
    expect($result)->toBe(2);
});

test('updates period when not set', function () {
    $period = null;
    $di = container();
    $periodMock = Mockery::mock('\Box_Period');
    $periodMock->shouldReceive('getCode')
        ->never();
    $di['period'] = $di->protect(fn (): \Mockery\MockInterface => $periodMock);

    $this->service->setDi($di);
    $clientOrder = new \Model_ClientOrder();
    $clientOrder->loadBean(new \Tests\Helpers\DummyBean());
    $result = $this->service->updatePeriod($clientOrder, $period);
    expect($result)->toBe(0);
});

test('updates order meta when not set', function () {
    $meta = null;
    $clientOrder = new \Model_ClientOrder();
    $clientOrder->loadBean(new \Tests\Helpers\DummyBean());
    $result = $this->service->updateOrderMeta($clientOrder, $meta);
    expect($result)->toBe(0);
});

test('updates order meta when empty', function () {
    $meta = [];
    $di = container();

    $dBMock = Mockery::mock('\Box_Database');
    $dBMock->shouldReceive('exec')
        ->atLeast()->once();
    $di['db'] = $dBMock;

    $this->service->setDi($di);
    $clientOrder = new \Model_ClientOrder();
    $clientOrder->loadBean(new \Tests\Helpers\DummyBean());
    $result = $this->service->updateOrderMeta($clientOrder, $meta);
    expect($result)->toBe(1);
});

test('updates order meta', function () {
    $meta = ['key' => 'value'];
    $di = container();

    $dBMock = Mockery::mock('\Box_Database');
    $dBMock->shouldReceive('findOne')
        ->with('ClientOrderMeta', 'client_order_id = :id AND name = :n', \Mockery::type('array'))
        ->atLeast()->once()
        ->andReturn(null);

    $clientOrderMetaModel = new \Model_ClientOrderMeta();
    $clientOrderMetaModel->loadBean(new \Tests\Helpers\DummyBean());
    $dBMock->shouldReceive('dispense')
        ->with('ClientOrderMeta')
        ->atLeast()->once()
        ->andReturn($clientOrderMetaModel);
    $dBMock->shouldReceive('store')
        ->atLeast()->once();
    $di['db'] = $dBMock;

    $this->service->setDi($di);
    $clientOrder = new \Model_ClientOrder();
    $clientOrder->loadBean(new \Tests\Helpers\DummyBean());
    $result = $this->service->updateOrderMeta($clientOrder, $meta);
    expect($result)->toBe(2);
});
