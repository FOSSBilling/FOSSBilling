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

beforeEach(function () {
    $this->api = new \Box\Mod\Stats\Api\Admin();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toBe($di);
});

test('gets summary', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getSummary')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->get_summary();
    expect($result)->toBeArray();
});

test('gets summary income', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getSummaryIncome')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->get_summary_income();
    expect($result)->toBeArray();
});

test('gets order statuses', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getOrdersStatuses')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $data = [];
    $result = $this->api->get_orders_statuses($data);
    expect($result)->toBeArray();
});

test('gets product summary', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getProductSummary')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $data = [];
    $result = $this->api->get_product_summary($data);
    expect($result)->toBeArray();
});

test('gets product sales', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getProductSales')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $data = [];
    $result = $this->api->get_product_sales($data);
    expect($result)->toBeArray();
});

test('gets income vs refunds', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('incomeAndRefundStats')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $data = [];
    $result = $this->api->get_income_vs_refunds($data);
    expect($result)->toBeArray();
});

test('gets refunds', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getRefunds')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $data = [];
    $result = $this->api->get_refunds($data);
    expect($result)->toBeArray();
});

test('gets income', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getIncome')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $data = [];
    $result = $this->api->get_income($data);
    expect($result)->toBeArray();
});

test('gets orders', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getTableStats')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $data = [];
    $result = $this->api->get_orders($data);
    expect($result)->toBeArray();
});

test('gets clients', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getTableStats')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $data = [];
    $result = $this->api->get_clients($data);
    expect($result)->toBeArray();
});

test('gets client countries', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getClientCountries')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $data = [];
    $result = $this->api->client_countries($data);
    expect($result)->toBeArray();
});

test('gets sales countries', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getSalesByCountry')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $data = [];
    $result = $this->api->sales_countries($data);
    expect($result)->toBeArray();
});

test('gets invoices', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getTableStats')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $data = [];
    $result = $this->api->get_invoices($data);
    expect($result)->toBeArray();
});

test('gets tickets', function () {
    $serviceMock = $this->createMock(\Box\Mod\Stats\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getTableStats')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $data = [];
    $result = $this->api->get_tickets($data);
    expect($result)->toBeArray();
});
