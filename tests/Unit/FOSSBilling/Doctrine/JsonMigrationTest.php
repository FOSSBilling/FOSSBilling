<?php

declare(strict_types=1);

use Box\Mod\System\Commands\JsonMigrate;
use Doctrine\DBAL\DriverManager;
use FOSSBilling\Config;
use FOSSBilling\Doctrine\JsonMigration;
use FOSSBilling\Doctrine\JsonMigrationException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->jsonMigrationConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $this->jsonBackupDirectory = sys_get_temp_dir() . '/fb-json-backup-' . bin2hex(random_bytes(6));
    (new Filesystem())->mkdir($this->jsonBackupDirectory, 0o700);
});

afterEach(function (): void {
    $this->jsonMigrationConnection->close();
    (new Filesystem())->remove($this->jsonBackupDirectory);
});

test('migration refuses the entire inventory before source changes when a late row is invalid', function (): void {
    $c = $this->jsonMigrationConnection;
    $c->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT)');
    $c->executeStatement('CREATE TABLE session (id VARCHAR(255) PRIMARY KEY, fingerprint TEXT)');
    $c->insert('product', ['id' => -1, 'config' => '']);
    $c->insert('session', ['id' => 'session-id', 'fingerprint' => '{private-secret']);
    $file = $this->jsonBackupDirectory . '/backup.jsonl';
    expect(fn () => (new JsonMigration())->migrate($c, $file))->toThrow(JsonMigrationException::class, 'session.fingerprint id=sha256:');
    expect($c->fetchOne('SELECT config FROM product'))->toBe('')
        ->and($c->fetchOne('SELECT fingerprint FROM session'))->toBe('{private-secret')
        ->and(file_get_contents($file))->not->toContain('"complete":true');
});

test('migration backs up exact bytes privately, normalizes only empty strings and safely repeats', function (): void {
    $c = $this->jsonMigrationConnection;
    $c->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT, untouched TEXT)');
    $values = [null, '', " \t\n", '{}', '[]', 'null', '{ "id":"001", "n":1.0, "label":"🚀" }'];
    foreach ($values as $id => $raw) {
        $c->insert('product', ['id' => $id - 2, 'config' => $raw, 'untouched' => 'same']);
    }
    $migration = new JsonMigration();
    expect(fn () => $migration->assertReady($c))->toThrow(JsonMigrationException::class);
    $file = $this->jsonBackupDirectory . '/backup.jsonl';
    expect($migration->migrate($c, $file))->toBe(['columns' => 1, 'empty_values' => 2]);
    $records = array_map(fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), file($file, FILE_IGNORE_NEW_LINES));
    $rows = array_values(array_filter($records, fn ($row) => array_key_exists('base64', $row)));
    expect(array_map(fn ($row) => $row['base64'] === null ? null : base64_decode($row['base64'], true), $rows))->toBe($values)
        ->and(fileperms($file) & 0o777)->toBe(0o600)
        ->and(end($records))->toBe(['complete' => true])
        ->and($c->fetchFirstColumn('SELECT config FROM product ORDER BY id'))->toBe(array_map(fn ($raw) => $raw !== null && trim($raw) === '' ? null : $raw, $values));
    $migration->assertReady($c);
    expect($migration->migrate($c, $this->jsonBackupDirectory . '/retry.jsonl'))->toBe(['columns' => 1, 'empty_values' => 0])
        ->and($c->fetchFirstColumn('SELECT DISTINCT untouched FROM product'))->toBe(['same']);
    // Existing backup files are never overwritten.
    set_error_handler(static fn (): bool => true);

    try {
        expect(fn () => $migration->migrate($c, $file))->toThrow(JsonMigrationException::class);
    } finally {
        restore_error_handler();
    }
});

test('migration and audit page string session identifiers without truncation or repetition', function (): void {
    $c = $this->jsonMigrationConnection;
    $c->executeStatement('CREATE TABLE session (id VARCHAR(255) PRIMARY KEY, fingerprint TEXT)');
    for ($i = 0; $i < 503; ++$i) {
        $c->insert('session', ['id' => sprintf('session-%04d', $i), 'fingerprint' => '']);
    }
    $issues = iterator_to_array((new FOSSBilling\Doctrine\JsonAudit())->inspect($c, 'session', 100));
    expect(count($issues))->toBe(503)->and($issues[502]['id'])->toBe('sha256:' . hash('sha256', 'session-0502'));
    expect((new JsonMigration())->migrate($c, $this->jsonBackupDirectory . '/sessions.jsonl'))->toBe(['columns' => 1, 'empty_values' => 503])
        ->and((int) $c->fetchOne('SELECT COUNT(*) FROM session WHERE fingerprint IS NULL'))->toBe(503);
});

test('migration refuses customized columns and indexes without rewriting anything', function (string $definition): void {
    $c = $this->jsonMigrationConnection;
    $c->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config ' . $definition . ')');
    if ($definition === 'TEXT') {
        $c->executeStatement('CREATE INDEX customized_config ON product (config)');
    }
    $c->insert('product', ['id' => 1, 'config' => '']);
    expect(fn () => (new JsonMigration())->migrate($c, $this->jsonBackupDirectory . '/backup.jsonl'))->toThrow(JsonMigrationException::class)
        ->and($c->fetchOne('SELECT config FROM product'))->toBe('');
})->with(['TEXT NOT NULL', "TEXT DEFAULT '{}'", 'TEXT']);

test('an interrupted migration retains a complete original backup and can resume with a new backup', function (): void {
    $c = $this->jsonMigrationConnection;
    $c->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT)');
    $c->insert('product', ['id' => 1, 'config' => '']);
    $c->insert('product', ['id' => 2, 'config' => '']);
    $c->executeStatement("CREATE TRIGGER interrupt_json BEFORE UPDATE OF config ON product WHEN OLD.id = 2 BEGIN SELECT RAISE(ABORT, 'private-secret'); END");
    $file = $this->jsonBackupDirectory . '/original.jsonl';
    expect(fn () => (new JsonMigration())->migrate($c, $file))->toThrow(Doctrine\DBAL\Exception\DriverException::class);
    expect($c->fetchFirstColumn('SELECT config FROM product ORDER BY id'))->toBe([null, ''])
        ->and(file_get_contents($file))->toContain('"complete":true');
    $original = file_get_contents($file);
    $c->executeStatement('DROP TRIGGER interrupt_json');
    expect((new JsonMigration())->migrate($c, $this->jsonBackupDirectory . '/retry.jsonl')['empty_values'])->toBe(1)
        ->and(file_get_contents($file))->toBe($original);
});

test('migration command defaults to no action and requires maintenance and stopped writers', function (): void {
    $command = new JsonMigrate();
    $command->setDi(new Pimple\Container(['dbal' => $this->jsonMigrationConnection]));
    $tester = new CommandTester($command);
    expect($tester->execute([]))->toBe(0)->and($tester->getDisplay())->toContain('No data was modified');
    expect($tester->execute(['--apply' => true]))->toBe(2);
    $config = Config::getConfig();
    $changed = $config;
    $changed['maintenance_mode']['enabled'] = true;
    Config::setConfig($changed, false);

    try {
        expect($tester->execute(['--apply' => true, '--writers-stopped' => true, '--backup-file' => $this->jsonBackupDirectory . '/command.jsonl']))->toBe(0);
        $this->jsonMigrationConnection->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT)');
        $this->jsonMigrationConnection->insert('product', ['id' => 1, 'config' => '{private-secret']);
        expect($tester->execute(['--apply' => true, '--writers-stopped' => true, '--backup-file' => $this->jsonBackupDirectory . '/blocked.jsonl']))->toBe(1)
            ->and($tester->getDisplay())->not->toContain('private-secret');
    } finally {
        Config::setConfig($config, false);
    }
});
