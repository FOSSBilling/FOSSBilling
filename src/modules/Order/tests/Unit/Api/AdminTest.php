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
use Box\Mod\Order\Api\Admin;
use Box\Mod\Order\Service;

beforeEach(function () {
    $this->api = new Admin();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toBe($di);
});

test('gets an order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $apiMock->setService($serviceMock);

    $data = ['id' => 1];
    $result = $apiMock->get($data);
    expect($result)->toBeArray();
});

test('gets order list', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getSearchQuery')
        ->atLeast()->once()
        ->andReturn(['query', []]);

    $paginatorMock = Mockery::mock(\FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(25);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn(['list' => []]);

    $modMock = Mockery::mock(\FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn(['show_addons' => 0]);

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['mod'] = $di->protect(fn () => $modMock);

    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->get_list([]);
    expect($result)->toBeArray();
});

test('creates an order', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('createOrder')
        ->atLeast()->once()
        ->andReturn(1);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->times(2)
        ->andReturn(new Model_Client(), new Model_Product());

    $di = container();
    $di['db'] = $dbMock;
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $data = [
        'client_id' => 1,
        'product_id' => 1,
    ];
    $result = $this->api->create($data);
    expect($result)->toBeInt();
});

test('updates an order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateOrder')
        ->atLeast()->once()
        ->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = [
        'client_id' => 1,
        'product_id' => 1,
    ];
    $result = $apiMock->update($data);
    expect($result)->toBeTrue();
});

test('activates an order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('activateOrder')
        ->atLeast()->once()
        ->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = [
        'client_id' => 1,
        'product_id' => 1,
    ];
    $result = $apiMock->activate($data);
    expect($result)->toBeTrue();
});

test('renews an order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('renewOrder')
        ->atLeast()->once()
        ->andReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->renew([]);
    expect($result)->toBeTrue();
});

test('renews pending setup order by activating it', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->status = Model_ClientOrder::STATUS_PENDING_SETUP;

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);
    $apiMock->shouldReceive('activate')
        ->atLeast()->once()
        ->andReturn(true);

    $result = $apiMock->renew([]);
    expect($result)->toBeTrue();
});

test('suspends an order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('suspendFromOrder')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $apiMock->setDi($di);
    $apiMock->setService($serviceMock);

    $result = $apiMock->suspend([]);
    expect($result)->toBeTrue();
});

test('unsuspends an order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->status = Model_ClientOrder::STATUS_SUSPENDED;

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('unsuspendFromOrder')
        ->atLeast()->once()
        ->andReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->unsuspend([]);
    expect($result)->toBeTrue();
});

test('throws exception when unsuspending non-suspended order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->status = Model_ClientOrder::STATUS_ACTIVE;

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('unsuspendFromOrder')
        ->never()
        ->andReturn(true);

    $apiMock->setService($serviceMock);

    expect(fn () => $apiMock->unsuspend([]))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('cancels an order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('cancelFromOrder')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $apiMock->setDi($di);
    $apiMock->setService($serviceMock);

    $result = $apiMock->cancel([]);
    expect($result)->toBeTrue();
});

test('uncancels an order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->status = Model_ClientOrder::STATUS_CANCELED;

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('uncancelFromOrder')
        ->atLeast()->once()
        ->andReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->uncancel([]);
    expect($result)->toBeTrue();
});

test('throws exception when uncanceling non-canceled order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->status = Model_ClientOrder::STATUS_ACTIVE;

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('uncancelFromOrder')
        ->never()
        ->andReturn(true);

    $apiMock->setService($serviceMock);

    expect(fn () => $apiMock->uncancel([]))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('deletes an order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('deleteFromOrder')
        ->atLeast()->once()
        ->andReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->delete([]);
    expect($result)->toBeTrue();
});

test('deletes an order with addons', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('deleteFromOrder')
        ->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('getOrderAddonsList')
        ->atLeast()->once()
        ->andReturn([new Model_ClientOrder()]);

    $apiMock->setService($serviceMock);

    $data = ['delete_addons' => true];
    $result = $apiMock->delete($data);
    expect($result)->toBeTrue();
});

test('batch suspends expired orders', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('batchSuspendExpired')
        ->atLeast()->once()
        ->andReturn(true);

    $this->api->setService($serviceMock);

    $result = $this->api->batch_suspend_expired([]);
    expect($result)->toBeTrue();
});

test('batch cancels suspended orders', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('batchCancelSuspended')
        ->atLeast()->once()
        ->andReturn(true);

    $this->api->setService($serviceMock);

    $result = $this->api->batch_cancel_suspended([]);
    expect($result)->toBeTrue();
});

test('updates order config', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateOrderConfig')
        ->atLeast()->once()
        ->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = ['config' => []];
    $result = $apiMock->update_config($data);
    expect($result)->toBeTrue();
});

test('throws exception when updating config without config param', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateOrderConfig')
        ->never()
        ->andReturn(true);

    $apiMock->setService($serviceMock);

    expect(fn () => $apiMock->update_config([]))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('gets order service data', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getOrderServiceData')
        ->atLeast()->once()
        ->andReturn([]);

    $admin = new Model_Admin();
    $admin->loadBean(new \RedBeanPHP\OODBBean());

    $apiMock->setService($serviceMock);
    $apiMock->setIdentity($admin);

    $data = ['id' => 1];
    $result = $apiMock->service($data);
    expect($result)->toBeArray();
});

test('gets status history list', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getOrderStatusSearchQuery')
        ->atLeast()->once()
        ->andReturn(['query', []]);

    $paginatorMock = Mockery::mock(\FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(25);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['pager'] = $paginatorMock;

    $apiMock->setDi($di);
    $apiMock->setService($serviceMock);

    $result = $apiMock->status_history_get_list([]);
    expect($result)->toBeArray();
});

test('adds status history', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('orderStatusAdd')
        ->atLeast()->once()
        ->andReturn(true);

    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $di = container();
    $apiMock->setDi($di);
    $apiMock->setService($serviceMock);

    $data = ['status' => Model_ClientOrder::STATUS_ACTIVE];
    $result = $apiMock->status_history_add($data);
    expect($result)->toBeTrue();
});

test('deletes status history', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('orderStatusRm')
        ->atLeast()->once()
        ->andReturn(true);

    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $data = ['id' => 1];
    $result = $this->api->status_history_delete($data);
    expect($result)->toBeTrue();
});

test('gets order statuses', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('counter')
        ->atLeast()->once()
        ->andReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->get_statuses();
    expect($result)->toBeArray();
});

test('gets invoice options', function () {
    $result = $this->api->get_invoice_options([]);
    expect($result)->toBeArray();
});

test('gets status pairs', function () {
    $result = $this->api->get_status_pairs([]);
    expect($result)->toBeArray();
});

test('gets order addons', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getOrderAddonsList')
        ->atLeast()->once()
        ->andReturn([new Model_ClientOrder()]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $apiMock->setService($serviceMock);

    $data = ['status' => Model_ClientOrder::STATUS_ACTIVE];
    $result = $apiMock->addons($data);
    expect($result)->toBeArray();
    expect($result[0])->toBeArray();
});

test('gets order with validation', function () {
    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray');

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($order);

    $di = container();
    $di['validator'] = $validatorMock;
    $di['db'] = $dbMock;
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $data = ['id' => 1];
    $this->api->get($data);
});

test('batch deletes orders', function () {
    $activityMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $activityMock->shouldReceive('delete')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $activityMock->setDi($di);

    $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
    expect($result)->toBeTrue();
});
