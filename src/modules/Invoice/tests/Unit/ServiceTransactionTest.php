<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use Box\Mod\Invoice\Repository\SubscriptionRepository;
use Box\Mod\Invoice\Repository\TransactionRepository;
use Box\Mod\Invoice\ServiceSubscription;
use Box\Mod\Invoice\ServiceTransaction;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function transactionService(?TransactionRepository $transactionRepo = null, ?PayGatewayRepository $payGatewayRepo = null, ?EntityManagerInterface $em = null): ServiceTransaction
{
    $service = new ServiceTransaction();
    $di = container();
    $em ??= Mockery::mock(EntityManagerInterface::class);
    $transactionRepo ??= Mockery::mock(TransactionRepository::class);
    $payGatewayRepo ??= Mockery::mock(PayGatewayRepository::class);
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepo);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($payGatewayRepo);
    $di['em'] = $em;
    $service->setDi($di);

    return $service;
}

test('gets dependency injection container', function (): void {
    $repo = Mockery::mock(TransactionRepository::class);
    $service = transactionService($repo);

    expect($service->getDi())->toBeInstanceOf(Pimple\Container::class)
        ->and($service->getTransactionRepository())->toBe($repo);
});

test('updates a transaction', function (): void {
    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('flush')->atLeast()->once();

    $service = transactionService(em: $em);
    $service->getDi()['events_manager'] = $eventsMock;
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();

    $transactionModel = createEntity(Transaction::class, ['id' => 1]);

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
    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $service = transactionService();
    $service->getDi()['events_manager'] = $eventsMock;

    $data = [
        'skip_validation' => false,
    ];

    expect(fn () => $service->create($data))
        ->toThrow(FOSSBilling\Exception::class, 'Transaction invoice ID is missing');
});

test('throws exception when creating transaction with missing gateway id', function (): void {
    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $service = transactionService();
    $service->getDi()['events_manager'] = $eventsMock;

    $data = [
        'skip_validation' => false,
        'invoice_id' => 2,
    ];

    expect(fn () => $service->create($data))
        ->toThrow(FOSSBilling\Exception::class, 'Payment gateway ID is missing');
});

test('deletes a transaction', function (): void {
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('remove')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $service = transactionService(em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();

    $transactionModel = createEntity(Transaction::class, ['id' => 7]);

    $result = $service->delete($transactionModel);
    expect($result)->toBeTrue();
});

test('converts to api array', function (): void {
    $payGatewayModel = createEntity(PayGateway::class, ['id' => 1]);
    $payGatewayModel->setName('Stripe');

    $payGatewayRepo = Mockery::mock(PayGatewayRepository::class);
    $payGatewayRepo->shouldReceive('find')->atLeast()->once()->andReturn($payGatewayModel);

    $service = transactionService(payGatewayRepo: $payGatewayRepo);

    $transactionModel = createEntity(Transaction::class, ['id' => 5, 'gatewayId' => 1]);

    $result = $service->toApiArray($transactionModel, false);
    expect($result)->toBeArray();
    expect($result['gateway'])->toBe('Stripe')
        ->and($result['status'])->toBe(Transaction::STATUS_RECEIVED)
        ->and($result['amount'])->toBe(0.0);
});

test('converts a transaction search result without database access', function (): void {
    $service = transactionService();

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
    $service = transactionService();

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
    $queryResult = [['status' => Transaction::STATUS_RECEIVED, 'counter' => 1]];
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn($queryResult);

    $service = transactionService();
    $service->getDi()['db'] = $dbMock;

    $result = $service->counter();
    expect($result)->toBeArray();
});

test('createAndProcess marks transaction as error when processing throws', function (): void {
    $transactionModel = createEntity(Transaction::class, ['id' => 1]);
    $transactionModel->setStatus(Transaction::STATUS_RECEIVED);

    $transactionRepo = Mockery::mock(TransactionRepository::class);
    $transactionRepo->shouldReceive('find')->with(1)->andReturn($transactionModel);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepo);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));
    $em->shouldReceive('flush')->once();
    $em->shouldReceive('refresh')->with($transactionModel)->once();

    $di = container();
    $di['em'] = $em;
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
        ->and($transactionModel->getStatus())->toBe(Transaction::STATUS_ERROR)
        ->and($transactionModel->getError())->toBe('Processing failed')
        ->and($transactionModel->getErrorCode())->toBe(1234);
});

test('createAndProcess skips processing when transaction is already processed', function (): void {
    $transactionModel = createEntity(Transaction::class, ['id' => 1]);
    $transactionModel->setStatus(Transaction::STATUS_PROCESSED);

    $transactionRepo = Mockery::mock(TransactionRepository::class);
    $transactionRepo->shouldReceive('find')->with(1)->andReturn($transactionModel);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepo);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));
    $em->shouldNotReceive('flush');

    $di = container();
    $di['em'] = $em;

    $service = Mockery::mock(ServiceTransaction::class)->makePartial();
    $service->shouldReceive('create')->once()->andReturn(1);
    $service->shouldNotReceive('processTransaction');
    $service->setDi($di);

    $result = $service->createAndProcess([]);

    expect($result)->toBe(1);
});

test('preProcessTransaction marks error on a generic exception', function (): void {
    $transactionModel = createEntity(Transaction::class, ['id' => 5]);
    $transactionModel->setStatus(Transaction::STATUS_PROCESSING);

    $transactionRepo = Mockery::mock(TransactionRepository::class);
    $transactionRepo->shouldReceive('find')->with(5)->andReturn($transactionModel);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepo);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));
    $em->shouldReceive('flush')->once();
    $em->shouldReceive('refresh')->with($transactionModel)->once();

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldNotReceive('fire');

    $di = container();
    $di['em'] = $em;
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
        ->and($transactionModel->getStatus())->toBe(Transaction::STATUS_ERROR)
        ->and($transactionModel->getError())->toBe('Unexpected DB error');
});

test('claimForProcessing includes error status in claim query', function (): void {
    $execArgs = [];
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->withArgs(function (string $sql, array $bindings) use (&$execArgs): bool {
            $execArgs = ['sql' => $sql, 'bindings' => $bindings];

            return true;
        })
        ->once()
        ->andReturn(1);

    $service = transactionService();
    $service->getDi()['db'] = $dbMock;

    $result = $service->claimForProcessing(7);

    expect($result)->toBeTrue()
        ->and($execArgs['bindings'])->toContain(Transaction::STATUS_ERROR)
        ->and($execArgs['bindings'])->toContain(Transaction::STATUS_RECEIVED)
        ->and($execArgs['bindings'])->toContain(Transaction::STATUS_PROCESSING)
        ->and($execArgs['sql'])->toContain('IN (?, ?)');
});

test('markTransactionError does not clobber an already processed transaction', function (): void {
    $transactionModel = createEntity(Transaction::class, ['id' => 3]);
    $transactionModel->setStatus(Transaction::STATUS_PROCESSED);

    $transactionRepo = Mockery::mock(TransactionRepository::class);
    $transactionRepo->shouldReceive('find')->with(3)->andReturn($transactionModel);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepo);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));
    $em->shouldNotReceive('flush');
    $em->shouldReceive('refresh')->with($transactionModel)->once();

    $di = container();
    $di['em'] = $em;

    $service = new ServiceTransaction();
    $service->setDi($di);

    $refl = new ReflectionClass($service);
    $method = $refl->getMethod('markTransactionError');
    $method->invoke($service, 3, new RuntimeException('late error'));

    expect($transactionModel->getStatus())->toBe(Transaction::STATUS_PROCESSED);
});

test('_subscribe creates and persists a subscription from an approved transaction', function (): void {
    $tx = createEntity(Transaction::class, ['id' => 1, 'gatewayId' => 5]);
    $tx->setStatus(Transaction::STATUS_APPROVED);
    $tx->setInvoiceId(10);
    $tx->setSId('sub_gateway_1');
    $tx->setAmount('29.99');
    $tx->setCurrency('USD');
    $tx->setTxnId('txn_001');

    $transactionRepo = Mockery::mock(TransactionRepository::class);
    $transactionRepo->shouldReceive('findOneProcessedByTxnId')->andReturnNull();

    $invoice = new Model_Invoice();
    $invoice->loadBean(new Tests\Helpers\DummyBean());
    $invoice->id = 10;
    $invoice->client_id = 7;
    $invoice->currency = 'USD';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')->with('Invoice', 10)->andReturn($invoice);

    $subscriptionService = Mockery::mock(ServiceSubscription::class);
    $subscriptionService->shouldReceive('getSubscriptionPeriod')->with($invoice)->andReturn('1M');

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepo);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));
    $em->shouldReceive('persist')->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire');

    $di = container();
    $di['db'] = $dbMock;
    $di['em'] = $em;
    $di['events_manager'] = $eventsMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn ($module, $sub = '') => $subscriptionService);

    $service = new ServiceTransaction();
    $service->setDi($di);

    $refl = new ReflectionClass($service);
    $method = $refl->getMethod('_subscribe');
    $method->invoke($service, $tx);

    expect($tx->getStatus())->toBe(Transaction::STATUS_PROCESSED);
});

test('_unsubscribe looks up the subscription by sid and delegates to the subscription service', function (): void {
    $tx = createEntity(Transaction::class, ['id' => 1, 'gatewayId' => 5]);
    $tx->setStatus(Transaction::STATUS_APPROVED);
    $tx->setSId('sub_gateway_1');
    $tx->setTxnId('txn_001');

    $transactionRepo = Mockery::mock(TransactionRepository::class);
    $transactionRepo->shouldReceive('findOneProcessedByTxnId')->andReturnNull();

    $subscription = createEntity(Subscription::class, ['id' => 12]);
    $subscriptionRepo = Mockery::mock(SubscriptionRepository::class);
    $subscriptionRepo->shouldReceive('findOneBySid')
        ->once()
        ->with('sub_gateway_1')
        ->andReturn($subscription);

    $subscriptionService = Mockery::mock(ServiceSubscription::class);
    $subscriptionService->shouldReceive('unsubscribe')
        ->once()
        ->with(Mockery::on(fn ($arg): bool => $arg instanceof Subscription && $arg->getId() === 12));

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepo);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn($subscriptionRepo);
    $em->shouldReceive('flush')->atLeast()->once();

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire');

    $di = container();
    $di['em'] = $em;
    $di['events_manager'] = $eventsMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn ($module, $sub = '') => $subscriptionService);

    $service = new ServiceTransaction();
    $service->setDi($di);

    $refl = new ReflectionClass($service);
    $method = $refl->getMethod('_unsubscribe');
    $method->invoke($service, $tx);

    expect($tx->getStatus())->toBe(Transaction::STATUS_PROCESSED);
});
