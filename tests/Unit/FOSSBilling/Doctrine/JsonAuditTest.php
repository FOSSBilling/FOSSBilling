<?php

declare(strict_types=1);

use Box\Mod\System\Commands\JsonAudit as JsonAuditCommand;
use Doctrine\DBAL\DriverManager;
use FOSSBilling\Doctrine\JsonAudit;
use Symfony\Component\Console\Tester\CommandTester;

test('JSON audit scans all rows in bounded batches without changing or exposing payloads', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT, addons TEXT)');
    $values = [null, '', '{invalid-secret', '{}', '[]', 'null', 'true', '"secret"', '"{\\"private\\":1}"'];
    foreach ($values as $id => $value) {
        $connection->insert('product', ['id' => $id - 2, 'config' => $value]);
    }
    $before = $connection->fetchAllAssociative('SELECT * FROM product ORDER BY id');
    $issues = iterator_to_array((new JsonAudit())->inspect($connection, 'product', 2));
    expect(array_column($issues, 'issue'))->toBe(['empty_string', 'invalid_json', 'unexpected_root', 'unexpected_root', 'double_encoded_json'])
        ->and(array_column($issues, 'id'))->toBe([-1, 0, 4, 5, 6])
        ->and($connection->fetchAllAssociative('SELECT * FROM product ORDER BY id'))->toBe($before);
    $command = new JsonAuditCommand();
    $command->setDi(new Pimple\Container(['dbal' => $connection]));
    $tester = new CommandTester($command);
    expect($tester->execute(['--table' => 'product', '--batch-size' => '2']))->toBe(1)
        ->and($tester->getDisplay())->not->toContain('invalid-secret', 'private')
        ->and($tester->getDisplay())->toContain('No data was modified.');
    expect($tester->execute(['--batch-size' => '0']))->toBe(2);
    expect($tester->execute(['--table' => 'not_a_table']))->toBe(2);
});

test('JSON audit tolerates absent optional module tables and columns', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT)');
    $connection->insert('product', ['id' => 1, 'config' => '{"label":"🚀"}']);
    expect(iterator_to_array((new JsonAudit())->inspect($connection)))->toBe([]);
});
