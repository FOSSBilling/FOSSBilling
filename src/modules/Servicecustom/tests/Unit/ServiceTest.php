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
use Box\Mod\Servicecustom\Service;
use Box\Mod\Formbuilder\Service as FormbuilderService;
use Box\Mod\Order\Service as OrderService;

beforeEach(function () {
    $this->service = new Service();
});

afterEach(function () {
    Mockery::close();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

test('validates custom form', function () {
    $form = [
        'fields' => [
            0 => [
                'required' => 1,
                'readonly' => 1,
                'name' => 'field_name',
                'default_value' => 'FieldName',
                'label' => 'label',
            ],
        ],
    ];

    $formbuilderService = Mockery::mock(FormbuilderService::class);
    $formbuilderService->shouldReceive('getForm')
        ->atLeast()->once()
        ->andReturn($form);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $formbuilderService);

    $this->service->setDi($di);

    $product = [
        'form_id' => 1,
    ];
    $data = [
        'label' => 'label',
        'field_name' => 'FieldName',
    ];
    $result = $this->service->validateCustomForm($data, $product);
    expect($result)->toBeNull();
});

test('throws exception when validating custom form with missing field name', function () {
    $form = [
        'fields' => [
            0 => [
                'required' => 1,
                'readonly' => 1,
                'name' => 'field_name',
                'default_value' => 'default',
                'label' => 'label',
            ],
        ],
    ];

    $formbuilderService = Mockery::mock(FormbuilderService::class);
    $formbuilderService->shouldReceive('getForm')
        ->atLeast()->once()
        ->andReturn($form);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $formbuilderService);

    $this->service->setDi($di);

    $product = [
        'form_id' => 1,
    ];
    $data = [];

    expect(fn () => $this->service->validateCustomForm($data, $product))
        ->toThrow(\Exception::class);
});

test('throws exception when validating custom form with readonly field change', function () {
    $form = [
        'fields' => [
            0 => [
                'required' => 1,
                'readonly' => 1,
                'name' => 'field_name',
                'default_value' => 'default',
                'label' => 'label',
            ],
        ],
    ];

    $formbuilderService = Mockery::mock(FormbuilderService::class);
    $formbuilderService->shouldReceive('getForm')
        ->atLeast()->once()
        ->andReturn($form);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $formbuilderService);

    $this->service->setDi($di);

    $product = [
        'form_id' => 1,
    ];
    $data = [
        'field_name' => 'field_name',
    ];

    expect(fn () => $this->service->validateCustomForm($data, $product))
        ->toThrow(\Exception::class);
});

test('creates action', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->product_id = 1;
    $order->client_id = 1;
    $order->config = 'config';

    $product = new \Model_Product();
    $product->loadBean(new \Tests\Helpers\DummyBean());
    $product->plugin = 'plugin';
    $product->plugin_config = 'plugin_config';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($product);
    $serviceCustomModel = new \Model_ServiceCustom();
    $serviceCustomModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($serviceCustomModel);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->action_create($order);
    expect($result)->toBeInstanceOf(\Model_ServiceCustom::class);
});

test('activates action', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new \Model_ServiceCustom();
    $serviceCustomModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn($serviceCustomModel);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $this->service->setDi($di);

    $result = $this->service->action_activate($order);
    expect($result)->toBeTrue();
});

test('throws exception when activating without order service', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $this->service->setDi($di);

    expect(fn () => $this->service->action_activate($order))
        ->toThrow(\Exception::class);
});

test('renews action', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new \Model_ServiceCustom();
    $serviceCustomModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn($serviceCustomModel);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $this->service->setDi($di);

    $result = $this->service->action_renew($order);
    expect($result)->toBeTrue();
});

test('throws exception when renewing without active service', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->id = 1;
    $order->client_id = 1;
    $order->config = 'config';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $this->service->setDi($di);

    expect(fn () => $this->service->action_renew($order))
        ->toThrow(\Exception::class);
});

test('suspends action', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new \Model_ServiceCustom();
    $serviceCustomModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn($serviceCustomModel);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $this->service->setDi($di);

    $result = $this->service->action_suspend($order);
    expect($result)->toBeTrue();
});

test('unsuspends action', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new \Model_ServiceCustom();
    $serviceCustomModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn($serviceCustomModel);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $this->service->setDi($di);

    $result = $this->service->action_unsuspend($order);
    expect($result)->toBeTrue();
});

test('cancels action', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new \Model_ServiceCustom();
    $serviceCustomModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn($serviceCustomModel);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $this->service->setDi($di);

    $result = $this->service->action_cancel($order);
    expect($result)->toBeTrue();
});

test('uncancels action', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new \Model_ServiceCustom();
    $serviceCustomModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn($serviceCustomModel);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $this->service->setDi($di);

    $result = $this->service->action_uncancel($order);
    expect($result)->toBeTrue();
});

test('deletes action', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new \Model_ServiceCustom();
    $serviceCustomModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn($serviceCustomModel);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $this->service->setDi($di);

    $result = $this->service->action_delete($order);
    expect($result)->toBeTrue();
});

test('gets config', function () {
    $decoded = [
        'J' => 5,
        0 => 'N',
    ];

    $di = container();
    $this->service->setDi($di);

    $model = new \Model_ServiceCustom();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->config = json_encode($decoded);

    $result = $this->service->getConfig($model);
    expect($result)->toBe($decoded);
});

test('converts to api array', function () {
    $di = container();
    $this->service->setDi($di);

    $model = new \Model_ServiceCustom();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;
    $model->client_id = 1;
    $model->plugin = 'plugin';
    $model->config = '{"config_param":"config_value"}';
    $model->updated_at = date('Y-m-d H:i:s');
    $model->created_at = date('Y-m-d H:i:s');

    $result = $this->service->toApiArray($model);

    expect($result)->toBeArray();
    expect($result['client_id'])->toBe($model->client_id);
    expect($result['plugin'])->toBe($model->plugin);
    expect($result['config_param'])->toBe('config_value');
    expect($result['updated_at'])->toBe($model->updated_at);
    expect($result['created_at'])->toBe($model->created_at);
});

test('throws exception for forbidden custom call method', function () {
    expect(fn () => $this->service->customCall(new \Model_ServiceCustom(), 'delete'))
        ->toThrow(\Exception::class);
});

test('gets service custom by order id', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn(new \Model_ClientOrder());

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(new \Model_ServiceCustom());

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $this->service->setDi($di);

    $result = $this->service->getServiceCustomByOrderId(1);
    expect($result)->toBeInstanceOf(\Model_ServiceCustom::class);
});

test('throws exception when getting service custom by order id without order service', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn(new \Model_ClientOrder());

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $this->service->setDi($di);

    expect(fn () => $this->service->getServiceCustomByOrderId(1))
        ->toThrow(\Exception::class);
});

test('updates config', function () {
    $model = new \Model_ServiceCustom();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getServiceCustomByOrderId')
        ->atLeast()->once()
        ->andReturn($model);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $config = ['param1' => 'value1'];
    $result = $serviceMock->updateConfig(1, $config);
    expect($result)->toBeNull();
});

test('throws exception when updating config with non-array', function () {
    $model = new \Model_ServiceCustom();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getServiceCustomByOrderId')
        ->never();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->never();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $config = '';

    expect(fn () => $serviceMock->updateConfig(1, $config))
        ->toThrow(\Exception::class);
});
