<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Api\Client;
use Box\Mod\Invoice\Service;
use Box\Mod\Invoice\ServiceTax;
use Box\Mod\Invoice\ServiceTransaction;

use function Tests\Helpers\container;

test('gets dependency injection container', function (): void {
    $api = new Client();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('gets an invoice', function (): void {
    $api = new Client();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data['hash'] = md5('1');
    $result = $api->get($data);
    expect($result)->toBeArray();
});

test('throws exception when invoice is not found', function (): void {
    $api = new Client();
    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setIdentity(new Model_Admin());

    $data['hash'] = md5('1');
    expect(fn () => $api->get($data))
        ->toThrow(FOSSBilling\Exception::class, 'Invoice was not found');
});

test('updates an invoice', function (): void {
    $api = new Client();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateInvoice')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data['hash'] = md5('1');
    $result = $api->update($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('throws exception when updating invoice not found', function (): void {
    $api = new Client();
    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setIdentity(new Model_Admin());

    $data['hash'] = md5('1');
    expect(fn () => $api->update($data))
        ->toThrow(FOSSBilling\Exception::class, 'Invoice was not found');
});

test('throws exception when updating paid invoice', function (): void {
    $api = new Client();
    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->status = 'paid';
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setIdentity(new Model_Admin());

    $data['hash'] = md5('1');
    expect(fn () => $api->update($data))
        ->toThrow(FOSSBilling\Exception::class, 'Paid Invoice cannot be modified');
});

test('creates renewal invoice', function (): void {
    $api = new Client();
    $generatedHash = 'generatedHashString';

    $serviceMock = Mockery::mock(Service::class);
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->hash = $generatedHash;
    $serviceMock->shouldReceive('generateForOrder')
        ->atLeast()->once()
        ->andReturn($model);
    $serviceMock->shouldReceive('approveInvoice');

    $dbMock = Mockery::mock('\Box_Database');
    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());
    $clientOrder->price = 10;
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($clientOrder);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $api->setDi($di);
    $api->setService($serviceMock);
    $identity = new Model_Admin();
    $identity->loadBean(new Tests\Helpers\DummyBean());
    $api->setIdentity($identity);

    $data['order_id'] = 1;
    $result = $api->renewal_invoice($data);
    expect($result)->toBeString()->toBe($generatedHash);
});

test('throws exception when creating renewal invoice for free order', function (): void {
    $api = new Client();
    $dbMock = Mockery::mock('\Box_Database');
    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());
    $clientOrder->id = 1;
    $clientOrder->price = 0;

    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($clientOrder);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $api->setDi($di);
    $identity = new Model_Admin();
    $identity->loadBean(new Tests\Helpers\DummyBean());
    $api->setIdentity($identity);

    $data['order_id'] = 1;

    expect(fn () => $api->renewal_invoice($data))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Order %d is free. No need to generate invoice.', $clientOrder->id));
});

test('throws exception when creating renewal invoice for order not found', function (): void {
    $api = new Client();
    $dbMock = Mockery::mock('\Box_Database');
    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());
    $clientOrder->price = 10;

    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $identity = new Model_Admin();
    $identity->loadBean(new Tests\Helpers\DummyBean());
    $api->setIdentity($identity);

    $data['order_id'] = 1;

    expect(fn () => $api->renewal_invoice($data))
        ->toThrow(FOSSBilling\Exception::class, 'Order not found');
});

test('creates funds invoice', function (): void {
    $api = new Client();
    $generatedHash = 'generatedHashString';

    $serviceMock = Mockery::mock(Service::class);
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->hash = $generatedHash;
    $serviceMock->shouldReceive('generateFundsInvoice')
        ->atLeast()->once()
        ->andReturn($model);
    $serviceMock->shouldReceive('approveInvoice');

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();

    $api->setDi($di);
    $api->setService($serviceMock);
    $identity = new Model_Client();
    $identity->loadBean(new Tests\Helpers\DummyBean());
    $api->setIdentity($identity);

    $data['amount'] = 10;
    $result = $api->funds_invoice($data);
    expect($result)->toBeString()->toBe($generatedHash);
});

test('deletes an invoice', function (): void {
    $api = new Client();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('deleteInvoiceByClient')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $api->setDi($di);
    $api->setService($serviceMock);
    $identity = new Model_Client();
    $identity->loadBean(new Tests\Helpers\DummyBean());
    $api->setIdentity($identity);

    $data['hash'] = md5('1');
    $result = $api->delete($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('gets transaction list', function (): void {
    $api = new Client();
    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('getSearchQuery')
        ->atLeast()->once()
        ->andReturn(['SqlString', []]);

    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(25);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn(['list' => []]);

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $transactionService);

    $api->setDi($di);

    $identity = new Model_Client();
    $identity->loadBean(new Tests\Helpers\DummyBean());
    $api->setIdentity($identity);
    $result = $api->transaction_get_list([]);
    expect($result)->toBeArray();
});

test('gets tax rate for client', function (): void {
    $api = new Client();
    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $taxRate = 20;

    $invoiceTaxService = Mockery::mock(ServiceTax::class);
    $invoiceTaxService->shouldReceive('getTaxRateForClient')
        ->atLeast()->once()
        ->andReturn($taxRate);

    $di = container();
    $di['mod_service'] = $di->protect(function ($service, $sub) use ($invoiceTaxService) {
        if ($service == 'Invoice' && $sub == 'Tax') {
            return $invoiceTaxService;
        }
    });
    $api->setDi($di);
    $api->setIdentity($client);

    $result = $api->get_tax_rate();
    expect($result)->toBe($taxRate);
});
