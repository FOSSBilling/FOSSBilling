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
use Box\Mod\Invoice\ServiceTax;
use Box\Mod\Client\Service as ClientService;
use Box\Mod\System\Service as SystemService;
use Box\Mod\Invoice\ServiceInvoiceItem;

beforeEach(function () {
    $this->service = new ServiceTax();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

test('gets tax rate for client by country and state', function () {
    $taxRateExpected = 0.21;
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')
        ->atLeast()->once()
        ->andReturn(true);

    $taxModel = new \Model_Tax();
    $taxModel->loadBean(new \Tests\Helpers\DummyBean());
    $taxModel->taxrate = $taxRateExpected;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($taxModel);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $clientServiceMock);
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getTaxRateForClient($clientModel);
    expect($result)->toBeFloat();
    expect($result)->toBe($taxRateExpected);
});

test('gets tax rate for client by country', function () {
    $taxRateExpected = 0.21;
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')
        ->atLeast()->once()
        ->andReturn(true);

    $taxModel = new \Model_Tax();
    $taxModel->loadBean(new \Tests\Helpers\DummyBean());
    $taxModel->taxrate = $taxRateExpected;

    $dbMock = Mockery::mock('\Box_Database');
    $callCount = 0;
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturnUsing(function () use (&$callCount, $taxModel) {
            $callCount++;
            if ($callCount == 1) return null;
            return $taxModel;
        });

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $clientServiceMock);
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getTaxRateForClient($clientModel);
    expect($result)->toBeFloat();
    expect($result)->toBe($taxRateExpected);
});

test('gets tax rate for client', function () {
    $taxRateExpected = 0.21;
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')
        ->atLeast()->once()
        ->andReturn(true);

    $taxModel = new \Model_Tax();
    $taxModel->loadBean(new \Tests\Helpers\DummyBean());
    $taxModel->taxrate = $taxRateExpected;

    $dbMock = Mockery::mock('\Box_Database');
    $callCount = 0;
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturnUsing(function () use (&$callCount, $taxModel) {
            $callCount++;
            if ($callCount <= 2) return null;
            return $taxModel;
        });

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $clientServiceMock);
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getTaxRateForClient($clientModel);
    expect($result)->toBeFloat();
    expect($result)->toBe($taxRateExpected);
});

test('returns zero tax rate when tax not found', function () {
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $clientServiceMock);
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $taxRateExpected = 0;
    $result = $this->service->getTaxRateForClient($clientModel);
    expect($result)->toBeInt();
    expect($result)->toBe($taxRateExpected);
});

test('returns zero tax rate when client is not taxable', function () {
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('isClientTaxable')
        ->atLeast()->once()
        ->andReturn(false);

    $taxModel = new \Model_Tax();
    $taxModel->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $clientServiceMock);
    $this->service->setDi($di);

    $taxRateExpected = 0;
    $result = $this->service->getTaxRateForClient($clientModel);
    expect($result)->toBeInt();
    expect($result)->toBe($taxRateExpected);
});

test('returns zero tax when tax rate is zero', function () {
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());
    $invoiceModel->taxrate = 0;

    $result = $this->service->getTax($invoiceModel);
    expect($result)->toBeInt();
    expect($result)->toBe(0);
});

test('gets tax', function () {
    $invoiceModel = new \Model_Invoice();
    $invoiceModel->loadBean(new \Tests\Helpers\DummyBean());
    $invoiceModel->taxrate = 15;

    $invoiceItemModel = new \Model_InvoiceItem();
    $invoiceItemModel->loadBean(new \Tests\Helpers\DummyBean());
    $invoiceItemModel->quantity = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$invoiceItemModel]);

    $invoiceItemService = Mockery::mock(ServiceInvoiceItem::class);
    $invoiceItemService->shouldReceive('getTax')
        ->atLeast()->once()
        ->andReturn(21);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $invoiceItemService);
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->getTax($invoiceModel);
    expect($result)->toBeInt();
});

test('deletes a tax', function () {
    $taxModel = new \Model_Tax();
    $taxModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $result = $this->service->delete($taxModel);
    expect($result)->toBeTrue();
});

test('creates a tax', function () {
    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('checkLimits')
        ->atLeast()->once();

    $taxModel = new \Model_Tax();
    $taxModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($taxModel);
    $newId = 2;
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($newId);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $data = [
        'name' => 'tax',
        'taxrate' => '0.18',
    ];
    $result = $this->service->create($data);
    expect($result)->toBeInt()->toBe($newId);
});

test('updates a tax', function () {
    $taxModel = new \Model_Tax();
    $taxModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(2);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $data = [
        'name' => 'tax',
        'taxrate' => '0.18',
    ];
    $result = $this->service->update($taxModel, $data);
    expect($result)->toBeBool()->toBeTrue();
});

test('gets search query', function () {
    $result = $this->service->getSearchQuery([]);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect($result[1])->toBe([]);
});

test('converts to api array', function () {
    $taxModel = new \Model_Tax();
    $taxModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->with($taxModel)
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->toApiArray($taxModel);
    expect($result)->toBeArray();
});
