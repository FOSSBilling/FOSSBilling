<?php

declare(strict_types=1);

use Box\Mod\Invoice\GatewayRefundAttempt;
use Doctrine\DBAL\DriverManager;
use FOSSBilling\Core\Exception\InformationException;

function refundAttemptConnection(): Doctrine\DBAL\Connection
{
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $db->executeStatement('CREATE TABLE invoice_gateway_refund_attempt (id VARCHAR(32) PRIMARY KEY, invoice_id BIGINT NOT NULL, fingerprint VARCHAR(64) NOT NULL, state VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL)');

    return $db;
}

test('a PayPal refund retry keeps its durable request id after rollback', function (): void {
    $db = refundAttemptConnection();
    $fingerprint = hash('sha256', 'refund');
    $id = GatewayRefundAttempt::prepare($db, 10, $fingerprint, true);
    $db->beginTransaction();
    GatewayRefundAttempt::complete($db, $id);
    $db->rollBack();
    expect(GatewayRefundAttempt::prepare($db, 10, $fingerprint, true))->toBe($id);
    expect(fn () => GatewayRefundAttempt::prepare($db, 10, hash('sha256', 'different'), true))->toThrow(InformationException::class, 'Reconcile');
    $db->beginTransaction();
    GatewayRefundAttempt::complete($db, $id);
    $db->commit();
    expect(GatewayRefundAttempt::prepare($db, 10, $fingerprint, true))->not->toBe($id);
});

test('ambiguous legacy and expired PayPal attempts cannot be resent automatically', function (): void {
    $db = refundAttemptConnection();
    $fingerprint = hash('sha256', 'refund');
    GatewayRefundAttempt::prepare($db, 10, $fingerprint, false);
    expect(fn () => GatewayRefundAttempt::prepare($db, 10, $fingerprint, false))->toThrow(InformationException::class, 'Reconcile');
    $db->executeStatement("UPDATE invoice_gateway_refund_attempt SET created_at = '2020-01-01 00:00:00'");
    expect(fn () => GatewayRefundAttempt::prepare($db, 10, $fingerprint, true))->toThrow(InformationException::class, 'Reconcile');
});

test('a refund attempt cannot be prepared inside a transaction', function (): void {
    $db = refundAttemptConnection();
    $db->beginTransaction();
    expect(fn () => GatewayRefundAttempt::prepare($db, 10, hash('sha256', 'refund'), true))->toThrow(InformationException::class, 'inside a database transaction');
    $db->rollBack();
});

test('staff can dismiss only the matching pending attempt', function (): void {
    $db = refundAttemptConnection();
    $id = GatewayRefundAttempt::prepare($db, 10, hash('sha256', 'refund'), false);
    expect(GatewayRefundAttempt::pending($db, 10)['id'])->toBe($id);
    expect(fn () => GatewayRefundAttempt::dismiss($db, 11, $id))->toThrow(InformationException::class, 'no longer pending');
    GatewayRefundAttempt::dismiss($db, 10, $id);
    expect(GatewayRefundAttempt::pending($db, 10))->toBeNull();
    expect(fn () => GatewayRefundAttempt::dismiss($db, 10, $id))->toThrow(InformationException::class, 'no longer pending');
    expect(GatewayRefundAttempt::prepare($db, 10, hash('sha256', 'new'), false))->not->toBe($id);
});
