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
use Box\Mod\Product\Api\Guest;
use Box\Mod\Product\Service;

beforeEach(function () {
    $this->api = new Guest();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toBe($di);
});

test('gets product with id', function () {
    $data = ['id' => 1];

    $model = new \Model_Product();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findOneActiveById')
        ->atLeast()->once()
        ->andReturn($model);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $this->api->setDi($di);
    $this->api->setService($serviceMock);
    $result = $this->api->get($data);
    expect($result)->toBeArray();
});

test('gets product with slug', function () {
    $data = ['slug' => 'product/1'];

    $model = new \Model_Product();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findOneActiveBySlug')
        ->atLeast()->once()
        ->andReturn($model);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $this->api->setDi($di);
    $this->api->setService($serviceMock);
    $result = $this->api->get($data);
    expect($result)->toBeArray();
});

test('throws exception when product not found', function () {
    $data = ['slug' => 'product/1'];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findOneActiveBySlug')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    expect(fn () => $this->api->get($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Product not found');
});

test('gets category list', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getProductCategorySearchQuery')
        ->atLeast()->once()
        ->andReturn(['sqlString', []]);
    $serviceMock->shouldReceive('toProductCategoryApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $pager = [
        'list' => [
            0 => ['id' => 1],
        ],
    ];
    $pagerMock = Mockery::mock(\FOSSBilling\Pagination::class);
    $pagerMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(50);
    $pagerMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn($pager);

    $modelProductCategory = new \Model_ProductCategory();
    $modelProductCategory->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($modelProductCategory);

    $di = container();
    $di['db'] = $dbMock;
    $di['pager'] = $pagerMock;

    $this->api->setService($serviceMock);
    $this->api->setDi($di);
    $result = $this->api->category_get_list([]);
    expect($result)->toBeArray();
});

test('gets category pairs', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getProductCategoryPairs')
        ->atLeast()->once()
        ->andReturn([]);

    $this->api->setService($serviceMock);
    $result = $this->api->category_get_pairs([]);
    expect($result)->toBeArray();
});

test('gets slider with empty list', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $this->api->setDi($di);

    $result = $this->api->get_slider([]);
    expect($result)->toBeArray();
    expect($result)->toBe([]);
});

test('gets slider', function () {
    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$productModel]);

    $di = container();
    $di['db'] = $dbMock;

    $this->api->setDi($di);

    $arr = [
        'id' => 1,
        'slug' => '/',
        'title' => 'New Item',
        'pricing' => '1W',
        'config' => [],
    ];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($arr);

    $this->api->setService($serviceMock);
    $result = $this->api->get_slider([]);
    expect($result)->toBeArray();
});

test('gets slider in json format', function () {
    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$productModel]);

    $di = container();
    $di['db'] = $dbMock;

    $this->api->setDi($di);

    $arr = [
        'id' => 1,
        'slug' => '/',
        'title' => 'New Item',
        'pricing' => '1W',
        'config' => [],
    ];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($arr);

    $this->api->setService($serviceMock);
    $result = $this->api->get_slider([]);
    expect($result)->toBeArray();

    $result = $this->api->get_slider(['format' => 'json']);
    expect($result)->toBeString();
    expect(json_decode($result ?? '', true))->toBeArray();
});
