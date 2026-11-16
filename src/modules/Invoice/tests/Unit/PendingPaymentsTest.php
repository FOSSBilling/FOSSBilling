<?php

declare(strict_types=1);

use Box\Mod\Invoice\PendingPayments;
use Doctrine\DBAL\DriverManager;

test('a payment stays pending until its gateway status changes', function (): void {
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $db->executeStatement('CREATE TABLE "transaction" (id INTEGER PRIMARY KEY, invoice_id INTEGER, status TEXT, txn_status TEXT, amount TEXT, currency TEXT, created_at TEXT)');
    $db->executeStatement("INSERT INTO \"transaction\" VALUES (1, 9, 'received', 'processing', '10.00', 'USD', '2020-01-01 00:00:00')");

    expect(PendingPayments::forInvoice($db, 9))->not->toBeNull()
        ->and((int) $db->fetchOne('SELECT ' . PendingPayments::sql('9', $db)))->toBe(1);

    $db->executeStatement("UPDATE \"transaction\" SET status = 'processing' WHERE id = 1");
    expect(PendingPayments::forInvoice($db, 9))->not->toBeNull()
        ->and((int) $db->fetchOne('SELECT ' . PendingPayments::sql('9', $db)))->toBe(1);

    $db->executeStatement("UPDATE \"transaction\" SET status = 'error' WHERE id = 1");
    expect(PendingPayments::forInvoice($db, 9))->not->toBeNull()
        ->and((int) $db->fetchOne('SELECT ' . PendingPayments::sql('9', $db)))->toBe(1);

    $db->executeStatement("UPDATE \"transaction\" SET txn_status = 'failed' WHERE id = 1");
    expect(PendingPayments::forInvoice($db, 9))->toBeNull()
        ->and((int) $db->fetchOne('SELECT ' . PendingPayments::sql('9', $db)))->toBe(0);
});
