<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\ServiceTransaction;
use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;

require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';

/** Inject another connection's commit at an actual transaction write boundary. */
class ReceiptRecoveryConnection extends Connection
{
    public ?Closure $beforeTransactionWrite = null;
    public ?Closure $afterTransactionWrite = null;

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        $transactionWrite = str_starts_with($sql, 'UPDATE') && str_contains($sql, 'transaction');
        if ($transactionWrite && $this->beforeTransactionWrite !== null) {
            $callback = $this->beforeTransactionWrite;
            $this->beforeTransactionWrite = null;
            $callback();
        }
        $affected = parent::executeStatement($sql, $params, $types);
        if ($transactionWrite && $this->afterTransactionWrite !== null) {
            $callback = $this->afterTransactionWrite;
            $this->afterTransactionWrite = null;
            $callback();
        }

        return $affected;
    }
}

test('a receipt completed after a recovery claim never resolves a provider adapter', function (string $gatewayName, string $entrypoint): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext(Tests\Helpers\fencedConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => ReceiptRecoveryConnection::class]));
    $gateway->setGateway($gatewayName);
    $transaction->setStatus(Transaction::STATUS_PROCESSED)->setGatewayCompletionState(Transaction::GATEWAY_COMPLETION_RUNNING);
    $em->flush();
    $connection = $em->getConnection();
    $table = $connection->quoteSingleIdentifier('transaction');
    $id = $transaction->getId();
    $connection->update($table, ['updated_at' => date('Y-m-d H:i:s', time() - 600)], ['id' => $id]);
    $connection->afterTransactionWrite = function () use ($connection, $table, $id): void {
        // The original completion owner acknowledges after the new claim.
        $connection->update($table, ['gateway_completion_state' => Transaction::GATEWAY_COMPLETION_DONE], ['id' => $id]);
    };
    $billing = gatewayDepositTestService($em);
    $di = $billing->getDi();
    $resolutions = 0;
    $di['mod_service'] = $di->protect(function () use (&$resolutions): never {
        ++$resolutions;

        throw new LogicException('Completed receipts must not resolve an adapter or rerun completion');
    });
    $worker = Mockery::mock(ServiceTransaction::class)->makePartial();
    $worker->setDi($di);
    if ($entrypoint === 'synchronous') {
        $worker->shouldReceive('create')->once()->with([])->andReturn($id);
        expect($worker->createAndProcess([]))->toBe($id);
    } elseif ($entrypoint === 'admin') {
        expect($worker->preProcessTransaction($transaction))->toBeTrue();
    } else {
        $worker->processAndCatchErrors($id);
    }
    expect($resolutions)->toBe(0);
    expect($transaction->getStatus())->toBe(Transaction::STATUS_PROCESSED)
        ->and($transaction->getGatewayCompletionState())->toBe(Transaction::GATEWAY_COMPLETION_DONE)
        ->and($transaction->getError())->toBeNull();
})->with(['Stripe', 'PayPalEmail'])->with(['background', 'synchronous', 'admin']);

test('error recording respects another workers financial commit and completion acknowledgement', function (string $observedStatus, ?int $committedState, string $backend): void {
    $filesystem = new Filesystem();
    $admin = null;
    $path = null;
    if ($backend === 'mysql') {
        $port = getenv('PAYMENT_TEST_MYSQL_PORT');
        if ($port === false || $port === '') {
            $this->markTestSkipped('Set PAYMENT_TEST_MYSQL_PORT for isolated MySQL/MariaDB receipt recovery tests.');
        }
        $params = ['driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => (int) $port, 'user' => 'root', 'password' => getenv('PAYMENT_TEST_MYSQL_PASSWORD') ?: ''];
        $admin = Tests\Helpers\fencedConnection($params);
        $database = 'receipt_recovery_' . bin2hex(random_bytes(6));
        $admin->executeStatement('CREATE DATABASE ' . $database);
        $params['dbname'] = $database;
    } else {
        $path = $filesystem->tempnam(sys_get_temp_dir(), 'fb-receipt-recovery-');
        $params = ['driver' => 'pdo_sqlite', 'path' => $path];
    }
    $connection = Tests\Helpers\fencedConnection($params + ['wrapperClass' => ReceiptRecoveryConnection::class]);
    $owner = Tests\Helpers\fencedConnection($params);

    try {
        [, $em, , , $transaction] = receiptTestContext($connection);
        $transaction->setStatus($observedStatus)->setGatewayCompletionState($observedStatus === Transaction::STATUS_PROCESSED ? Transaction::GATEWAY_COMPLETION_RUNNING : Transaction::GATEWAY_COMPLETION_DONE);
        $em->flush();
        $transaction->setProcessingToken(str_repeat('a', 32));
        $em->flush();
        $id = $transaction->getId();
        $table = $connection->quoteSingleIdentifier('transaction');
        if ($committedState !== null) {
            $connection->beforeTransactionWrite = function () use ($owner, $table, $id, $committedState): void {
                $owner->update($table, ['status' => Transaction::STATUS_PROCESSED, 'gateway_completion_state' => $committedState, 'error' => null, 'error_code' => null], ['id' => $id]);
            };
        }
        $worker = new ServiceTransaction();
        $worker->setDi(gatewayDepositTestService($em)->getDi());
        (new ReflectionProperty($worker, 'processingTokens'))->setValue($worker, [$id => str_repeat('a', 32)]);
        (new ReflectionMethod($worker, 'markTransactionError'))->invoke($worker, $id, new RuntimeException('completion failed', 123));
        $done = $committedState === Transaction::GATEWAY_COMPLETION_DONE;
        $status = $committedState !== null || $observedStatus === Transaction::STATUS_PROCESSED ? Transaction::STATUS_PROCESSED : Transaction::STATUS_ERROR;
        $state = $done ? Transaction::GATEWAY_COMPLETION_DONE : ($status === Transaction::STATUS_PROCESSED ? Transaction::GATEWAY_COMPLETION_PENDING : Transaction::GATEWAY_COMPLETION_DONE);
        expect($transaction->getStatus())->toBe($status)
            ->and($transaction->getGatewayCompletionState())->toBe($state)
            ->and($transaction->getError())->toBe($done ? null : 'completion failed')
            ->and($transaction->getErrorCode())->toBe($done ? null : 123);
        // A subsequent unrelated flush cannot restore stale completion data.
        $transaction->setNote('staff note');
        $em->flush();
        expect((int) $owner->fetchOne("SELECT gateway_completion_state FROM $table WHERE id = ?", [$id]))->toBe($state)
            ->and(array_column($worker->getReceived(), 'id'))->when($done, fn ($expectation) => $expectation->not->toContain($id));
    } finally {
        $connection->close();
        $owner->close();
        if ($admin !== null) {
            $admin->executeStatement('DROP DATABASE ' . $database);
            $admin->close();
        }
        if ($path !== null) {
            $filesystem->remove($path);
        }
    }
})->with([
    'acknowledgement beats stale completion error' => [Transaction::STATUS_PROCESSED, Transaction::GATEWAY_COMPLETION_DONE],
    'settled receipt beats stale processing error' => [Transaction::STATUS_PROCESSING, Transaction::GATEWAY_COMPLETION_DONE],
    'financial commit stays processed and retryable' => [Transaction::STATUS_PROCESSING, Transaction::GATEWAY_COMPLETION_RUNNING],
    'unfinished completion remains retryable' => [Transaction::STATUS_PROCESSED, null],
    'ordinary processing error remains diagnosable' => [Transaction::STATUS_PROCESSING, null],
])->with(['sqlite', 'mysql']);
