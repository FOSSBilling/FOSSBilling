<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;
use FOSSBilling\Core\Doctrine\JsonMigration;
use FOSSBilling\Core\Doctrine\JsonMigrationException;
use FOSSBilling\Core\Security\Crypt;

beforeEach(function (): void {
    $this->jsonMigrationConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
});

test('an invalid row is quarantined to NULL without blocking earlier source values', function (): void {
    $c = $this->jsonMigrationConnection;
    $c->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT)');
    $c->executeStatement('CREATE TABLE session (id VARCHAR(255) PRIMARY KEY, fingerprint TEXT)');
    $c->insert('product', ['id' => 1, 'config' => '']);
    $c->insert('session', ['id' => 'session-secret', 'fingerprint' => '{payload-secret']);

    $em = EntityManagerFactory::create($c);
    $result = (new JsonMigration())->automatic($em, new Crypt());
    expect($result['columns'])->toBe(2)
        ->and($result['empty_values'])->toBe(1)
        ->and($result['quarantined_values'])->toBe(1)
        ->and($result['decoded_values'])->toBe(0)
        ->and($result['quarantined'][0]['table'])->toBe('session')
        ->and($result['quarantined'][0]['column'])->toBe('fingerprint')
        ->and((string) $result['quarantined'][0]['id'])->toStartWith('sha256:')
        ->and($result['quarantined'][0]['issue'])->toBe('invalid_json')
        ->and(json_encode($result))->not->toContain('session-secret', 'payload-secret');
    expect($c->fetchOne('SELECT config FROM product'))->toBeNull()
        ->and($c->fetchOne('SELECT fingerprint FROM session'))->toBeNull()
        ->and((int) $c->fetchOne('SELECT COUNT(*) FROM json_migration_record'))->toBe(1);
    expect((new JsonMigration())->automatic($em, new Crypt()))->toBe(['columns' => 0, 'empty_values' => 0, 'quarantined_values' => 0, 'decoded_values' => 0]);
});

test('quarantined originals survive finalization for later review and repair', function (): void {
    $c = $this->jsonMigrationConnection;
    $c->executeStatement('CREATE TABLE `transaction` (id INTEGER PRIMARY KEY, ipn TEXT)');
    $c->insert('`transaction`', ['id' => 1, 'ipn' => '{broken-payload']);

    $em = EntityManagerFactory::create($c);
    $crypt = new Crypt();
    (new JsonMigration())->automatic($em, $crypt);
    $store = new FOSSBilling\Core\Doctrine\JsonQuarantineStore($c, $crypt);
    $found = $store->find('transaction', 'ipn', '1');
    expect($found)->not->toBeNull()
        ->and($found['original'])->toBe('{broken-payload')
        ->and($found['entry']['issue'])->toBe('invalid_json')
        ->and($store->list())->toHaveCount(1)
        ->and($store->list('transaction'))->toHaveCount(1)
        ->and($store->list('product'))->toBe([])
        ->and($c->fetchOne('SELECT ipn FROM `transaction` WHERE id = 1'))->toBeNull();
});

test('automatic conversion normalizes empty configuration values and keeps document meaning', function (): void {
    $c = $this->jsonMigrationConnection;
    $c->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT, untouched TEXT)');
    $values = [null, '', " \t\n", '{}', '[]', 'null', '{"0":"object","id":"001","fraction":1.0,"label":"🚀"}'];
    foreach ($values as $id => $value) {
        $c->insert('product', ['id' => $id, 'config' => $value, 'untouched' => 'same']);
    }
    $em = EntityManagerFactory::create($c);
    $migration = new JsonMigration();
    expect($migration->automatic($em, new Crypt()))->toBe(['columns' => 1, 'empty_values' => 2, 'quarantined_values' => 0, 'decoded_values' => 0, 'quarantined' => []])
        ->and($c->fetchFirstColumn('SELECT config FROM product ORDER BY id'))->toBe(array_map(fn ($raw) => $raw !== null && trim($raw) === '' ? null : $raw, $values))
        ->and($c->fetchFirstColumn('SELECT DISTINCT untouched FROM product'))->toBe(['same'])
        ->and($migration->automatic($em, new Crypt()))->toBe(['columns' => 0, 'empty_values' => 0, 'quarantined_values' => 0, 'decoded_values' => 0])
        ->and((int) $c->fetchOne('SELECT COUNT(*) FROM json_migration_record'))->toBe(1);
});

test('transaction IPN rows with legacy payloads migrate without blocking the update (#4680)', function (): void {
    $c = $this->jsonMigrationConnection;
    $c->executeStatement('CREATE TABLE `transaction` (id INTEGER PRIMARY KEY, ipn TEXT)');
    $valid = json_encode(['source' => 'ipn', 'get' => [], 'post' => ['txn_id' => 'abc'], 'http_raw_post_data' => 'txn_id=abc', 'server' => []], JSON_THROW_ON_ERROR);
    $doubleEncoded = json_encode($valid, JSON_THROW_ON_ERROR);
    $c->insert('`transaction`', ['id' => 1, 'ipn' => '{broken-payload']);
    $c->insert('`transaction`', ['id' => 2, 'ipn' => $doubleEncoded]);
    $c->insert('`transaction`', ['id' => 3, 'ipn' => $valid]);

    $em = EntityManagerFactory::create($c);
    $result = (new JsonMigration())->automatic($em, new Crypt());
    expect($result['columns'])->toBe(1)
        ->and($result['quarantined_values'])->toBe(1)
        ->and($result['decoded_values'])->toBe(1)
        ->and($result['quarantined'][0]['table'])->toBe('transaction')
        ->and((int) $result['quarantined'][0]['id'])->toBe(1)
        ->and($c->fetchOne('SELECT ipn FROM `transaction` WHERE id = 1'))->toBeNull()
        ->and(json_decode($c->fetchOne('SELECT ipn FROM `transaction` WHERE id = 2'), true))->toBe(json_decode($valid, true))
        ->and($c->fetchOne('SELECT ipn FROM `transaction` WHERE id = 3'))->toBe($valid);
    (new JsonMigration())->assertReady($c);
    expect((new JsonMigration())->automatic($em, new Crypt()))->toBe(['columns' => 0, 'empty_values' => 0, 'quarantined_values' => 0, 'decoded_values' => 0]);
});

test('recipient filters keep blocking the migration because NULL would lift all restrictions', function (): void {
    $c = $this->jsonMigrationConnection;
    $c->executeStatement('CREATE TABLE mod_massmailer (id INTEGER PRIMARY KEY, filter TEXT)');
    $c->insert('mod_massmailer', ['id' => 1, 'filter' => '{"client_groups":[7],"client_groups":[7]}']);

    expect(fn () => (new JsonMigration())->automatic(EntityManagerFactory::create($c), new Crypt()))
        ->toThrow(JsonMigrationException::class, 'JSON preflight failed for mod_massmailer.filter id=1');
    expect($c->fetchOne('SELECT filter FROM mod_massmailer'))->toBe('{"client_groups":[7],"client_groups":[7]}')
        ->and(FOSSBilling\Core\Doctrine\JsonMigrationJournal::hasRecord($c, FOSSBilling\Core\Doctrine\JsonMigrationJournal::COMPLETE))->toBeFalse();
});

test('customized columns and indexes require review without source changes', function (string $definition): void {
    $c = $this->jsonMigrationConnection;
    $c->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config ' . $definition . ')');
    if ($definition === 'TEXT') {
        $c->executeStatement('CREATE INDEX customized_config ON product (config)');
    }
    $c->insert('product', ['id' => 1, 'config' => '']);
    expect(fn () => (new JsonMigration())->automatic(EntityManagerFactory::create($c), new Crypt()))->toThrow(JsonMigrationException::class)
        ->and($c->fetchOne('SELECT config FROM product'))->toBe('');
})->with(['TEXT NOT NULL', "TEXT DEFAULT '{}'", 'TEXT']);
