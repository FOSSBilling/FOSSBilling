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

    $model = new Model_Product();

    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('findOneActiveById');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($model);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $serviceMock->shouldReceive('toApiArray');
    $expectation2->atLeast()->once();
    $expectation2->andReturn([]);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);
    $result = $api->get($data);
    expect($result)->toBeArray();
});

test('gets product with slug', function (): void {
    $api = new Guest();
    $data = ['slug' => 'product/1'];

    $model = new Model_Product();

    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('findOneActiveBySlug');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($model);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $serviceMock->shouldReceive('toApiArray');
    $expectation2->atLeast()->once();
    $expectation2->andReturn([]);

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
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('findOneActiveBySlug');
    $expectation->atLeast()->once();
    $expectation->andReturn(null);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    expect(fn () => $api->get($data))
        ->toThrow(FOSSBilling\Exception::class, 'Product not found');
});

test('gets category list', function (): void {
    $api = new Guest();
    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('getProductCategorySearchQuery');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(['sqlString', []]);
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $serviceMock->shouldReceive('toProductCategoryApiArray');
    $expectation2->atLeast()->once();
    $expectation2->andReturn([]);

    $pager = [
        'list' => [
            0 => ['id' => 1],
        ],
    ];
    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class);
    /** @var Mockery\Expectation $expectation4 */
    $expectation4 = $pagerMock->shouldReceive('getPaginatedResultSet');
    $expectation4->atLeast()->once();
    $expectation4->andReturn($pager);

    $modelProductCategory = new Model_ProductCategory();
    $modelProductCategory->loadBean(new Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation5 */
    $expectation5 = $dbMock->shouldReceive('getExistingModelById');
    $expectation5->atLeast()->once();
    $expectation5->andReturn($modelProductCategory);

    $di = container();
    $di['db'] = $dbMock;
    $di['pager'] = $pagerMock;

    $api->setService($serviceMock);
    $api->setDi($di);
    $result = $api->category_get_list([]);
    expect($result)->toBeArray();
});

test('gets category pairs', function (): void {
    $api = new Guest();
    $serviceMock = Mockery::mock(Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getProductCategoryPairs');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);
    $result = $api->category_get_pairs([]);
    expect($result)->toBeArray();
});
