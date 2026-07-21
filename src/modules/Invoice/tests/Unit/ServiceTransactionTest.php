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
use function Tests\Helpers\createEntity;

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

    $transactionModel = createEntity(\Box\Mod\Invoice\Entity\Transaction::class);

    $di = container();
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

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $transactionModel = createEntity(\Box\Mod\Invoice\Entity\Transaction::class);

    $result = $service->delete($transactionModel);
    expect($result)->toBeTrue();
});

test('converts to api array', function (): void {
    $service = new ServiceTransaction();
    $payGatewayEntity = new Box\Mod\Invoice\Entity\PayGateway();
    $payGatewayEntity->setName('Test Gateway');

    $payGatewayRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\PayGatewayRepository::class);
    $payGatewayRepoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($payGatewayEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\PayGateway::class)
        ->andReturn($payGatewayRepoMock);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $expected = [
        'id' => null,
        'invoice_id' => null,
        'txn_id' => null,
        'txn_status' => null,
        'gateway_id' => 1,
        'gateway' => 'Test Gateway',
        'amount' => 0.0,
        'currency' => null,
        'type' => null,
        'status' => 'received',
        'ip' => null,
        'validate_ipn' => true,
        'error' => null,
        'error_code' => null,
        'note' => null,
        'created_at' => null,
        'updated_at' => null,
    ];

    $transactionModel = createEntity(\Box\Mod\Invoice\Entity\Transaction::class, ['gateway_id' => 1]);

    $result = $service->toApiArray($transactionModel, false);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('converts a transaction search result without database access', function (): void {
    $service = new ServiceTransaction();
    $di = container();
    $di['db'] = static function (): never {
        throw new RuntimeException('Search result conversion must not access the database');
    };
    $service->setDi($di);

    $result = $service->searchResultToApiArray([
        'id' => 12,
        'invoice_id' => 34,
        'txn_id' => 'txn_123',
        'txn_status' => 'complete',
        'gateway_id' => 2,
        'gateway' => 'Stripe',
        'amount' => '19.95',
        'currency' => 'USD',
        'type' => 'payment',
        'status' => 'processed',
        'ip' => '192.0.2.1',
        'validate_ipn' => 1,
        'error' => null,
        'error_code' => null,
        'note' => 'Test payment',
        'created_at' => '2026-07-19 10:00:00',
        'updated_at' => '2026-07-19 10:01:00',
    ]);

    expect($result)->toMatchArray([
        'id' => 12,
        'gateway' => 'Stripe',
        'amount' => 19.95,
        'status' => 'processed',
    ]);
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
        [], [], 'LEFT JOIN pay_gateway as pg on m.gateway_id = pg.id',
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
        ['date_from' => '2012-12-12'], ['date_from' => strtotime('2012-12-12 00:00:00 UTC')], 'AND UNIX_TIMESTAMP(m.created_at) >= :date_from',
    ],
    [
        ['date_to' => '2012-12-12'], ['date_to' => strtotime('2012-12-12 00:00:00 UTC')], 'AND UNIX_TIMESTAMP(m.created_at) <= :date_to',
    ],
]);

test('counts transactions', function (): void {
    $service = new ServiceTransaction();
    $queryResult = [['status' => \Box\Mod\Invoice\Entity\Transaction::STATUS_RECEIVED, 'counter' => 1]];
    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn($queryResult);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $result = $service->counter();
    expect($result)->toBeArray();
});

test('createAndProcess marks transaction as error when processing throws', function (): void {
    $transactionEntity = new Box\Mod\Invoice\Entity\Transaction();
    $transactionEntity->setStatus(\Box\Mod\Invoice\Entity\Transaction::STATUS_RECEIVED);

    $transactionRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\TransactionRepository::class);
    $transactionRepoMock->shouldReceive('find')
        ->andReturn($transactionEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Transaction::class)
        ->andReturn($transactionRepoMock);
    $emMock->shouldReceive('persist')->andReturnNull();
    $emMock->shouldReceive('flush')->andReturnNull();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service = Mockery::mock(ServiceTransaction::class)->makePartial();
    $service->shouldReceive('create')->once()->andReturn(1);
    $service->shouldReceive('processTransaction')
        ->with(1)
        ->once()
        ->andThrow(new RuntimeException('Processing failed', 1234));
    $service->setDi($di);

    $thrown = null;

    try {
        $service->createAndProcess([]);
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(RuntimeException::class)
        ->and($thrown->getMessage())->toBe('Processing failed')
        ->and($transactionEntity->getStatus())->toBe(\Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR)
        ->and($transactionEntity->getError())->toBe('Processing failed')
        ->and($transactionEntity->getErrorCode())->toBe(1234);
});

test('createAndProcess skips processing when transaction is already processed', function (): void {
    $transactionEntity = new Box\Mod\Invoice\Entity\Transaction();
    $transactionEntity->setStatus(\Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED);

    $transactionRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\TransactionRepository::class);
    $transactionRepoMock->shouldReceive('find')
        ->andReturn($transactionEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Transaction::class)
        ->andReturn($transactionRepoMock);

    $di = container();
    $di['em'] = $emMock;

    $service = Mockery::mock(ServiceTransaction::class)->makePartial();
    $service->shouldReceive('create')->once()->andReturn(1);
    $service->shouldNotReceive('processTransaction');
    $service->setDi($di);

    $result = $service->createAndProcess([]);

    expect($result)->toBe(1);
});

test('preProcessTransaction marks error on a generic exception', function (): void {
    $transactionEntity = new Box\Mod\Invoice\Entity\Transaction();
    $idProp = new ReflectionProperty(Box\Mod\Invoice\Entity\Transaction::class, 'id');
    $idProp->setValue($transactionEntity, 5);
    $transactionEntity->setStatus(\Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSING);

    $transactionRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\TransactionRepository::class);
    $transactionRepoMock->shouldReceive('find')
        ->andReturn($transactionEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Transaction::class)
        ->andReturn($transactionRepoMock);
    $emMock->shouldReceive('persist')->andReturnNull();
    $emMock->shouldReceive('flush')->andReturnNull();

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldNotReceive('fire');

    $di = container();
    $di['em'] = $emMock;
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
        $service->preProcessTransaction($transactionEntity);
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(RuntimeException::class)
        ->and($transactionEntity->getStatus())->toBe(\Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR)
        ->and($transactionEntity->getError())->toBe('Unexpected DB error');
});

test('claimForProcessing includes error status in claim query', function (): void {
    $service = new ServiceTransaction();

    $execArgs = [];
    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('executeStatement')
        ->withArgs(function (string $sql, array $bindings) use (&$execArgs): bool {
            $execArgs = ['sql' => $sql, 'bindings' => $bindings];

            return true;
        })
        ->once()
        ->andReturn(1);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $result = $service->claimForProcessing(7);

    expect($result)->toBeTrue()
        ->and($execArgs['bindings'])->toContain(\Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR, \Box\Mod\Invoice\Entity\Transaction::STATUS_RECEIVED, \Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSING);
});

test('markTransactionError does not clobber an already processed transaction', function (): void {
    $transactionEntity = new Box\Mod\Invoice\Entity\Transaction();
    $idProp = new ReflectionProperty(Box\Mod\Invoice\Entity\Transaction::class, 'id');
    $idProp->setValue($transactionEntity, 3);
    $transactionEntity->setStatus(\Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED);

    $transactionRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\TransactionRepository::class);
    $transactionRepoMock->shouldReceive('find')
        ->andReturn($transactionEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Transaction::class)
        ->andReturn($transactionRepoMock);

    $di = container();
    $di['em'] = $emMock;

    $service = new ServiceTransaction();
    $service->setDi($di);

    $refl = new ReflectionClass($service);
    $method = $refl->getMethod('markTransactionError');
    $method->invoke($service, 3, new RuntimeException('late error'));

    expect($transactionEntity->getStatus())->toBe(\Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED);
});
