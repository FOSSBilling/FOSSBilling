<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Servicehosting\Api\Guest;

use function Tests\Helpers\container;

test('free tlds', function (): void {
    $api = new Guest();
    $di = container();

    $model = new Box\Mod\Product\Entity\Product();
    $model->setType(Box\Mod\Product\Service::HOSTING);

    $productService = Mockery::mock(Box\Mod\Product\Service::class);
    $productService->shouldReceive('findProductById')->once()->with(1)->andReturn($model);

    $di['mod_service'] = $di->protect(function (string $service) use ($productService) {
        if ($service === 'product') {
            return $productService;
        }

        throw new RuntimeException('Unexpected service request');
    });

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock->shouldReceive('getFreeTlds')->atLeast()->once()->with($model)->andReturn([]);
    $api->setService($serviceMock);
    $api->setDi($di);

    $result = $api->free_tlds(['product_id' => 1]);
    expect($result)->toBeArray();
});

test('free tlds product type is not hosting', function (): void {
    $api = new Guest();
    $di = container();

    $model = new Box\Mod\Product\Entity\Product();

    $productService = Mockery::mock(Box\Mod\Product\Service::class);
    $productService->shouldReceive('findProductById')->once()->with(1)->andReturn($model);

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class)->shouldIgnoreMissing();

    $di['validator'] = $validatorMock;
    $di['mod_service'] = $di->protect(function (string $service) use ($productService) {
        if ($service === 'product') {
            return $productService;
        }

        throw new RuntimeException('Unexpected service request');
    });

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock->shouldNotReceive('getFreeTlds');
    $api->setService($serviceMock);
    $api->setDi($di);

    expect(fn () => $api->free_tlds(['product_id' => 1]))
        ->toThrow(FOSSBilling\Exception::class, 'Product type is invalid');
});
