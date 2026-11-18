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

test('pending payments follow typed order links and valid legacy references', function (?int $typedId, string $reference, bool $pending): void {
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $db->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, status TEXT)');
    $db->executeStatement('CREATE TABLE invoice_item (invoice_id INTEGER, type TEXT, order_id INTEGER, rel_id TEXT COLLATE RTRIM)');
    $db->executeStatement('CREATE TABLE "transaction" (invoice_id INTEGER, status TEXT, txn_status TEXT)');
    $db->insert('invoice', ['id' => 9, 'status' => 'unpaid']);
    $db->insert('invoice_item', ['invoice_id' => 9, 'type' => 'order', 'order_id' => $typedId, 'rel_id' => $reference]);
    $db->executeStatement("INSERT INTO \"transaction\" VALUES (9, 'processing', 'processing')");

    expect(PendingPayments::forOrder($db, 7))->toBe($pending);
    $db->executeStatement("UPDATE invoice SET status = 'paid'");
    expect(PendingPayments::forOrder($db, 7))->toBeFalse();
})->with([
    'typed link takes precedence' => [7, '99', true],
    'different typed order' => [8, '7', false],
    'legacy canonical' => [null, '7', true],
    'legacy padded' => [null, '007', true],
    'legacy trailing space' => [null, '7 ', false],
    'legacy fraction' => [null, '7.0', false],
]);
