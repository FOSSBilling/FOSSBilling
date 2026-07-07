<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Formbuilder\Service as FormbuilderService;
use Box\Mod\Order\Service as OrderService;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Service as ProductService;
use Box\Mod\Servicecustom\Service;

use function Tests\Helpers\container;

test('di returns dependency injection container', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toEqual($di);
});

test('validate custom form', function (): void {
    $service = new Service();
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
    $formbuilderService->shouldReceive('getForm')->atLeast()->once()->andReturn($form);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $formbuilderService);

    $service->setDi($di);

    $product = [
        'form_id' => 1,
    ];
    $data = [
        'label' => 'label',
        'field_name' => 'FieldName',
    ];
    $result = $service->validateCustomForm($data, $product);
    expect($result)->toBeNull();
});

test('validate custom form field name not set exception', function (): void {
    $service = new Service();
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
    $formbuilderService->shouldReceive('getForm')->atLeast()->once()->andReturn($form);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $formbuilderService);

    $service->setDi($di);

    $product = [
        'form_id' => 1,
    ];
    $data = [];

    expect(fn () => $service->validateCustomForm($data, $product))
        ->toThrow(Exception::class);
});

test('validate custom form readonly field change exception', function (): void {
    $service = new Service();
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
    $formbuilderService->shouldReceive('getForm')->atLeast()->once()->andReturn($form);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $formbuilderService);

    $service->setDi($di);

    $product = [
        'form_id' => 1,
    ];
    $data = [
        'field_name' => 'field_name',
    ];

    expect(fn () => $service->validateCustomForm($data, $product))
        ->toThrow(Exception::class);
});

test('validate custom form invalid url exception', function (): void {
    $service = new Service();
    $form = [
        'fields' => [
            0 => [
                'type' => 'url',
                'name' => 'website',
                'label' => 'Website',
                'required' => 0,
            ],
        ],
    ];

    $formbuilderService = Mockery::mock(FormbuilderService::class);
    $formbuilderService->shouldReceive('getForm')->atLeast()->once()->andReturn($form);
    $formbuilderService->shouldReceive('validateUrlField')->atLeast()->once()->andReturn(false);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $formbuilderService);

    $service->setDi($di);

    $product = [
        'form_id' => 1,
    ];
    $data = [
        'website' => 'invalid-url',
    ];

    expect(fn () => $service->validateCustomForm($data, $product))
        ->toThrow(FOSSBilling\InformationException::class, 'Field Website must be a valid URL with a TLD (e.g., https://example.com)');
});

test('validate custom form valid url', function (): void {
    $service = new Service();
    $form = [
        'fields' => [
            0 => [
                'type' => 'url',
                'name' => 'website',
                'label' => 'Website',
                'required' => 0,
            ],
        ],
    ];

    $formbuilderService = Mockery::mock(FormbuilderService::class);
    $formbuilderService->shouldReceive('getForm')->atLeast()->once()->andReturn($form);
    $formbuilderService->shouldReceive('validateUrlField')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $formbuilderService);

    $service->setDi($di);

    $product = [
        'form_id' => 1,
    ];
    $data = [
        'website' => 'https://example.com',
    ];

    $result = $service->validateCustomForm($data, $product);
    expect($result)->toBeNull();
});

test('validate custom form url array input throws information exception', function (): void {
    $service = new Service();
    $form = [
        'fields' => [
            0 => [
                'type' => 'url',
                'name' => 'website',
                'label' => 'Website',
                'required' => 0,
            ],
        ],
    ];

    $formbuilderService = Mockery::mock(FormbuilderService::class);
    $formbuilderService->shouldReceive('getForm')->once()->andReturn($form);
    $formbuilderService->shouldNotReceive('validateUrlField');

    $di = container();
    $di['mod_service'] = $di->protect(function (string $module) use ($formbuilderService): Mockery\MockInterface {
        if ($module !== 'formbuilder') {
            throw new InvalidArgumentException(sprintf('Unexpected module requested: %s', $module));
        }

        return $formbuilderService;
    });

    $service->setDi($di);

    $product = [
        'form_id' => 1,
    ];
    $data = [
        'website' => ['x'],
    ];

    expect(fn () => $service->validateCustomForm($data, $product))
        ->toThrow(FOSSBilling\InformationException::class, 'Field Website must be a valid URL with a TLD (e.g., https://example.com)');
});

test('action create', function (): void {
    $service = new Service();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->product_id = 1;
    $order->client_id = 1;
    $order->config = 'config';

    $product = new Product();
    $product->setPlugin('plugin');
    $product->setPluginConfig('plugin_config');

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn(1);
    $serviceCustomModel = new Model_ServiceCustom();
    $serviceCustomModel->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('dispense')->atLeast()->once()->andReturn($serviceCustomModel);

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('findProductById')->once()->with(1)->andReturn($product);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function (string $service) use ($productService): Mockery\MockInterface {
        if ($service === 'product') {
            return $productService;
        }

        throw new RuntimeException('Unexpected service request');
    });
    $service->setDi($di);

    $result = $service->action_create($order);
    expect($result)->toBeInstanceOf(Model_ServiceCustom::class);
});

test('action activate', function (): void {
    $service = new Service();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new Model_ServiceCustom();
    $serviceCustomModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $serviceMock = Mockery::mock(OrderService::class);
    $serviceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceCustomModel);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);
    $service->setDi($di);

    $result = $service->action_activate($order);
    expect($result)->toBeTrue();
});

test('action activate order service not created exception', function (): void {
    $service = new Service();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceMock = Mockery::mock(OrderService::class);
    $serviceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);
    $service->setDi($di);

    expect(fn (): bool => $service->action_activate($order))
        ->toThrow(Exception::class);
});

test('action renew', function (): void {
    $service = new Service();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new Model_ServiceCustom();
    $serviceCustomModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $serviceMock = Mockery::mock(OrderService::class);
    $serviceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceCustomModel);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);
    $service->setDi($di);

    $result = $service->action_renew($order);
    expect($result)->toBeTrue();
});

test('active service not found exception', function (): void {
    $service = new Service();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->id = 1;
    $order->client_id = 1;
    $order->config = 'config';

    $serviceMock = Mockery::mock(OrderService::class);
    $serviceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);
    $service->setDi($di);

    expect(fn (): bool => $service->action_renew($order))
        ->toThrow(Exception::class);
});

test('action suspend', function (): void {
    $service = new Service();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new Model_ServiceCustom();
    $serviceCustomModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $serviceMock = Mockery::mock(OrderService::class);
    $serviceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceCustomModel);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);
    $service->setDi($di);

    $result = $service->action_suspend($order);
    expect($result)->toBeTrue();
});

test('action unsuspend', function (): void {
    $service = new Service();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new Model_ServiceCustom();
    $serviceCustomModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $serviceMock = Mockery::mock(OrderService::class);
    $serviceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceCustomModel);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);
    $service->setDi($di);

    $result = $service->action_unsuspend($order);
    expect($result)->toBeTrue();
});

test('action cancel', function (): void {
    $service = new Service();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new Model_ServiceCustom();
    $serviceCustomModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $serviceMock = Mockery::mock(OrderService::class);
    $serviceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceCustomModel);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);
    $service->setDi($di);

    $result = $service->action_cancel($order);
    expect($result)->toBeTrue();
});

test('action uncancel', function (): void {
    $service = new Service();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new Model_ServiceCustom();
    $serviceCustomModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $serviceMock = Mockery::mock(OrderService::class);
    $serviceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceCustomModel);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);
    $service->setDi($di);

    $result = $service->action_uncancel($order);
    expect($result)->toBeTrue();
});

test('action delete', function (): void {
    $service = new Service();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $order->config = 'config';

    $serviceCustomModel = new Model_ServiceCustom();
    $serviceCustomModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceCustomModel->plugin = '';

    $serviceMock = Mockery::mock(OrderService::class);
    $serviceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceCustomModel);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('trash')->atLeast()->once()->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);
    $service->setDi($di);

    $result = $service->action_delete($order);
    expect($result)->toBeTrue();
});

test('get config', function (): void {
    $service = new Service();
    $decoded = [
        'J' => 5,
        0 => 'N',
    ];

    $di = container();
    $service->setDi($di);

    $model = new Model_ServiceCustom();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->config = json_encode($decoded);

    $result = $service->getConfig($model);

    expect($result)->toEqual($decoded);
});

test('to api array', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);

    $model = new Model_ServiceCustom();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->id = 1;
    $model->client_id = 1;
    $model->plugin = 'plugin';
    $model->config = '{"config_param":"config_value"}';
    $model->updated_at = date('Y-m-d H:i:s');
    $model->created_at = date('Y-m-d H:i:s');

    $result = $service->toApiArray($model);

    expect($result['client_id'])->toEqual($model->client_id);
    expect($result['plugin'])->toEqual($model->plugin);
    expect($result['config_param'])->toEqual('config_value');
    expect($result['updated_at'])->toEqual($model->updated_at);
    expect($result['created_at'])->toEqual($model->created_at);
});

test('custom call forbidden method exception', function (): void {
    $service = new Service();

    expect(fn () => $service->customCall(new Model_ServiceCustom(), 'delete'))
        ->toThrow(Exception::class);
});

test('get service custom by order id', function (): void {
    $service = new Service();
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn(new Model_ClientOrder());

    $orderService = Mockery::mock(OrderService::class);
    $orderService->shouldReceive('getOrderService')->atLeast()->once()->andReturn(new Model_ServiceCustom());

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderService);
    $service->setDi($di);

    $result = $service->getServiceCustomByOrderId(1);

    expect($result)->toBeInstanceOf(Model_ServiceCustom::class);
});

test('get service custom by order id rejects order owned by another client', function (): void {
    $service = new Service();
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->once()->with('ClientOrder', 'id = ? AND client_id = ?', [1, 42])->andReturn(null);
    $dbMock->shouldNotReceive('getExistingModelById');

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    expect(fn () => $service->getServiceCustomByOrderId(1, 42))
        ->toThrow(FOSSBilling\InformationException::class, 'Order not found');
});

test('get service custom by order id order service not found exception', function (): void {
    $service = new Service();
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn(new Model_ClientOrder());

    $orderService = Mockery::mock(OrderService::class);
    $orderService->shouldReceive('getOrderService')->atLeast()->once()->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderService);
    $service->setDi($di);

    expect(fn () => $service->getServiceCustomByOrderId(1))
        ->toThrow(Exception::class);
});

test('update config', function (): void {
    $model = new Model_ServiceCustom();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->id = 1;

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getServiceCustomByOrderId')->atLeast()->once()->andReturn($model);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();
    $serviceMock->setDi($di);

    $config = ['param1' => 'value1'];
    $result = $serviceMock->updateConfig(1, $config);
    expect($result)->toBeNull();
});

test('update config not array exception', function (): void {
    $model = new Model_ServiceCustom();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->id = 1;

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldNotReceive('getServiceCustomByOrderId');

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldNotReceive('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();
    $serviceMock->setDi($di);

    $config = '';

    expect(fn () => $serviceMock->updateConfig(1, $config))
        ->toThrow(Exception::class);
});
