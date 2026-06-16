<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\ServiceTransaction;

use function Tests\Helpers\container;

test('gets dependency injection container', function (): void {
    $service = new ServiceTransaction();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('updates a transaction', function (): void {
    $service = new ServiceTransaction();
    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $transactionModel = new Model_Transaction();
    $transactionModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventsMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $data = [
        'invoice_id' => 1,
        'txn_id' => 2,
        'txn_status' => '',
        'gateway_id' => 1,
        'amount' => '',
        'currency' => '',
        'type' => '',
        'note' => '',
        'status' => '',
        'validate_ipn' => '',
    ];
    $result = $service->update($transactionModel, $data);
    expect($result)->toBeTrue();
});

test('throws exception when creating transaction with missing invoice id', function (): void {
    $service = new ServiceTransaction();
    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventsMock;

    $service->setDi($di);

    $data = [
        'skip_validation' => false,
    ];

    expect(fn () => $service->create($data))
        ->toThrow(FOSSBilling\Exception::class, 'Transaction invoice ID is missing');
});

test('throws exception when creating transaction with missing gateway id', function (): void {
    $service = new ServiceTransaction();
    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventsMock;

    $service->setDi($di);

    $data = [
        'skip_validation' => false,
        'invoice_id' => 2,
    ];

    expect(fn () => $service->create($data))
        ->toThrow(FOSSBilling\Exception::class, 'Payment gateway ID is missing');
});

test('deletes a transaction', function (): void {
    $service = new ServiceTransaction();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $transactionModel = new Model_Transaction();
    $transactionModel->loadBean(new Tests\Helpers\DummyBean());

    $result = $service->delete($transactionModel);
    expect($result)->toBeTrue();
});

test('converts to api array', function (): void {
    $service = new ServiceTransaction();
    $dbMock = Mockery::mock('\Box_Database');
    $payGatewayModel = new Model_PayGateway();
    $payGatewayModel->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($payGatewayModel);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $expected = [
        'id' => null,
        'invoice_id' => null,
        'txn_id' => null,
        'txn_status' => null,
        'gateway_id' => 1,
        'gateway' => null,
        'amount' => null,
        'currency' => null,
        'type' => null,
        'status' => null,
        'ip' => null,
        'validate_ipn' => null,
        'error' => null,
        'error_code' => null,
        'note' => null,
        'created_at' => null,
        'updated_at' => null,
    ];

    $transactionModel = new Model_Transaction();
    $transactionModel->loadBean(new Tests\Helpers\DummyBean());
    $transactionModel->gateway_id = 1;

    $result = $service->toApiArray($transactionModel, false);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('gets search query with various parameters', function (array $data, array $expectedParams, string $expectedStringPart): void {
    $service = new ServiceTransaction();
    $di = container();

    $service->setDi($di);
    $result = $service->getSearchQuery($data);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect(str_contains((string) $result[0], $expectedStringPart))->toBeTrue();
    expect($result[1])->toBe($expectedParams);
})->with([
    [
        [], [], 'SELECT m.*',
    ],
    [
        ['search' => 'keyword'], ['note' => '%keyword%', 'search_invoice_id' => '%keyword%', 'search_txn_id' => '%keyword%', 'ipn' => '%keyword%'], 'AND (m.note LIKE :note OR m.invoice_id LIKE :search_invoice_id OR m.txn_id LIKE :search_txn_id OR m.ipn LIKE :ipn)',
    ],
    [
        ['invoice_hash' => 'hashString'], ['hash' => 'hashString'], 'AND i.hash = :hash',
    ],
    [
        ['invoice_id' => '1'], ['invoice_id' => '1'], 'AND m.invoice_id = :invoice_id',
    ],
    [
        ['gateway_id' => '2'], ['gateway_id' => '2'], 'AND m.gateway_id = :gateway_id',
    ],
    [
        ['client_id' => '3'], ['client_id' => '3'], 'AND i.client_id = :client_id',
    ],
    [
        ['status' => 'active'], ['status' => 'active'], 'AND m.status = :status',
    ],
    [
        ['currency' => 'Eur'], ['currency' => 'Eur'], 'AND m.currency = :currency',
    ],
    [
        ['type' => 'payment'], ['type' => 'payment'], 'AND m.type = :type',
    ],
    [
        ['txn_id' => 'longTxn_id'], ['txn_id' => 'longTxn_id'], 'AND m.txn_id = :txn_id',
    ],
    [
        ['date_from' => '2012-12-12'], ['date_from' => 1_355_270_400], 'AND UNIX_TIMESTAMP(m.created_at) >= :date_from',
    ],
    [
        ['date_to' => '2012-12-12'], ['date_to' => 1_355_270_400], 'AND UNIX_TIMESTAMP(m.created_at) <= :date_to',
    ],
]);

test('counts transactions', function (): void {
    $service = new ServiceTransaction();
    $queryResult = [['status' => Model_Transaction::STATUS_RECEIVED, 'counter' => 1]];
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn($queryResult);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->counter();
    expect($result)->toBeArray();
});

test('createAndProcess marks transaction as error when processing throws', function (): void {
    $transactionModel = new Model_Transaction();
    $transactionModel->loadBean(new Tests\Helpers\DummyBean());
    $transactionModel->id = 1;
    $transactionModel->status = Model_Transaction::STATUS_RECEIVED;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->with('Transaction', 1)
        ->andReturn($transactionModel);
    $dbMock->shouldReceive('load')
        ->with('Transaction', 1)
        ->andReturn($transactionModel);
    $dbMock->shouldReceive('store')
        ->with($transactionModel)
        ->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service = Mockery::mock(ServiceTransaction::class)->makePartial();
    $service->shouldReceive('create')->once()->andReturn(1);
    $service->shouldReceive('processTransaction')
        ->with(1)
        ->once()
        ->andThrow(new RuntimeException('Processing failed'));
    $service->setDi($di);

    $thrown = null;

    try {
        $service->createAndProcess([]);
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(RuntimeException::class)
        ->and($thrown->getMessage())->toBe('Processing failed')
        ->and($transactionModel->status)->toBe(Model_Transaction::STATUS_ERROR)
        ->and($transactionModel->error)->toBe('Processing failed');
});

test('createAndProcess skips processing when transaction is already processed', function (): void {
    $transactionModel = new Model_Transaction();
    $transactionModel->loadBean(new Tests\Helpers\DummyBean());
    $transactionModel->id = 1;
    $transactionModel->status = Model_Transaction::STATUS_PROCESSED;
    $transactionModel->error = null;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->with('Transaction', 1)
        ->andReturn($transactionModel);
    $dbMock->shouldNotReceive('store');

    $di = container();
    $di['db'] = $dbMock;

    $service = Mockery::mock(ServiceTransaction::class)->makePartial();
    $service->shouldReceive('create')->once()->andReturn(1);
    $service->shouldNotReceive('processTransaction');
    $service->setDi($di);

    $result = $service->createAndProcess([]);

    expect($result)->toBe(1);
});

test('preProcessTransaction marks error on a generic exception', function (): void {
    $transactionModel = new Model_Transaction();
    $transactionModel->loadBean(new Tests\Helpers\DummyBean());
    $transactionModel->id = 5;
    $transactionModel->status = Model_Transaction::STATUS_PROCESSING;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->with('Transaction', 5)
        ->andReturn($transactionModel);
    $dbMock->shouldReceive('store')
        ->with($transactionModel)
        ->once();

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldNotReceive('fire');

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventsMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service = Mockery::mock(ServiceTransaction::class)->makePartial();
    $service->shouldReceive('processTransaction')
        ->with(5)
        ->once()
        ->andThrow(new RuntimeException('Unexpected DB error'));
    $service->setDi($di);

    $thrown = null;

    try {
        $service->preProcessTransaction($transactionModel);
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(RuntimeException::class)
        ->and($transactionModel->status)->toBe(Model_Transaction::STATUS_ERROR)
        ->and($transactionModel->error)->toBe('Unexpected DB error');
});

test('claimForProcessing includes error status in claim query', function (): void {
    $service = new ServiceTransaction();

    $execArgs = [];
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->withArgs(function (string $sql, array $bindings) use (&$execArgs): bool {
            $execArgs = ['sql' => $sql, 'bindings' => $bindings];

            return true;
        })
        ->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->claimForProcessing(7);

    expect($result)->toBeTrue()
        ->and($execArgs['bindings'])->toContain(Model_Transaction::STATUS_ERROR)
        ->and($execArgs['bindings'])->toContain(Model_Transaction::STATUS_RECEIVED)
        ->and($execArgs['bindings'])->toContain(Model_Transaction::STATUS_PROCESSING)
        ->and($execArgs['sql'])->toContain('IN (?, ?)');
});

test('markTransactionError does not clobber an already processed transaction', function (): void {
    $transactionModel = new Model_Transaction();
    $transactionModel->loadBean(new Tests\Helpers\DummyBean());
    $transactionModel->id = 3;
    $transactionModel->status = Model_Transaction::STATUS_PROCESSED;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->with('Transaction', 3)
        ->andReturn($transactionModel);
    $dbMock->shouldNotReceive('store');

    $di = container();
    $di['db'] = $dbMock;

    $service = new ServiceTransaction();
    $service->setDi($di);

    $refl = new ReflectionClass($service);
    $method = $refl->getMethod('markTransactionError');
    $method->invoke($service, 3, new RuntimeException('late error'));

    expect($transactionModel->status)->toBe(Model_Transaction::STATUS_PROCESSED);
});
