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
use Box\Mod\Servicedownloadable\Service;
use Box\Mod\Order\Service as OrderService;

beforeEach(function () {
    $this->service = new Service();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

test('attaches order config', function () {
    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->config = '{"filename" : "temp/asdcxTest.txt"}';

    $data = [];

    $expected = array_merge(json_decode($productModel->config ?? '', true), $data);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')
        ->zeroOrMoreTimes();

    $di = container();
    $di['validator'] = $validatorMock;
    $this->service->setDi($di);
    $result = $this->service->attachOrderConfig($productModel, $data);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('creates action', function () {
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientOrderModel->config = '{"filename" : "temp/asdcxTest.txt"}';

    $model = new \Model_ServiceDownloadable();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($model);

    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')
        ->zeroOrMoreTimes();

    $di = container();
    $di['db'] = $dbMock;
    $di['validator'] = $validatorMock;

    $this->service->setDi($di);
    $result = $this->service->action_create($clientOrderModel);
    expect($result)->toBeInstanceOf(\Model_ServiceDownloadable::class);
});

test('deletes action', function () {
    $clientOrderModel = new \Model_ClientOrder();

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(new \Model_ServiceDownloadable());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $this->service->setDi($di);
    $this->service->action_delete($clientOrderModel);
});

test('saves product config', function () {
    $data = [
        'update_orders' => true,
    ];

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->config = '{"filename": "test.txt"}';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->with($productModel)
        ->andReturn(1);

    $di = new \Pimple\Container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->saveProductConfig($productModel, $data);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();

    $updatedConfig = json_decode($productModel->config, true);
    expect($updatedConfig)->toBeArray();
    expect($updatedConfig['filename'])->toBe('test.txt');
    expect($updatedConfig['update_orders'])->toBeTrue();
    expect($productModel->updated_at)->not->toBeNull();
});

test('saves product config with existing config', function () {
    $data = [
        'update_orders' => false,
    ];

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->config = '{"filename": "existing.txt", "update_orders": true}';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->with($productModel)
        ->andReturn(1);

    $di = new \Pimple\Container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->saveProductConfig($productModel, $data);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();

    $updatedConfig = json_decode($productModel->config, true);
    expect($updatedConfig)->toBeArray();
    expect($updatedConfig['filename'])->toBe('existing.txt');
    expect($updatedConfig['update_orders'])->toBeFalse();
    expect($productModel->updated_at)->not->toBeNull();
});

test('saves product config with no existing config', function () {
    $data = [
        'update_orders' => true,
    ];

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->config = null;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->with($productModel)
        ->andReturn(1);

    $di = new \Pimple\Container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->saveProductConfig($productModel, $data);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();

    $updatedConfig = json_decode((string) $productModel->config, true);
    expect($updatedConfig)->toBeArray();
    expect($updatedConfig['update_orders'])->toBeTrue();
    expect($productModel->updated_at)->not->toBeNull();
});
