<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Api\Admin;
use Box\Mod\Invoice\Service;
use Box\Mod\Invoice\ServicePayGateway;
use Box\Mod\Invoice\ServiceSubscription;
use Box\Mod\Invoice\ServiceTax;
use Box\Mod\Invoice\ServiceTransaction;

use function Tests\Helpers\container;

test('gets dependency injection container', function (): void {
    $api = new Admin();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('gets invoice list', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getSearchQuery')
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

    $api->setDi($di);
    $api->setService($serviceMock);
    $result = $api->get_list([]);
    expect($result)->toBeArray();
});

test('gets an invoice', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data['id'] = 1;
    $result = $api->get($data);
    expect($result)->toBeArray();
});

test('marks invoice as paid', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
        'execute' => true,
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('markAsPaid')
        ->atLeast()->once()
        ->andReturn(true);

    $gatewayServiceMock = Mockery::mock(ServicePayGateway::class);
    $gatewayServiceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn(['code' => 'PayPal', 'enabled' => 1]);

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->gateway_id = '1';

    $gatewayModel = new Model_PayGateway();
    $gatewayModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturnUsing(function ($type) use ($invoiceModel, $gatewayModel) {
            if ($type === 'PayGateway') {
                return $gatewayModel;
            }

            return $invoiceModel;
        });

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(function ($name, $sub = '') use ($serviceMock, $gatewayServiceMock) {
        if ($name === 'Invoice' && $sub === 'PayGateway') {
            return $gatewayServiceMock;
        }

        return $serviceMock;
    });
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->mark_as_paid($data);
    expect($result)->toBeTrue();
});

test('prepares an invoice', function (): void {
    $api = new Admin();
    $data = [
        'client_id' => 1,
    ];
    $newInvoiceId = 1;

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $invoiceModel->id = $newInvoiceId;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('prepareInvoice')
        ->atLeast()->once()
        ->andReturn($invoiceModel);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Client();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->prepare($data);
    expect($result)->toBeInt()->toBe($newInvoiceId);
});

test('approves an invoice', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('approveInvoice')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->approve($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('refunds an invoice', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];
    $newNegativeInvoiceId = 2;
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('refundInvoice')
        ->atLeast()->once()
        ->andReturn($newNegativeInvoiceId);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->refund($data);
    expect($result)->toBeInt()->toBe($newNegativeInvoiceId);
});

test('updates an invoice', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateInvoice')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->update($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('deletes an invoice item', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $invoiceItemService = Mockery::mock(Box\Mod\Invoice\ServiceInvoiceItem::class);
    $invoiceItemService->shouldReceive('remove')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_InvoiceItem();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $invoiceItemService);

    $api->setDi($di);

    $result = $api->item_delete($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('deletes an invoice', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('deleteInvoiceByAdmin')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->delete($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('creates renewal invoice', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];
    $newInvoiceId = 3;
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('renewInvoice')
        ->atLeast()->once()
        ->andReturn($newInvoiceId);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_ClientOrder();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->price = 10;
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->renewal_invoice($data);
    expect($result)->toBeInt()->toBe($newInvoiceId);
});

test('throws exception when creating renewal invoice for free order', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_ClientOrder();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->id = 1;
    $model->price = 0;
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);

    expect(fn () => $api->renewal_invoice($data))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Order %d is free. No need to generate invoice.', $model->id));
});

test('processes batch pay with credits', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('doBatchPayWithCredits')
        ->atLeast()->once()
        ->andReturn(true);

    $api->setService($serviceMock);

    $result = $api->batch_pay_with_credits([]);
    expect($result)->toBeBool()->toBeTrue();
});

test('pays invoice with credits', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('payInvoiceWithCredits')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->pay_with_credits($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('generates batch invoices', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('generateInvoicesForExpiringOrders')
        ->atLeast()->once()
        ->andReturn(true);

    $api->setService($serviceMock);

    $result = $api->batch_generate();
    expect($result)->toBeBool()->toBeTrue();
});

test('activates paid invoices in batch', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('doBatchPaidInvoiceActivation')
        ->atLeast()->once()
        ->andReturn(true);

    $api->setService($serviceMock);

    $result = $api->batch_activate_paid();
    expect($result)->toBeBool()->toBeTrue();
});

test('sends reminders in batch', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('doBatchRemindersSend')
        ->atLeast()->once()
        ->andReturn(true);

    $api->setService($serviceMock);

    $result = $api->batch_send_reminders([]);
    expect($result)->toBeBool()->toBeTrue();
});

test('invokes due event in batch', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('doBatchInvokeDueEvent')
        ->atLeast()->once()
        ->andReturn(true);

    $api->setService($serviceMock);

    $result = $api->batch_invoke_due_event([]);
    expect($result)->toBeBool()->toBeTrue();
});

test('sends reminder for an invoice', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('sendInvoiceReminder')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Invoice();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->send_reminder($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('gets invoice statuses', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('counter')
        ->atLeast()->once()
        ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->get_statuses([]);
    expect($result)->toBeArray();
});

test('processes all transactions', function (): void {
    $api = new Admin();
    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('processReceivedATransactions')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $transactionService);

    $api->setDi($di);
    $result = $api->transaction_process_all([]);
    expect($result)->toBeBool()->toBeTrue();
});

test('processes a transaction', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('preProcessTransaction')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Transaction();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventsMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $transactionService);

    $api->setDi($di);

    $result = $api->transaction_process($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('updates a transaction', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('update')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Transaction();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $transactionService);

    $api->setDi($di);

    $result = $api->transaction_update($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('creates a transaction', function (): void {
    $api = new Admin();
    $newTransactionId = 1;
    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('create')
        ->atLeast()->once()
        ->andReturn($newTransactionId);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $transactionService);
    $api->setDi($di);

    $result = $api->transaction_create([]);
    expect($result)->toBeInt()->toBe($newTransactionId);
});

test('deletes a transaction', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('delete')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Transaction();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $transactionService);

    $api->setDi($di);

    $result = $api->transaction_delete($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('gets a transaction', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Transaction();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $transactionService);

    $api->setDi($di);

    $result = $api->transaction_get($data);
    expect($result)->toBeArray();
});

test('gets transaction list', function (): void {
    $api = new Admin();
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
    $result = $api->transaction_get_list([]);
    expect($result)->toBeArray();
});

test('gets transaction statuses', function (): void {
    $api = new Admin();
    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('counter')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $transactionService);

    $api->setDi($di);

    $result = $api->transaction_get_statuses([]);
    expect($result)->toBeArray();
});

test('gets transaction status pairs', function (): void {
    $api = new Admin();
    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('getStatusPairs')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $transactionService);

    $api->setDi($di);

    $result = $api->transaction_get_statuses_pairs([]);
    expect($result)->toBeArray();
});

test('gets transaction statuses list', function (): void {
    $api = new Admin();
    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('getStatuses')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $transactionService);

    $api->setDi($di);

    $result = $api->transaction_statuses([]);
    expect($result)->toBeArray();
});

test('gets transaction gateway statuses', function (): void {
    $api = new Admin();
    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('getGatewayStatuses')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $transactionService);

    $api->setDi($di);

    $result = $api->transaction_gateway_statuses([]);
    expect($result)->toBeArray();
});

test('gets transaction types', function (): void {
    $api = new Admin();
    $transactionService = Mockery::mock(ServiceTransaction::class);
    $transactionService->shouldReceive('getTypes')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $transactionService);

    $api->setDi($di);

    $result = $api->transaction_types([]);
    expect($result)->toBeArray();
});

test('gets gateway list', function (): void {
    $api = new Admin();
    $gatewayService = Mockery::mock(ServicePayGateway::class);
    $gatewayService->shouldReceive('getSearchQuery')
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
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $gatewayService);

    $api->setDi($di);
    $result = $api->gateway_get_list([]);
    expect($result)->toBeArray();
});

test('gets gateway pairs', function (): void {
    $api = new Admin();
    $gatewayService = Mockery::mock(ServicePayGateway::class);
    $gatewayService->shouldReceive('getPairs')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $gatewayService);
    $api->setDi($di);

    $result = $api->gateway_get_pairs([]);
    expect($result)->toBeArray();
});

test('gets available gateways', function (): void {
    $api = new Admin();
    $gatewayService = Mockery::mock(ServicePayGateway::class);
    $gatewayService->shouldReceive('getAvailable')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $gatewayService);
    $api->setDi($di);

    $result = $api->gateway_get_available([]);
    expect($result)->toBeArray();
});

test('installs a gateway', function (): void {
    $api = new Admin();
    $data = [
        'code' => 'PP',
    ];

    $gatewayService = Mockery::mock(ServicePayGateway::class);
    $gatewayService->shouldReceive('install')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $gatewayService);
    $api->setDi($di);

    $result = $api->gateway_install($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('gets a gateway', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $gatewayService = Mockery::mock(ServicePayGateway::class);
    $gatewayService->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_PayGateway();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $gatewayService);

    $api->setDi($di);

    $result = $api->gateway_get($data);
    expect($result)->toBeArray();
});

test('copies a gateway', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];
    $newGatewayId = 1;
    $gatewayService = Mockery::mock(ServicePayGateway::class);
    $gatewayService->shouldReceive('copy')
        ->atLeast()->once()
        ->andReturn($newGatewayId);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_PayGateway();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $gatewayService);

    $api->setDi($di);

    $result = $api->gateway_copy($data);
    expect($result)->toBeInt()->toBe($newGatewayId);
});

test('updates a gateway', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $gatewayService = Mockery::mock(ServicePayGateway::class);
    $gatewayService->shouldReceive('update')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_PayGateway();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $gatewayService);

    $api->setDi($di);

    $result = $api->gateway_update($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('deletes a gateway', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $gatewayService = Mockery::mock(ServicePayGateway::class);
    $gatewayService->shouldReceive('delete')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_PayGateway();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $gatewayService);

    $api->setDi($di);

    $result = $api->gateway_delete($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('gets subscription list', function (): void {
    $api = new Admin();
    $subscriptionService = Mockery::mock(ServiceSubscription::class);
    $subscriptionService->shouldReceive('getSearchQuery')
        ->atLeast()->once()
        ->andReturn(['SqlString', []]);

    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(25);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $subscriptionService);

    $api->setDi($di);
    $result = $api->subscription_get_list([]);
    expect($result)->toBeArray();
});

test('creates a subscription', function (): void {
    $api = new Admin();
    $data = [
        'client_id' => 1,
        'gateway_id' => 1,
        'currency' => 'EU',
    ];
    $newSubscriptionId = 1;
    $subscriptionService = Mockery::mock(ServiceSubscription::class);
    $subscriptionService->shouldReceive('create')
        ->atLeast()->once()
        ->andReturn($newSubscriptionId);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_PayGateway();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->currency = 'EU';

    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($client, $model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $subscriptionService);

    $api->setDi($di);

    $result = $api->subscription_create($data);
    expect($result)->toBeInt()->toBe($newSubscriptionId);
});

test('throws exception when creating subscription with currency mismatch', function (): void {
    $api = new Admin();
    $data = [
        'client_id' => 1,
        'gateway_id' => 1,
        'currency' => 'EU',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_PayGateway();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($client, $model);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);

    expect(fn () => $api->subscription_create($data))
        ->toThrow(FOSSBilling\Exception::class, 'Client currency must match subscription currency. Check if clients currency is defined.');
});

test('updates a subscription', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $subscriptionService = Mockery::mock(ServiceSubscription::class);
    $subscriptionService->shouldReceive('update')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Subscription();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $subscriptionService);

    $api->setDi($di);

    $result = $api->subscription_update($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('gets a subscription', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $subscriptionService = Mockery::mock(ServiceSubscription::class);
    $subscriptionService->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Subscription();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $subscriptionService);

    $api->setDi($di);

    $result = $api->subscription_get($data);
    expect($result)->toBeArray();
});

test('deletes a subscription', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $subscriptionService = Mockery::mock(ServiceSubscription::class);
    $subscriptionService->shouldReceive('delete')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Subscription();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $subscriptionService);

    $api->setDi($di);

    $result = $api->subscription_delete($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('deletes a tax', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];

    $taxService = Mockery::mock(ServiceTax::class);
    $taxService->shouldReceive('delete')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Tax();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $taxService);

    $api->setDi($di);

    $result = $api->tax_delete($data);
    expect($result)->toBeBool()->toBeTrue();
});

test('creates a tax', function (): void {
    $api = new Admin();
    $data = [
        'id' => 1,
    ];
    $newTaxId = 1;
    $taxService = Mockery::mock(ServiceTax::class);
    $taxService->shouldReceive('create')
        ->atLeast()->once()
        ->andReturn($newTaxId);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $taxService);

    $api->setDi($di);

    $result = $api->tax_create($data);
    expect($result)->toBeInt()->toBe($newTaxId);
});

test('gets tax list', function (): void {
    $api = new Admin();
    $taxService = Mockery::mock(ServiceTax::class);
    $taxService->shouldReceive('getSearchQuery')
        ->atLeast()->once()
        ->andReturn(['SqlString', []]);

    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(25);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $taxService);

    $api->setDi($di);

    $result = $api->tax_get_list([]);
    expect($result)->toBeArray();
});

test('deletes invoices in batch', function (): void {
    $api = new Admin();
    $activityMock = Mockery::mock(Admin::class)->makePartial();
    $activityMock->shouldReceive('delete')->atLeast()->once()->andReturn(true);

    $di = container();
    $activityMock->setDi($di);

    $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
    expect($result)->toBeTrue();
});

test('deletes subscriptions in batch', function (): void {
    $api = new Admin();
    $activityMock = Mockery::mock(Admin::class)->makePartial();
    $activityMock->shouldReceive('subscription_delete')->atLeast()->once()->andReturn(true);

    $di = container();
    $activityMock->setDi($di);

    $result = $activityMock->batch_delete_subscription(['ids' => [1, 2, 3]]);
    expect($result)->toBeTrue();
});

test('deletes transactions in batch', function (): void {
    $api = new Admin();
    $activityMock = Mockery::mock(Admin::class)->makePartial();
    $activityMock->shouldReceive('transaction_delete')->atLeast()->once()->andReturn(true);

    $di = container();
    $activityMock->setDi($di);

    $result = $activityMock->batch_delete_transaction(['ids' => [1, 2, 3]]);
    expect($result)->toBeTrue();
});

test('deletes taxes in batch', function (): void {
    $api = new Admin();
    $activityMock = Mockery::mock(Admin::class)->makePartial();
    $activityMock->shouldReceive('tax_delete')->atLeast()->once()->andReturn(true);

    $di = container();
    $activityMock->setDi($di);

    $result = $activityMock->batch_delete_tax(['ids' => [1, 2, 3]]);
    expect($result)->toBeTrue();
});

test('gets a tax', function (): void {
    $api = new Admin();
    $taxService = Mockery::mock(ServiceTax::class);
    $taxService->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Tax();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $taxService);

    $api->setDi($di);
    $api->setService($taxService);
    $api->setIdentity(new Model_Admin());

    $data['id'] = 1;
    $result = $api->tax_get($data);
    expect($result)->toBeArray();
});

test('updates a tax', function (): void {
    $api = new Admin();
    $taxService = Mockery::mock(ServiceTax::class);
    $taxService->shouldReceive('update')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $model = new Model_Tax();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $taxService);

    $api->setDi($di);
    $api->setService($taxService);
    $api->setIdentity(new Model_Admin());

    $data['id'] = 1;
    $result = $api->tax_update($data);
    expect($result)->toBeBool()->toBeTrue();
});
