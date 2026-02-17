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
use Box\Mod\Invoice\Api\Guest;
use Box\Mod\Invoice\Service;
use Box\Mod\Invoice\ServicePayGateway;

test('gets dependency injection container', function (): void {
    $api = new \Box\Mod\Invoice\Api\Guest();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('gets an invoice', function (): void {
    $api = new \Box\Mod\Invoice\Api\Guest();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('checkInvoiceAuth')
        ->atLeast()->once();
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new \Model_Invoice();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $api->setIdentity(new \Model_Admin());

    $data['hash'] = md5('1');
    $result = $api->get($data);
    expect($result)->toBeArray();
});

test('throws exception when invoice is not found', function (): void {
    $api = new \Box\Mod\Invoice\Api\Guest();
    $dbMock = Mockery::mock('\Box_Database');
    $model = new \Model_Invoice();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setIdentity(new \Model_Admin());

    $data['hash'] = md5('1');
    expect(fn () => $api->get($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Invoice was not found');
});

test('updates an invoice', function (): void {
    $api = new \Box\Mod\Invoice\Api\Guest();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateInvoice')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new \Model_Invoice();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $api->setIdentity(new \Model_Admin());

    $data['hash'] = md5('1');
    $result = $api->update($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('throws exception when updating invoice not found', function (): void {
    $api = new \Box\Mod\Invoice\Api\Guest();
    $dbMock = Mockery::mock('\Box_Database');
    $model = new \Model_Invoice();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setIdentity(new \Model_Admin());

    $data['hash'] = md5('1');
    expect(fn () => $api->update($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Invoice was not found');
});

test('throws exception when updating paid invoice', function (): void {
    $api = new \Box\Mod\Invoice\Api\Guest();
    $dbMock = Mockery::mock('\Box_Database');
    $model = new \Model_Invoice();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->status = 'paid';
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setIdentity(new \Model_Admin());

    $data['hash'] = md5('1');
    expect(fn () => $api->update($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Paid Invoice cannot be modified');
});

test('gets active gateways', function (): void {
    $api = new \Box\Mod\Invoice\Api\Guest();
    $gatewayServiceMock = Mockery::mock(ServicePayGateway::class);
    $gatewayServiceMock->shouldReceive('getActive')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $gatewayServiceMock);

    $api->setDi($di);

    $result = $api->gateways([]);
    expect($result)->toBeArray();
});

test('processes payment', function (): void {
    $api = new \Box\Mod\Invoice\Api\Guest();
    $data = [
        'hash' => '',
        'gateway_id' => '',
    ];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('processInvoice')
        ->atLeast()->once()
        ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->payment($data);
    expect($result)->toBeArray();
});

test('throws exception when payment hash is missing', function (): void {
    $api = new \Box\Mod\Invoice\Api\Guest();
    $data = [
        'gateway_id' => '',
    ];

    expect(fn () => $api->payment($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Invoice hash not passed. Missing param hash');
});

test('throws exception when payment gateway id is missing', function (): void {
    $api = new \Box\Mod\Invoice\Api\Guest();
    $data = [
        'hash' => '',
    ];

    expect(fn () => $api->payment($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Payment method not found. Missing param gateway_id');
});

test('generates PDF', function (): void {
    $api = new \Box\Mod\Invoice\Api\Guest();
    $data = [
        'hash' => '',
    ];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('generatePDF')
        ->atLeast()->once();

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);

    $api->pdf($data);
});
