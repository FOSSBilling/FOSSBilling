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

test('gets dependency injection container', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('gets summary', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getSummary');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->get_summary();
    expect($result)->toBeArray();
});

test('gets summary income', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getSummaryIncome');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->get_summary_income();
    expect($result)->toBeArray();
});

test('gets order statuses', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getOrdersStatuses');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->get_orders_statuses($data);
    expect($result)->toBeArray();
});

test('gets product summary', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getProductSummary');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->get_product_summary($data);
    expect($result)->toBeArray();
});

test('gets product sales', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getProductSales');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->get_product_sales($data);
    expect($result)->toBeArray();
});

test('gets income vs refunds', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('incomeAndRefundStats');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->get_income_vs_refunds($data);
    expect($result)->toBeArray();
});

test('gets refunds', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getRefunds');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->get_refunds($data);
    expect($result)->toBeArray();
});

test('gets income', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getIncome');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->get_income($data);
    expect($result)->toBeArray();
});

test('gets orders', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getTableStats');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->get_orders($data);
    expect($result)->toBeArray();
});

test('gets clients', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getTableStats');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->get_clients($data);
    expect($result)->toBeArray();
});

test('gets client countries', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getClientCountries');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->client_countries($data);
    expect($result)->toBeArray();
});

test('gets sales countries', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getSalesByCountry');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->sales_countries($data);
    expect($result)->toBeArray();
});

test('gets invoices', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getTableStats');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->get_invoices($data);
    expect($result)->toBeArray();
});

test('gets tickets', function (): void {
    $api = new \Box\Mod\Stats\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Stats\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getTableStats');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->get_tickets($data);
    expect($result)->toBeArray();
});
