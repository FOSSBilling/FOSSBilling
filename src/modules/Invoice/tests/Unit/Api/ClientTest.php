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
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Service;
use Box\Mod\Invoice\ServiceTax;
use Box\Mod\Invoice\ServiceTransaction;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Repository\OrderRepository;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\moduleService;

test('gets dependency injection container', function (): void {
    $api = apiEndpoint(new Client());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('gets an invoice', function (): void {
    $api = apiEndpoint(new Client());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $model = createEntity(Invoice::class);

    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $identity = new Model_Client();
    $identity->loadBean(new Tests\Helpers\DummyBean());
    $api->setIdentity($identity);

    $data['hash'] = md5('1');
    $result = $api->get($data);
    expect($result)->toBeArray();
});

test('throws exception when invoice is not found', function (): void {
    $api = apiEndpoint(new Client());
    $dbMock = Mockery::mock('\Box_Database');

    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $identity = new Model_Client();
    $identity->loadBean(new Tests\Helpers\DummyBean());
    $api->setIdentity($identity);

    $data['hash'] = md5('1');
    expect(fn () => $api->get($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Invoice was not found');
});

test('creates renewal invoice', function (): void {
    $api = apiEndpoint(new Client());
    $generatedHash = 'generatedHashString';

    $serviceMock = Mockery::mock(Service::class);
    $model = createEntity(Invoice::class);

    $model->hash = $generatedHash;
    $serviceMock->shouldReceive('generateForOrder')
        ->atLeast()->once()
        ->andReturn($model);
    $serviceMock->shouldReceive('approveInvoice');

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->atLeast()->once()
        ->andReturn(createEntity(Order::class, ['price' => 10]));

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $api->setDi($di);
    $api->setService($serviceMock);
    $identity = \Tests\Helpers\admin();
    $api->setIdentity($identity);

    $data['order_id'] = 1;
    $result = $api->renewal_invoice($data);
    expect($result)->toBeString()->toBe($generatedHash);
});

test('creates renewal invoice for free order', function (): void {
    $api = apiEndpoint(new Client());
    $generatedHash = 'generatedHashString';

    $serviceMock = Mockery::mock(Service::class);
    $model = createEntity(Invoice::class);

    $model->hash = $generatedHash;
    $serviceMock->shouldReceive('generateForOrder')
        ->atLeast()->once()
        ->andReturn($model);
    $serviceMock->shouldReceive('approveInvoice');

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->atLeast()->once()
        ->andReturn(createEntity(Order::class, ['id' => 1, 'price' => 0]));

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $api->setDi($di);
    $api->setService($serviceMock);
    $identity = \Tests\Helpers\admin();
    $api->setIdentity($identity);

    $data['order_id'] = 1;
    $result = $api->renewal_invoice($data);
    expect($result)->toBeString()->toBe($generatedHash);
});

test('throws exception when creating renewal invoice for order not found', function (): void {
    $api = apiEndpoint(new Client());
    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);

    $api->setDi($di);
    $identity = \Tests\Helpers\admin();
    $api->setIdentity($identity);

    $data['order_id'] = 1;

    expect(fn () => $api->renewal_invoice($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Order not found');
});

test('creates funds invoice', function (): void {
    $api = apiEndpoint(new Client());
    $generatedHash = 'generatedHashString';

    $serviceMock = Mockery::mock(Service::class);
    $model = createEntity(Invoice::class);

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

test('gets transaction list', function (): void {
    $api = apiEndpoint(new Client());
    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('getSearchQuery')
        ->atLeast()->once()
        ->andReturn(['SqlString', []]);
    $transactionService->shouldReceive('searchResultToApiArray')
        ->once()
        ->with(['id' => 1])
        ->andReturn(['id' => 1, 'gateway' => 'Stripe']);

    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn(['list' => [['id' => 1]]]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldNotReceive('getExistingModelById');

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(moduleService(['invoice:transaction' => $transactionService]));

    $api->setDi($di);

    $identity = new Model_Client();
    $identity->loadBean(new Tests\Helpers\DummyBean());
    $api->setIdentity($identity);
    $result = $api->transaction_get_list([]);
    expect($result['list'])->toBe([['id' => 1, 'gateway' => 'Stripe']]);
});

test('gets tax rate for client', function (): void {
    $api = apiEndpoint(new Client());
    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $taxRate = 20;

    $invoiceTaxService = Mockery::mock(ServiceTax::class);
    $invoiceTaxService->shouldReceive('getTaxRateForClient')
        ->atLeast()->once()
        ->andReturn($taxRate);

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['invoice:tax' => $invoiceTaxService]));
    $api->setDi($di);
    $api->setIdentity($client);

    $result = $api->get_tax_rate();
    expect($result)->toBe($taxRate);
});
