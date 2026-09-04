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
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Repository\InvoiceRepository;
use Box\Mod\Invoice\Repository\TransactionRepository;
use Box\Mod\Invoice\Service;
use Box\Mod\Invoice\ServiceTax;
use Box\Mod\Invoice\ServiceTransaction;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Repository\OrderRepository;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\moduleService;
use function Tests\Helpers\setEntityId;

test('gets dependency injection container', function (): void {
    $api = apiEndpoint(new Client());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('gets invoice list', function (): void {
    $api = apiEndpoint(new Client());

    $identity = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 7]);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')
        ->once()
        ->with(Mockery::on(fn ($inv): bool => $inv instanceof Invoice))
        ->andReturn(['id' => 1]);

    $invoiceRepo = Mockery::mock(InvoiceRepository::class);
    $invoiceRepo->shouldReceive('getSearchQueryBuilder')
        ->once()
        ->with(['client_id' => 7, 'approved' => true])
        ->andReturn(Mockery::mock(Doctrine\ORM\QueryBuilder::class));

    $paginatorMock = Mockery::mock(FOSSBilling\Core\Pagination\Service::class);
    $paginatorMock->shouldReceive('paginateMappedQuery')
        ->once()
        ->andReturnUsing(fn ($qb, $pagination, $mapper): array => ['list' => [$mapper(createEntity(Invoice::class))]]);

    $di = container();
    $di['pager'] = $paginatorMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $serviceMock->shouldReceive('getInvoiceRepository')->andReturn($invoiceRepo);
    $api->setIdentity($identity);

    $result = $api->get_list([]);
    expect($result['list'])->toBe([['id' => 1]]);
});

test('gets an invoice', function (): void {
    $api = apiEndpoint(new Client());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $model = createEntity(Invoice::class);
    $identity = createEntity(Box\Mod\Client\Entity\Client::class);
    $data['hash'] = md5('1');

    $di = container();
    $invoiceRepo = $di['em']->getRepository(Invoice::class);
    $invoiceRepo->shouldReceive('findOneBy')
        ->atLeast()->once()
        ->with(['hash' => $data['hash'], 'clientId' => $identity->getId()])
        ->andReturn($model);
    $serviceMock->shouldReceive('getInvoiceRepository')->andReturn($invoiceRepo);

    $api->setDi($di);
    $api->setService($serviceMock);
    $api->setIdentity($identity);

    $result = $api->get($data);
    expect($result)->toBeArray();
});

test('throws exception when invoice is not found', function (): void {
    $api = apiEndpoint(new Client());

    $di = container();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getInvoiceRepository')->andReturn($di['em']->getRepository(Invoice::class));

    $api->setDi($di);
    $api->setService($serviceMock);
    $identity = createEntity(Box\Mod\Client\Entity\Client::class);
    $api->setIdentity($identity);

    $data['hash'] = md5('1');
    expect(fn () => $api->get($data))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Invoice was not found');
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

test('creates renewal invoice from a real invoice entity without accessing private properties', function (): void {
    // Regression test: renewal_invoice() used to read $invoice->id and
    // $invoice->hash directly, which are private on the Doctrine entity and
    // fatal with "Cannot access private property". createEntity() below
    // masks that with magic getters/setters, so this uses a real Invoice
    // instance instead.
    $api = apiEndpoint(new Client());
    $generatedHash = 'generatedHashString';

    $model = new Invoice();
    setEntityId($model, 1);
    $model->setHash($generatedHash);

    $serviceMock = Mockery::mock(Service::class);
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
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Order not found');
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
    $identity = createEntity(Box\Mod\Client\Entity\Client::class);
    $api->setIdentity($identity);

    $data['amount'] = 10;
    $result = $api->funds_invoice($data);
    expect($result)->toBeString()->toBe($generatedHash);
});

test('creates funds invoice from a real invoice entity without accessing private properties', function (): void {
    // Regression test: same as the renewal_invoice() case above - funds_invoice()
    // also read $invoice->id and $invoice->hash directly.
    $api = apiEndpoint(new Client());
    $generatedHash = 'generatedHashString';

    $model = new Invoice();
    setEntityId($model, 1);
    $model->setHash($generatedHash);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('generateFundsInvoice')
        ->atLeast()->once()
        ->andReturn($model);
    $serviceMock->shouldReceive('approveInvoice');

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();

    $api->setDi($di);
    $api->setService($serviceMock);
    $identity = createEntity(Box\Mod\Client\Entity\Client::class);
    $api->setIdentity($identity);

    $data['amount'] = 10;
    $result = $api->funds_invoice($data);
    expect($result)->toBeString()->toBe($generatedHash);
});

test('gets transaction list', function (): void {
    $api = apiEndpoint(new Client());
    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('transactionResultToApiArray')
        ->once()
        ->with(Mockery::on(fn ($t): bool => $t instanceof Transaction), 'Stripe')
        ->andReturn(['id' => 1, 'gateway' => 'Stripe']);

    $transactionRepo = Mockery::mock(TransactionRepository::class);
    $transactionRepo->shouldReceive('getSearchQueryBuilder')
        ->once()
        ->with(['client_id' => 7, 'status' => 'processed'])
        ->andReturn(Mockery::mock(Doctrine\ORM\QueryBuilder::class));

    $paginatorMock = Mockery::mock(FOSSBilling\Core\Pagination\Service::class);
    $paginatorMock->shouldReceive('paginateMappedQuery')
        ->once()
        ->andReturnUsing(fn ($qb, $pagination, $mapper): array => ['list' => [$mapper([0 => createEntity(Transaction::class, ['id' => 1]), 'gateway' => 'Stripe'])]]);

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['mod_service'] = $di->protect(moduleService(['invoice:transaction' => $transactionService]));

    $api->setDi($di);
    $transactionService->shouldReceive('getTransactionRepository')->andReturn($transactionRepo);

    $identity = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 7]);
    $api->setIdentity($identity);
    $result = $api->transaction_get_list([]);
    expect($result['list'])->toBe([['id' => 1, 'gateway' => 'Stripe']]);
});

test('gets tax rate for client', function (): void {
    $api = apiEndpoint(new Client());
    $client = createEntity(Box\Mod\Client\Entity\Client::class);

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
