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

test('getDi returns the dependency injection container', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Admin();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('update throws exception when order is not activated', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Admin();
    $data['order_id'] = 1;
    $model = new \Model_ClientOrder();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()
        ->once()
        ->andReturn($model);

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once();

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $di['validator'] = $validatorMock;
    $api->setDi($di);

    expect(fn () => $api->update($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Order is not activated');
});

test('update updates downloadable product', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Admin();
    $data['order_id'] = 1;
    $model = new \Model_ClientOrder();

    $modelDownloadableModel = new \Model_ServiceDownloadable();

    $serviceMock = Mockery::mock(\Box\Mod\Servicedownloadable\Service::class);
    $serviceMock->shouldReceive('updateProductFile')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn($modelDownloadableModel);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()
        ->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $api->setDi($di);
    $api->setService($serviceMock);
    $result = $api->update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('configSave saves product configuration', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Admin();
    $data = [
        'id' => 1,
        'update_orders' => true,
    ];

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->config = '{"filename": "test.txt"}';

    $serviceMock = Mockery::mock(\Box\Mod\Servicedownloadable\Service::class);
    $serviceMock->shouldReceive('saveProductConfig')
        ->with($productModel, $data)
        ->atLeast()
        ->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->with('Product', $data['id'], 'Product not found')
        ->atLeast()
        ->once()
        ->andReturn($productModel);

    $di = new \Pimple\Container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->config_save($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('configSave throws exception when product not found', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Admin();
    $data = [
        'id' => 999,
        'update_orders' => true,
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->with('Product', $data['id'], 'Product not found')
        ->atLeast()
        ->once()
        ->andThrow(new \FOSSBilling\Exception('Product not found'));

    $di = new \Pimple\Container();
    $di['db'] = $dbMock;

    $api->setDi($di);

    expect(fn () => $api->config_save($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Product not found');
});

test('sendFile throws exception when product not found', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Admin();
    $data = ['id' => 999];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->with('Product', $data['id'], 'Product not found')
        ->atLeast()
        ->once()
        ->andThrow(new \FOSSBilling\Exception('Product not found'));

    $di = new \Pimple\Container();
    $di['db'] = $dbMock;

    $api->setDi($di);

    expect(fn () => $api->send_file($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Product not found');
});

test('sendFile throws exception when no file configured', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Admin();
    $data = ['id' => 1];

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->config = '{}';

    $serviceMock = Mockery::mock(\Box\Mod\Servicedownloadable\Service::class);
    $serviceMock->shouldReceive('sendProductFile')
        ->with($productModel)
        ->atLeast()
        ->once()
        ->andThrow(new \FOSSBilling\Exception('No file associated with this product.', null, 404));

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->with('Product', $data['id'], 'Product not found')
        ->atLeast()
        ->once()
        ->andReturn($productModel);

    $di = new \Pimple\Container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    expect(fn () => $api->send_file($data))
        ->toThrow(\FOSSBilling\Exception::class, 'No file associated with this product.');
});

test('sendFile throws exception when file cannot be downloaded', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Admin();
    $data = ['id' => 1];

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->config = '{"filename": "test.txt"}';

    $serviceMock = Mockery::mock(\Box\Mod\Servicedownloadable\Service::class);
    $serviceMock->shouldReceive('sendProductFile')
        ->with($productModel)
        ->atLeast()
        ->once()
        ->andThrow(new \FOSSBilling\Exception('File cannot be downloaded at the moment. Please contact support.', null, 404));

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->with('Product', $data['id'], 'Product not found')
        ->atLeast()
        ->once()
        ->andReturn($productModel);

    $di = new \Pimple\Container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    expect(fn () => $api->send_file($data))
        ->toThrow(\FOSSBilling\Exception::class, 'File cannot be downloaded at the moment. Please contact support.');
});

test('sendFile sends product file', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Admin();
    $data = ['id' => 1];

    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->config = '{"filename": "test.txt"}';

    $serviceMock = Mockery::mock(\Box\Mod\Servicedownloadable\Service::class);
    $serviceMock->shouldReceive('sendProductFile')
        ->with($productModel)
        ->atLeast()
        ->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->with('Product', $data['id'], 'Product not found')
        ->atLeast()
        ->once()
        ->andReturn($productModel);

    $di = new \Pimple\Container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->send_file($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});
