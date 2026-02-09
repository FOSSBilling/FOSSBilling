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
use Box\Mod\Invoice\ServiceTransaction;

beforeEach(function () {
    $this->service = new ServiceTransaction();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

test('updates a transaction', function () {
    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $transactionModel = new \Model_Transaction();
    $transactionModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventsMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

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
    $result = $this->service->update($transactionModel, $data);
    expect($result)->toBeTrue();
});

test('throws exception when creating transaction with missing invoice id', function () {
    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventsMock;

    $this->service->setDi($di);

    $data = [
        'skip_validation' => false,
    ];

    expect(fn () => $this->service->create($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Transaction invoice ID is missing');
});

test('throws exception when creating transaction with missing gateway id', function () {
    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventsMock;

    $this->service->setDi($di);

    $data = [
        'skip_validation' => false,
        'invoice_id' => 2,
    ];

    expect(fn () => $this->service->create($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Payment gateway ID is missing');
});

test('deletes a transaction', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once();

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $transactionModel = new \Model_Transaction();
    $transactionModel->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->delete($transactionModel);
    expect($result)->toBeTrue();
});

test('converts to api array', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $payGatewayModel = new \Model_PayGateway();
    $payGatewayModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($payGatewayModel);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

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

    $transactionModel = new \Model_Transaction();
    $transactionModel->loadBean(new \Tests\Helpers\DummyBean());
    $transactionModel->gateway_id = 1;

    $result = $this->service->toApiArray($transactionModel, false);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('gets search query with various parameters', function (array $data, array $expectedParams, string $expectedStringPart) {
    $di = container();

    $this->service->setDi($di);
    $result = $this->service->getSearchQuery($data);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect(str_contains($result[0], $expectedStringPart))->toBeTrue();
    expect($result[1])->toBe($expectedParams);
})->with([
    [
        [], [], 'SELECT m.*',
    ],
    [
        ['search' => 'keyword'], ['note' => '%keyword%', 'search_invoice_id' => '%keyword%', 'search_txn_id' => '%keyword%', 'ipn' => '%keyword%'], 'AND m.note LIKE :note OR m.invoice_id LIKE :search_invoice_id OR m.txn_id LIKE :search_txn_id OR m.ipn LIKE :ipn',
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

test('counts transactions', function () {
    $queryResult = [['status' => \Model_Transaction::STATUS_RECEIVED, 'counter' => 1]];
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn($queryResult);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->counter();
    expect($result)->toBeArray();
});
