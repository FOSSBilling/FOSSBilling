<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use FOSSBilling\Crypt;
use FOSSBilling\Doctrine\EntityManagerFactory;
use FOSSBilling\Doctrine\JsonMigration;
use FOSSBilling\Doctrine\JsonMigrationException;

beforeEach(function (): void {
    $this->jsonMigrationConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
});

test('a late invalid row blocks the automatic migration before earlier source values change', function (): void {
    $c = $this->jsonMigrationConnection;
    $c->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT)');
    $c->executeStatement('CREATE TABLE session (id VARCHAR(255) PRIMARY KEY, fingerprint TEXT)');
    $c->insert('product', ['id' => 1, 'config' => '']);
    $c->insert('session', ['id' => 'session-secret', 'fingerprint' => '{payload-secret']);

    try {
        (new JsonMigration())->automatic(EntityManagerFactory::create($c), new Crypt());
        $this->fail('Invalid source data was accepted.');
    } catch (JsonMigrationException $error) {
        expect($error->getMessage())->toContain('session.fingerprint id=sha256:')->not->toContain('session-secret', 'payload-secret');
    }
    expect($c->fetchOne('SELECT config FROM product'))->toBe('')
        ->and($c->fetchOne('SELECT fingerprint FROM session'))->toBe('{payload-secret');
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
    expect($migration->automatic($em, new Crypt()))->toBe(['columns' => 1, 'empty_values' => 2])
        ->and($c->fetchFirstColumn('SELECT config FROM product ORDER BY id'))->toBe(array_map(fn ($raw) => $raw !== null && trim($raw) === '' ? null : $raw, $values))
        ->and($c->fetchFirstColumn('SELECT DISTINCT untouched FROM product'))->toBe(['same'])
        ->and($migration->automatic($em, new Crypt()))->toBe(['columns' => 0, 'empty_values' => 0])
        ->and((int) $c->fetchOne('SELECT COUNT(*) FROM json_migration_record'))->toBe(1);
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
