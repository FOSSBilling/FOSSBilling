<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Client\Repository\ClientBalanceRepository;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Event\AfterAdminTransactionCreateEvent;
use Box\Mod\Invoice\Event\AfterAdminTransactionProcessEvent;
use Box\Mod\Invoice\Event\AfterAdminTransactionUpdateEvent;
use Box\Mod\Invoice\Event\BeforeAdminTransactionCreateEvent;
use Box\Mod\Invoice\Event\BeforeAdminTransactionUpdateEvent;
use Box\Mod\Invoice\Repository\InvoiceRepository;
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use Box\Mod\Invoice\Repository\TransactionRepository;
use Box\Mod\Invoice\ServiceTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\setEntityId;

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
    $em = Mockery::mock(EntityManagerInterface::class);
    $flushed = false;
    $em->shouldReceive('flush')->once()->andReturnUsing(function () use (&$flushed): void {
        $flushed = true;
    });

    $invoice = createEntity(Invoice::class, ['id' => 1]);
    $gateway = createEntity(PayGateway::class, ['id' => 1]);

    $invoiceRepository = Mockery::mock(InvoiceRepository::class);
    $invoiceRepository->shouldReceive('find')->with(1)->andReturn($invoice);
    $payGatewayRepository = Mockery::mock(PayGatewayRepository::class);
    $payGatewayRepository->shouldReceive('find')->with(1)->andReturn($gateway);

    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepository);

    $transactionModel = createEntity(Transaction::class, ['id' => 1]);
    $events = [];
    $dispatcher = new SymfonyEventDispatcher();
    $dispatcher->addListener(BeforeAdminTransactionUpdateEvent::class, static function (BeforeAdminTransactionUpdateEvent $event) use (&$events, $transactionModel): void {
        $events[] = [$event, $transactionModel->getTxnId(), false];
    });
    $dispatcher->addListener(AfterAdminTransactionUpdateEvent::class, static function (AfterAdminTransactionUpdateEvent $event) use (&$events, $transactionModel, &$flushed): void {
        $events[] = [$event, $transactionModel->getTxnId(), $flushed];
    });

    $service = transactionService(payGatewayRepo: $payGatewayRepository, em: $em);
    $service->getDi()['event_dispatcher'] = $dispatcher;
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();

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
    expect($result)->toBeTrue()
        ->and($events)->toHaveCount(2)
        ->and($events[0][0])->toBeInstanceOf(BeforeAdminTransactionUpdateEvent::class)
        ->and($events[0][0]->transactionId)->toBe(1)
        ->and($events[0][1])->toBeNull()
        ->and($events[0][2])->toBeFalse()
        ->and($events[1][0])->toBeInstanceOf(AfterAdminTransactionUpdateEvent::class)
        ->and($events[1][0]->transactionId)->toBe(1)
        ->and($events[1][1])->toBe('2')
        ->and($events[1][2])->toBeTrue();
});

test('updates a transaction subscription link', function (): void {
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('flush')->atLeast()->once();

    $service = transactionService(em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();

    $transactionModel = createEntity(Transaction::class, ['id' => 1]);

    expect($service->update($transactionModel, ['s_id' => 'I-ABC123', 's_period' => '1M']))->toBeTrue()
        ->and($transactionModel->getSId())->toBe('I-ABC123')
        ->and($transactionModel->getSPeriod())->toBe('1M')
        ->and($service->update($transactionModel, ['s_id' => 'I-NEW']))->toBeTrue()
        ->and($transactionModel->getSId())->toBe('I-NEW')
        ->and($transactionModel->getSPeriod())->toBe('1M');
});

test('processed transaction history fields are frozen', function (): void {
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('flush')->once();

    $service = transactionService(em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();

    $transactionModel = createEntity(Transaction::class, ['id' => 1, 'amount' => '42.50', 'currency' => 'USD']);
    $transactionModel->setStatus(Transaction::STATUS_PROCESSED);

    // Money and history fields cannot change once the transaction processed.
    foreach ([['amount' => '99.99'], ['currency' => 'EUR'], ['txn_id' => 'other'], ['invoice_id' => 2]] as $data) {
        expect(fn () => $service->update($transactionModel, $data))
            ->toThrow(FOSSBilling\InformationException::class, 'record money that already moved');
    }

    // Operational annotations may still be edited.
    expect($service->update($transactionModel, ['note' => 'verified with gateway']))->toBeTrue()
        ->and($transactionModel->getNote())->toBe('verified with gateway');
});

test('processed transaction accepts re-submitted unchanged values', function (): void {
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('flush')->once();

    $service = transactionService(em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();

    $transactionModel = createEntity(Transaction::class, ['id' => 1, 'amount' => '42.50', 'currency' => 'USD']);
    $transactionModel->setStatus(Transaction::STATUS_PROCESSED);

    // The API renders DECIMAL '42.50' as float 42.5 and forms post '' for
    // nulls: re-submitting those round-tripped values changes nothing.
    expect($service->update($transactionModel, ['amount' => '42.5', 'currency' => 'USD', 'txn_status' => '', 'note' => 'x']))->toBeTrue();
});

test('transaction cannot be re-pointed to a canceled invoice', function (): void {
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldNotReceive('flush');

    $canceled = createEntity(Invoice::class, ['id' => 9]);
    $canceled->setStatus(Invoice::STATUS_CANCELED);
    $invoiceRepository = Mockery::mock(InvoiceRepository::class);
    $invoiceRepository->shouldReceive('find')->with(9)->andReturn($canceled);
    $em->shouldReceive('getRepository')->with(Invoice::class)->andReturn($invoiceRepository);

    $service = transactionService(em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();

    $transactionModel = createEntity(Transaction::class, ['id' => 1]);

    expect(fn () => $service->update($transactionModel, ['invoice_id' => 9]))
        ->toThrow(FOSSBilling\InformationException::class, 'canceled, refunded, or replaced');
});

test('processed transactions cannot be deleted', function (): void {
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldNotReceive('remove', 'flush');

    $service = transactionService(em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();

    $transactionModel = createEntity(Transaction::class, ['id' => 1]);
    $transactionModel->setStatus(Transaction::STATUS_PROCESSED);

    expect(fn () => $service->delete($transactionModel))
        ->toThrow(FOSSBilling\InformationException::class, 'record money that already moved');
});

test('deleting a transaction removes its orphaned balance rows', function (): void {
    $balance = createEntity(ClientBalance::class, ['id' => 7]);
    $balanceRepository = Mockery::mock(ClientBalanceRepository::class);
    $balanceRepository->shouldReceive('findBy')
        ->once()
        ->with(['type' => 'transaction', 'relId' => '1'])
        ->andReturn([$balance]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(ClientBalance::class)->andReturn($balanceRepository);
    $em->shouldReceive('remove')->twice();
    $em->shouldReceive('flush')->once();

    $service = transactionService(em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();

    $transactionModel = createEntity(Transaction::class, ['id' => 1]);

    expect($service->delete($transactionModel))->toBeTrue();
});

test('throws exception when creating transaction with missing invoice id', function (): void {
    $service = transactionService();
    $events = [];
    $dispatcher = new SymfonyEventDispatcher();
    $dispatcher->addListener(BeforeAdminTransactionCreateEvent::class, static function (BeforeAdminTransactionCreateEvent $event) use (&$events): void {
        $events[] = $event;
    });
    $service->getDi()['event_dispatcher'] = $dispatcher;

    $data = [
        'skip_validation' => false,
    ];

    expect(fn (): ?int => $service->create($data))
        ->toThrow(FOSSBilling\Exception::class, 'Transaction invoice ID is missing')
        ->and($events)->toHaveCount(1)
        ->and($events[0]->input)->toBe(['skip_validation' => false]);
});

test('throws exception when creating transaction with missing gateway id', function (): void {
    $service = transactionService();

    $data = [
        'skip_validation' => false,
        'invoice_id' => 2,
    ];

    expect(fn (): ?int => $service->create($data))
        ->toThrow(FOSSBilling\Exception::class, 'Payment gateway ID is missing');
});

test('creates a transaction with safe lifecycle events and excludes raw IPN and credential input', function (): void {
    $transactionRepository = Mockery::mock(TransactionRepository::class);
    $payGatewayRepository = Mockery::mock(PayGatewayRepository::class);
    $em = Mockery::mock(EntityManagerInterface::class);
    $flushed = false;
    $em->shouldReceive('persist')->once()->with(Mockery::on(static function (Transaction $transaction): bool {
        setEntityId($transaction, 27);

        return true;
    }));
    $em->shouldReceive('flush')->once()->andReturnUsing(function () use (&$flushed): void {
        $flushed = true;
    });

    $events = [];
    $dispatcher = new SymfonyEventDispatcher();
    $dispatcher->addListener(BeforeAdminTransactionCreateEvent::class, static function (BeforeAdminTransactionCreateEvent $event) use (&$events, &$flushed): void {
        $events[] = [$event, $flushed];
    });
    $dispatcher->addListener(AfterAdminTransactionCreateEvent::class, static function (AfterAdminTransactionCreateEvent $event) use (&$events, &$flushed): void {
        $events[] = [$event, $flushed];
    });

    $service = transactionService($transactionRepository, $payGatewayRepository, $em);
    $service->getDi()['event_dispatcher'] = $dispatcher;

    $input = [
        'skip_validation' => true,
        'source' => 'admin',
        'txn_id' => 'tx_safe_ref',
        'post' => ['token' => 'secret-post-token'],
        'get' => ['api_key' => 'secret-query-key'],
        'server' => ['REMOTE_ADDR' => '192.0.2.1'],
        'http_raw_post_data' => 'raw-secret-payload',
        'password' => 'secret-password',
        'config' => ['secret' => 'gateway-credential'],
    ];

    expect($service->create($input))->toBe(27)
        ->and($events)->toHaveCount(2)
        ->and($events[0][0])->toBeInstanceOf(BeforeAdminTransactionCreateEvent::class)
        ->and($events[0][0]->input)->toBe([
            'skip_validation' => true,
            'source' => 'admin',
            'txn_id' => 'tx_safe_ref',
        ])
        ->and($events[0][1])->toBeFalse()
        ->and($events[1][0])->toBeInstanceOf(AfterAdminTransactionCreateEvent::class)
        ->and($events[1][0]->transactionId)->toBe(27)
        ->and($events[1][1])->toBeTrue();
});

test('deletes a transaction', function (): void {
    $em = Mockery::mock(EntityManagerInterface::class);
    $balanceRepository = Mockery::mock(ClientBalanceRepository::class);
    $balanceRepository->shouldReceive('findBy')->andReturn([]);
    $em->shouldReceive('getRepository')->with(ClientBalance::class)->andReturn($balanceRepository);
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

    $service = transactionService();

    $transactionModel = createEntity(Transaction::class, ['id' => 5, 'gateway' => $payGatewayModel]);

    $result = $service->toApiArray($transactionModel, false);
    expect($result)->toBeArray();
    expect($result['gateway'])->toBe('Stripe')
        ->and($result['status'])->toBe(Transaction::STATUS_RECEIVED)
        ->and($result['amount'])->toBe(0.0);
});

test('converts to api array with an empty ipn payload', function (): void {
    $service = transactionService();

    $transactionModel = createEntity(Transaction::class, ['id' => 6]);

    $result = $service->toApiArray($transactionModel, true);
    expect($result['ipn'])->toBe([]);
});

test('converts a transaction result without database access', function (): void {
    $service = transactionService();

    $transaction = createEntity(Transaction::class, [
        'id' => 12,
        'invoice_id' => 34,
        'txn_id' => 'txn_123',
        'txn_status' => 'complete',
        'gateway_id' => 2,
        'amount' => '19.95',
        'currency' => 'USD',
        'type' => 'payment',
        'status' => 'processed',
        'ip' => '192.0.2.1',
        'validate_ipn' => true,
        'error' => null,
        'error_code' => null,
        'note' => 'Test payment',
        'created_at' => '2026-07-19 10:00:00',
        'updated_at' => '2026-07-19 10:01:00',
    ]);

    $result = $service->transactionResultToApiArray($transaction, 'Stripe');

    expect($result)->toMatchArray([
        'id' => 12,
        'gateway' => 'Stripe',
        'amount' => 19.95,
        'status' => 'processed',
        'validate_ipn' => true,
        'created_at' => '2026-07-19 10:00:00',
        'updated_at' => '2026-07-19 10:01:00',
    ]);
});

test('counts transactions', function (): void {
    $queryResult = [['status' => Transaction::STATUS_RECEIVED, 'counter' => 1]];
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('quoteSingleIdentifier')->with('transaction')->andReturn('"transaction"');
    $connection->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn($queryResult);

    $service = transactionService();
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn($connection);

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
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')->once()->andReturn(1);
    $em->shouldReceive('getConnection')->andReturn($connection);

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

test('preProcessTransaction returns a boolean result', function (): void {
    $transactionModel = createEntity(Transaction::class, ['id' => 5]);

    $events = [];
    $dispatcher = new SymfonyEventDispatcher();
    $dispatcher->addListener(AfterAdminTransactionProcessEvent::class, static function (AfterAdminTransactionProcessEvent $event) use (&$events): void {
        $events[] = $event;
    });

    $di = container();
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')->once()->andReturn(1);
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['event_dispatcher'] = $dispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service = Mockery::mock(ServiceTransaction::class)->makePartial();
    $service->shouldReceive('processTransaction')
        ->with(5)
        ->once()
        ->andReturnUsing(function () use (&$events): int {
            $events[] = 'processed';

            return 1;
        });
    $service->setDi($di);

    $result = $service->preProcessTransaction($transactionModel);
    expect($result)->toBeTrue()
        ->and($events)->toHaveCount(2)
        ->and($events[0])->toBe('processed')
        ->and($events[1])->toBeInstanceOf(AfterAdminTransactionProcessEvent::class)
        ->and($events[1]->transactionId)->toBe(5);
});

test('preProcessTransaction skips a transaction claimed by another worker', function (): void {
    $transactionModel = createEntity(Transaction::class, ['id' => 5]);

    $di = container();
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')->once()->andReturn(0);
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $logger = new Tests\Helpers\TestLogger();
    $di['logger'] = $logger;

    $service = Mockery::mock(ServiceTransaction::class)->makePartial();
    $service->shouldNotReceive('processTransaction');
    $service->setDi($di);

    expect($service->preProcessTransaction($transactionModel))->toBeTrue()
        ->and($logger->calls)->toContain([
            'method' => 'info',
            'params' => ['Skipped processing transaction #{id}: already claimed by another worker', ['id' => 5]],
        ]);
});

test('preProcessTransaction returns true when the adapter returns nothing', function (): void {
    $transactionModel = createEntity(Transaction::class, ['id' => 5]);

    $di = container();
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')->once()->andReturn(1);
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service = Mockery::mock(ServiceTransaction::class)->makePartial();
    $service->shouldReceive('processTransaction')
        ->with(5)
        ->once()
        ->andReturnNull();
    $service->setDi($di);

    $result = $service->preProcessTransaction($transactionModel);
    expect($result)->toBeTrue();
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
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')->once()->andReturn(1);
    $em->shouldReceive('getConnection')->andReturn($connection);

    $events = [];
    $dispatcher = new SymfonyEventDispatcher();
    $dispatcher->addListener(AfterAdminTransactionProcessEvent::class, static function (AfterAdminTransactionProcessEvent $event) use (&$events): void {
        $events[] = $event;
    });

    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = $dispatcher;
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
        ->and($transactionModel->getError())->toBe('Unexpected DB error')
        ->and($events)->toBeEmpty();
});

test('processes the rest of a received transaction batch after a failure', function (): void {
    $first = createEntity(Transaction::class, ['id' => 1]);
    $second = createEntity(Transaction::class, ['id' => 2]);

    $transactionRepository = Mockery::mock(TransactionRepository::class);
    $transactionRepository->shouldReceive('find')->once()->with(1)->andReturn($first);
    $transactionRepository->shouldReceive('find')->once()->with(2)->andReturn($second);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Transaction::class)->andReturn($transactionRepository);

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service = Mockery::mock(ServiceTransaction::class)->makePartial();
    $service->shouldReceive('getReceived')->once()->andReturn([
        ['id' => 1],
        ['id' => 2],
    ]);
    $service->shouldReceive('preProcessTransaction')->once()->with($first)->andThrow(new RuntimeException('First transaction failed'));
    $service->shouldReceive('preProcessTransaction')->once()->with($second)->andReturnTrue();
    $service->setDi($di);

    expect($service->processReceivedATransactions())->toBeTrue();
});

test('claimForProcessing includes error status in claim query', function (): void {
    $execArgs = [];
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')
        ->withArgs(function (string $sql, array $bindings) use (&$execArgs): bool {
            $execArgs = ['sql' => $sql, 'bindings' => $bindings];

            return true;
        })
        ->once()
        ->andReturn(1);

    $service = transactionService();
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn($connection);

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
