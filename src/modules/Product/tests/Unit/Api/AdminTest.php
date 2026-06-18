<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Product\Api\Admin;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\ProductCategory;
use Box\Mod\Product\Entity\Promo;
use Box\Mod\Product\Service;

use function Tests\Helpers\container;

test('gets dependency injection container', function (): void {
    $api = new Admin();
    $di = container();
    $api->setDi($di);
    expect($api->getDi())->toBe($di);
});

test('gets product list', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getPaginatedProducts')->once()->with([], null)->andReturn(['list' => []]);

    $api->setService($serviceMock);
    $api->setDi(container());
    expect($api->get_list([]))->toBeArray();
});

test('gets product pairs', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getPairs')->atLeast()->once()->andReturn([]);

    $api->setService($serviceMock);
    expect($api->get_pairs([]))->toBeArray();
});

test('gets a product', function (): void {
    $api = new Admin();
    $data = ['id' => 1];
    $model = new Product();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findProductById')->once()->with(1)->andReturn($model);
    $serviceMock->shouldReceive('toApiArray')->once()->andReturn([]);

    $api->setDi(container());
    $api->setService($serviceMock);
    expect($api->get($data))->toBeArray();
});

test('gets product types', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getTypes')->atLeast()->once()->andReturn([]);

    $api->setService($serviceMock);
    expect($api->get_types())->toBeArray();
});

test('throws exception when preparing domain product already created', function (): void {
    $api = new Admin();
    $data = ['title' => 'testTitle', 'type' => 'domain'];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getMainDomainProduct')->atLeast()->once()->andReturn((new Product())->setType('domain'));

    $api->setDi(container());
    $api->setService($serviceMock);

    expect(fn () => $api->prepare($data))
        ->toThrow(FOSSBilling\Exception::class, 'You have already created domain product');
});

test('throws exception when preparing unrecognized product type', function (): void {
    $api = new Admin();
    $data = ['title' => 'testTitle', 'type' => 'customForTestException'];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getTypes')->atLeast()->once()->andReturn(['license' => 'License', 'domain' => 'Domain']);

    $api->setDi(container());
    $api->setService($serviceMock);

    expect(fn () => $api->prepare($data))
        ->toThrow(FOSSBilling\Exception::class, "Product type {$data['type']} is not registered.");
});

test('prepares a product', function (): void {
    $api = new Admin();
    $data = ['title' => 'testTitle', 'type' => 'license'];
    $newProductId = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getTypes')->atLeast()->once()->andReturn(['license' => 'License', 'domain' => 'Domain']);
    $serviceMock->shouldReceive('createProduct')->atLeast()->once()->andReturn($newProductId);

    $api->setDi(container());
    $api->setService($serviceMock);

    expect($api->prepare($data))->toBe($newProductId);
});

test('updates a product', function (): void {
    $api = new Admin();
    $data = ['id' => 1];
    $model = new Product();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findProductById')->once()->with(1)->andReturn($model);
    $serviceMock->shouldReceive('updateProduct')->once()->andReturn(true);

    $api->setDi(container());
    $api->setService($serviceMock);

    expect($api->update($data))->toBeTrue();
});

test('throws exception when updating priority without priority param', function (): void {
    $api = new Admin();
    $api->setDi(container());

    expect(fn () => $api->update_priority([]))
        ->toThrow(FOSSBilling\Exception::class, 'priority params is missing');
});

test('updates priority', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updatePriority')->atLeast()->once()->andReturn(true);

    $api->setService($serviceMock);
    expect($api->update_priority(['priority' => []]))->toBeTrue();
});

test('updates product config', function (): void {
    $api = new Admin();
    $data = ['id' => 1];
    $model = new Product();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findProductById')->once()->with(1)->andReturn($model);
    $serviceMock->shouldReceive('updateConfig')->once()->andReturn(true);

    $api->setDi(container());
    $api->setService($serviceMock);

    expect($api->update_config($data))->toBeTrue();
});

test('gets addon pairs', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getAddons')->atLeast()->once()->andReturn([]);

    $api->setService($serviceMock);
    expect($api->addon_get_pairs([]))->toBeArray();
});

test('creates an addon', function (): void {
    $api = new Admin();
    $data = ['title' => 'Title4test'];
    $newAddonId = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('createAddon')->atLeast()->once()->andReturn($newAddonId);

    $api->setDi(container());
    $api->setService($serviceMock);

    expect($api->addon_create($data))->toBe($newAddonId);
});

test('gets an addon', function (): void {
    $api = new Admin();
    $data = ['id' => 1];

    $model = new Product();
    $reflection = new ReflectionProperty($model, 'isAddon');
    $reflection->setAccessible(true);
    $reflection->setValue($model, true);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findProductById')->once()->with(1)->andReturn($model);
    $serviceMock->shouldReceive('toApiArray')->once()->andReturn([]);

    $api->setDi(container());
    $api->setService($serviceMock);

    expect($api->addon_get($data))->toBeArray();
});

test('updates an addon', function (): void {
    $data = ['id' => 1];

    $apiMock = Mockery::mock(Admin::class . '[update]');
    $apiMock->shouldReceive('update')->atLeast()->once()->andReturn([]);

    $model = new Product();
    $reflection = new ReflectionProperty($model, 'isAddon');
    $reflection->setAccessible(true);
    $reflection->setValue($model, true);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findProductById')->once()->with(1)->andReturn($model);

    $di = container();
    $di['logger'] = new Box_Log();

    $apiMock->setService($serviceMock);
    $apiMock->setDi($di);

    expect($apiMock->addon_update($data))->toBeArray();
});

test('deletes an addon', function (): void {
    $apiMock = Mockery::mock(Admin::class . '[delete]');
    $apiMock->shouldReceive('delete')->atLeast()->once()->andReturn(true);

    expect($apiMock->addon_delete([]))->toBeTrue();
});

test('deletes a product', function (): void {
    $api = new Admin();
    $data = ['id' => 1];
    $model = new Product();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findProductById')->once()->with(1)->andReturn($model);
    $serviceMock->shouldReceive('deleteProduct')->once()->andReturn(true);

    $api->setService($serviceMock);
    $api->setDi(container());

    expect($api->delete($data))->toBeTrue();
});

test('gets category pairs', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getProductCategoryPairs')->atLeast()->once()->andReturn([]);

    $api->setService($serviceMock);
    expect($api->category_get_pairs([]))->toBeArray();
});

test('updates a category', function (): void {
    $api = new Admin();
    $data = ['id' => 1];
    $model = new ProductCategory();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findProductCategoryById')->once()->with(1)->andReturn($model);
    $serviceMock->shouldReceive('updateCategory')->once()->andReturn(true);

    $api->setService($serviceMock);
    $api->setDi(container());

    expect($api->category_update($data))->toBeTrue();
});

test('gets a category', function (): void {
    $api = new Admin();
    $data = ['id' => 1];
    $model = new ProductCategory();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findProductCategoryById')->once()->with(1)->andReturn($model);
    $serviceMock->shouldReceive('toProductCategoryApiArray')->once()->andReturn([]);

    $api->setService($serviceMock);
    $api->setDi(container());

    expect($api->category_get($data))->toBeArray();
});

test('creates a category', function (): void {
    $api = new Admin();
    $data = ['title' => 'test Title'];
    $newCategoryId = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('createCategory')->atLeast()->once()->andReturn($newCategoryId);

    $api->setDi(container());
    $api->setService($serviceMock);

    expect($api->category_create($data))->toBe($newCategoryId);
});

test('deletes a category', function (): void {
    $api = new Admin();
    $data = ['id' => 1];
    $model = new ProductCategory();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findProductCategoryById')->once()->with(1)->andReturn($model);
    $serviceMock->shouldReceive('removeProductCategory')->once()->andReturn(true);

    $api->setService($serviceMock);
    $api->setDi(container());

    expect($api->category_delete($data))->toBeTrue();
});

test('gets promo list', function (): void {
    $api = new Admin();
    $qbMock = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $promo = ['id' => 1];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getPromoSearchQueryBuilder')->atLeast()->once()->andReturn($qbMock);
    $serviceMock->shouldReceive('enrichPromoApiArray')->once()->with($promo)->andReturn(['id' => 1]);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class);
    $pagerMock->shouldReceive('paginateDoctrineQuery')->atLeast()->once()->andReturn(['list' => [$promo]]);

    $di = container();
    $di['pager'] = $pagerMock;

    $api->setService($serviceMock);
    $api->setDi($di);
    expect($api->promo_get_list([]))->toBeArray();
});

test('creates a promo', function (): void {
    $api = new Admin();
    $data = [
        'code' => 'test',
        'type' => 'addon',
        'value' => '10',
        'products' => [],
        'periods' => [],
    ];
    $newPromoId = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('createPromo')->atLeast()->once()->andReturn($newPromoId);

    $api->setDi(container());
    $api->setService($serviceMock);

    expect($api->promo_create($data))->toBe($newPromoId);
});

test('gets a promo without explicit id delegates to service', function (): void {
    $api = new Admin();
    $promo = new Promo();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findPromoById')->atLeast()->once()->andReturn($promo);
    $serviceMock->shouldReceive('toPromoApiArray')->atLeast()->once()->andReturn([]);

    $api->setService($serviceMock);
    $api->setDi(container());

    expect($api->promo_get([]))->toBeArray();
});

test('gets a promo', function (): void {
    $api = new Admin();
    $data = ['id' => 1];
    $promo = new Promo();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findPromoById')->atLeast()->once()->with(1)->andReturn($promo);
    $serviceMock->shouldReceive('toPromoApiArray')->atLeast()->once()->andReturn([]);

    $api->setService($serviceMock);
    $api->setDi(container());

    expect($api->promo_get($data))->toBeArray();
});

test('gets promo redemption list', function (): void {
    $api = new Admin();
    $qbMock = Mockery::mock(Doctrine\ORM\QueryBuilder::class);

    $repoMock = Mockery::mock(Box\Mod\Product\Repository\PromoRedemptionRepository::class);
    $repoMock->shouldReceive('getSearchQueryBuilder')->atLeast()->once()->andReturn($qbMock);

    $serviceMock = Mockery::mock(Service::class . '[getPromoRedemptionRepository,enrichPromoRedemptionApiArray]');
    $serviceMock->shouldReceive('getPromoRedemptionRepository')->atLeast()->once()->andReturn($repoMock);
    $serviceMock->shouldReceive('enrichPromoRedemptionApiArray')->atLeast()->once()->andReturn([]);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class);
    $pagerMock->shouldReceive('paginateDoctrineQuery')->atLeast()->once()->andReturn(['list' => [[]]]);

    $di = container();
    $di['pager'] = $pagerMock;

    $api->setService($serviceMock);
    $api->setDi($di);

    expect($api->promo_redemption_get_list(['promo_id' => 1]))->toBeArray();
});

test('updates a promo', function (): void {
    $api = new Admin();
    $data = ['id' => 1];
    $model = new Promo();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findPromoById')->atLeast()->once()->with(1)->andReturn($model);
    $serviceMock->shouldReceive('updatePromo')->atLeast()->once()->with($model, $data)->andReturn(true);

    $api->setDi(container());
    $api->setService($serviceMock);
    expect($api->promo_update($data))->toBeTrue();
});

test('deletes a promo', function (): void {
    $api = new Admin();
    $data = ['id' => 1];
    $model = new Promo();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findPromoById')->atLeast()->once()->with(1)->andReturn($model);
    $serviceMock->shouldReceive('deletePromo')->atLeast()->once()->with($model)->andReturn(true);

    $api->setDi(container());
    $api->setService($serviceMock);
    expect($api->promo_delete($data))->toBeTrue();
});
