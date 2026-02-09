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

beforeEach(function () {
    $this->api = new Guest();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toBe($di);
});

test('gets an invoice', function () {
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

    $this->api->setDi($di);
    $this->api->setService($serviceMock);
    $this->api->setIdentity(new \Model_Admin());

    $data['hash'] = md5('1');
    $result = $this->api->get($data);
    expect($result)->toBeArray();
});

test('throws exception when invoice is not found', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $model = new \Model_Invoice();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;

    $this->api->setDi($di);
    $this->api->setIdentity(new \Model_Admin());

    $data['hash'] = md5('1');
    expect(fn () => $this->api->get($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Invoice was not found');
});

test('updates an invoice', function () {
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

    $this->api->setDi($di);
    $this->api->setService($serviceMock);
    $this->api->setIdentity(new \Model_Admin());

    $data['hash'] = md5('1');
    $result = $this->api->update($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('throws exception when updating invoice not found', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $model = new \Model_Invoice();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;

    $this->api->setDi($di);
    $this->api->setIdentity(new \Model_Admin());

    $data['hash'] = md5('1');
    expect(fn () => $this->api->update($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Invoice was not found');
});

test('throws exception when updating paid invoice', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $model = new \Model_Invoice();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->status = 'paid';
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $this->api->setDi($di);
    $this->api->setIdentity(new \Model_Admin());

    $data['hash'] = md5('1');
    expect(fn () => $this->api->update($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Paid Invoice cannot be modified');
});

test('gets active gateways', function () {
    $gatewayServiceMock = Mockery::mock(ServicePayGateway::class);
    $gatewayServiceMock->shouldReceive('getActive')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $gatewayServiceMock);

    $this->api->setDi($di);

    $result = $this->api->gateways([]);
    expect($result)->toBeArray();
});

test('processes payment', function () {
    $data = [
        'hash' => '',
        'gateway_id' => '',
    ];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('processInvoice')
        ->atLeast()->once()
        ->andReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->payment($data);
    expect($result)->toBeArray();
});

test('throws exception when payment hash is missing', function () {
    $data = [
        'gateway_id' => '',
    ];

    expect(fn () => $this->api->payment($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Invoice hash not passed. Missing param hash');
});

test('throws exception when payment gateway id is missing', function () {
    $data = [
        'hash' => '',
    ];

    expect(fn () => $this->api->payment($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Payment method not found. Missing param gateway_id');
});

test('generates PDF', function () {
    $data = [
        'hash' => '',
    ];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('generatePDF')
        ->atLeast()->once();

    $di = container();
    $this->api->setDi($di);

    $this->api->setService($serviceMock);

    $this->api->pdf($data);
});
