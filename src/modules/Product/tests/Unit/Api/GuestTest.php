<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Product\Api\Guest;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Service;

use function Tests\Helpers\container;

test('gets dependency injection container', function (): void {
    $api = new Guest();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('gets product with id', function (): void {
    $api = new Guest();
    $data = ['id' => 1];

    $model = new Product();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findOneActiveById')->atLeast()->once()->andReturn($model);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);
    $result = $api->get($data);
    expect($result)->toBeArray();
});

test('gets product with slug', function (): void {
    $api = new Guest();
    $data = ['slug' => 'product/1'];

    $model = new Product();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findOneActiveBySlug')->atLeast()->once()->andReturn($model);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);
    $result = $api->get($data);
    expect($result)->toBeArray();
});

test('throws exception when product not found', function (): void {
    $api = new Guest();
    $data = ['slug' => 'product/1'];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findOneActiveBySlug')->atLeast()->once()->andReturn(null);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    expect(fn () => $api->get($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Product not found');
});

test('gets paginated product list', function (): void {
    $api = new Guest();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getPaginatedProducts')
        ->once()
        ->with(['status' => 'enabled', 'show_hidden' => false])
        ->andReturn(['list' => []]);

    $api->setService($serviceMock);
    $result = $api->get_list([]);
    expect($result)->toBeArray();
});

test('gets category list', function (): void {
    $api = new Guest();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getPaginatedProductCategories')
        ->once()
        ->with(['status' => 'enabled'])
        ->andReturn(['list' => []]);

    $api->setService($serviceMock);
    $result = $api->category_get_list([]);
    expect($result)->toBeArray();
});

test('gets category pairs', function (): void {
    $api = new Guest();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getProductCategoryPairs')->atLeast()->once()->andReturn([]);

    $api->setService($serviceMock);
    $result = $api->category_get_pairs([]);
    expect($result)->toBeArray();
});
