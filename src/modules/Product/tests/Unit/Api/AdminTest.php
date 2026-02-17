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
use Box\Mod\Product\Api\Admin;
use Box\Mod\Product\Service;

beforeEach(function () {
    $api = new Admin();
});

test('gets dependency injection container', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('gets product list', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getProductSearchQuery')
        ->atLeast()->once()
        ->andReturn(['sqlString', []]);

    $pagerMock = Mockery::mock(\FOSSBilling\Pagination::class);
    $pagerMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(50);
    $pagerMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn(['list' => []]);

    $di = container();
    $di['pager'] = $pagerMock;

    $api->setService($serviceMock);
    $api->setDi($di);
    $result = $api->get_list([]);
    expect($result)->toBeArray();
});

test('gets product pairs', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getPairs')
        ->atLeast()->once()
        ->andReturn([]);

    $api->setService($serviceMock);
    $result = $api->get_pairs([]);
    expect($result)->toBeArray();
});

test('gets a product', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['id' => 1];

    $model = new \Model_Product();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $result = $api->get($data);
    expect($result)->toBeArray();
});

test('gets product types', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getTypes')
        ->atLeast()->once()
        ->andReturn([]);

    $api->setService($serviceMock);
    $result = $api->get_types();
    expect($result)->toBeArray();
});

test('throws exception when preparing domain product already created', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = [
        'title' => 'testTitle',
        'type' => 'domain',
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getMainDomainProduct')
        ->atLeast()->once()
        ->andReturn(new \Model_ProductDomain());

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    expect(fn () => $api->prepare($data))
        ->toThrow(\FOSSBilling\Exception::class, 'You have already created domain product');
});

test('throws exception when preparing unrecognized product type', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = [
        'title' => 'testTitle',
        'type' => 'customForTestException',
    ];

    $typeArray = [
        'license' => 'License',
        'domain' => 'Domain',
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getTypes')
        ->atLeast()->once()
        ->andReturn($typeArray);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    expect(fn () => $api->prepare($data))
        ->toThrow(\FOSSBilling\Exception::class, "Product type {$data['type']} is not registered.");
});

test('prepares a product', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = [
        'title' => 'testTitle',
        'type' => 'license',
    ];

    $typeArray = [
        'license' => 'License',
        'domain' => 'Domain',
    ];

    $newProductId = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getTypes')
        ->atLeast()->once()
        ->andReturn($typeArray);

    $serviceMock->shouldReceive('createProduct')
        ->atLeast()->once()
        ->andReturn($newProductId);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->prepare($data);
    expect($result)->toBeInt();
    expect($result)->toBe($newProductId);
});

test('updates a product', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['id' => 1];
    $model = new \Model_Product();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateProduct')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('throws exception when updating priority without priority param', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = [];

    expect(fn () => $api->update_priority($data))
        ->toThrow(\FOSSBilling\Exception::class, 'priority params is missing');
});

test('updates priority', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['priority' => []];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updatePriority')
        ->atLeast()->once()
        ->andReturn(true);

    $api->setService($serviceMock);

    $result = $api->update_priority($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('updates product config', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['id' => 1];
    $model = new \Model_Product();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateConfig')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->update_config($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('gets addon pairs', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getAddons')
        ->atLeast()->once()
        ->andReturn([]);

    $api->setService($serviceMock);
    $result = $api->addon_get_pairs([]);
    expect($result)->toBeArray();
});

test('creates an addon', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['title' => 'Title4test'];
    $newAddonId = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('createAddon')
        ->atLeast()->once()
        ->andReturn($newAddonId);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->addon_create($data);
    expect($result)->toBeInt();
    expect($result)->toBe($newAddonId);
});

test('gets an addon', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['id' => 1];

    $model = new \Model_Product();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->is_addon = true;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->addon_get($data);
    expect($result)->toBeArray();
});

test('updates an addon', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['id' => 1];

    $apiMock = Mockery::mock(Admin::class)->makePartial();
    $apiMock->shouldReceive('update')
        ->atLeast()->once()
        ->andReturn([]);

    $model = new \Model_Product();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->is_addon = true;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $apiMock->setDi($di);

    $result = $apiMock->addon_update($data);
    expect($result)->toBeArray();
});

test('deletes an addon', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $apiMock = Mockery::mock(Admin::class)->makePartial();
    $apiMock->shouldReceive('delete')
        ->atLeast()->once()
        ->andReturn(true);

    $result = $apiMock->addon_delete([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('deletes a product', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['id' => 1];

    $model = new \Model_Product();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('deleteProduct')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;

    $api->setService($serviceMock);
    $api->setDi($di);

    $result = $api->delete($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('gets category pairs', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getProductCategoryPairs')
        ->atLeast()->once()
        ->andReturn([]);

    $api->setService($serviceMock);
    $result = $api->category_get_pairs([]);
    expect($result)->toBeArray();
});

test('updates a category', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['id' => 1];

    $model = new \Model_ProductCategory();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateCategory')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;

    $api->setService($serviceMock);
    $api->setDi($di);

    $result = $api->category_update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('gets a category', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['id' => 1];

    $model = new \Model_ProductCategory();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toProductCategoryApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $api->setService($serviceMock);
    $api->setDi($di);

    $result = $api->category_get($data);
    expect($result)->toBeArray();
});

test('creates a category', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['title' => 'test Title'];
    $newCategoryId = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('createCategory')
        ->atLeast()->once()
        ->andReturn($newCategoryId);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->category_create($data);
    expect($result)->toBeInt();
    expect($result)->toBe($newCategoryId);
});

test('deletes a category', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['id' => 1];

    $model = new \Model_ProductCategory();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('removeProductCategory')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;

    $api->setService($serviceMock);
    $api->setDi($di);

    $result = $api->category_delete($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('gets promo list', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getPromoSearchQuery')
        ->atLeast()->once()
        ->andReturn(['sqlString', []]);

    $pagerMock = Mockery::mock(\FOSSBilling\Pagination::class);
    $pagerMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(50);
    $pagerMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn(['list' => []]);

    $di = container();
    $di['pager'] = $pagerMock;

    $api->setService($serviceMock);
    $api->setDi($di);
    $result = $api->promo_get_list([]);
    expect($result)->toBeArray();
});

test('creates a promo', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = [
        'code' => 'test',
        'type' => 'addon',
        'value' => '10',
        'products' => [],
        'periods' => [],
    ];
    $newPromoId = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('createPromo')
        ->atLeast()->once()
        ->andReturn($newPromoId);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->promo_create($data);
    expect($result)->toBeInt();
    expect($result)->toBe($newPromoId);
});

test('throws exception when getting promo without id', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = [];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andThrow(new \FOSSBilling\InformationException('Promo ID was not passed'));

    $di = container();
    $di['db'] = $dbMock;
    $api->setDi($di);

    expect(fn () => $api->promo_get($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Promo ID was not passed');
});

test('gets a promo', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['id' => 1];

    $model = new \Model_Promo();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toPromoApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $api->setService($serviceMock);
    $api->setDi($di);

    $result = $api->promo_get($data);
    expect($result)->toBeArray();
});

test('updates a promo', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['id' => 1];

    $model = new \Model_Promo();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updatePromo')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $result = $api->promo_update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('deletes a promo', function (): void {
    $api = new \Box\Mod\Product\Api\Admin();
    $data = ['id' => 1];
    $model = new \Model_Promo();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('deletePromo')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $result = $api->promo_delete($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});
