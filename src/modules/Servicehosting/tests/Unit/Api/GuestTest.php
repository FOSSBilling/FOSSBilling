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
use Box\Mod\Servicehosting\Api\Guest;

test('testFreeTlds', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Guest();
    $di = container();

    $model = new \Model_Product();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->type = \Model_Product::HOSTING;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($model);

    $di['db'] = $dbMock;

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getFreeTlds')
    ->atLeast()->once()
        ->with($model)
    ->andReturn([]);
    $api->setService($serviceMock);
    $api->setDi($di);

    $result = $api->free_tlds(['product_id' => 1]);
    expect($result)->toBeArray();
});

test('testFreeTldsProductTypeIsNotHosting', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Guest();
    $di = container();

    $model = new \Model_Product();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($model);

    $validatorStub = $this->createStub(\FOSSBilling\Validate::class);

    $di['db'] = $dbMock;
    $di['validator'] = $validatorStub;

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->shouldReceive('getFreeTlds');
    $api->setService($serviceMock);
    $api->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Product type is invalid');
    $api->free_tlds(['product_id' => 1]);
});
