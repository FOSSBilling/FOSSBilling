<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DataCollector\PDO\TraceablePDO;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use FOSSBilling\Core\Doctrine\DebugMiddleware;

function tracedDatabase(): Doctrine\DBAL\Connection
{
    $configuration = new Configuration();
    $configuration->setMiddlewares([new DebugMiddleware()]);

    return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
}

test('DBAL and native PDO statements are traced once in execution order', function (): void {
    $connection = tracedDatabase();
    $pdo = $connection->getNativeConnection();
    expect($pdo)->toBeInstanceOf(TraceablePDO::class);

    $connection->executeStatement('CREATE TABLE example (id INTEGER PRIMARY KEY, name TEXT)');
    $connection->executeStatement("INSERT INTO example (name) VALUES ('first')");
    $connection->executeStatement('INSERT INTO example (name) VALUES (?)', ['second']);
    expect($connection->fetchOne('SELECT COUNT(*) FROM example'))->toBe(2)
        ->and($connection->fetchOne('SELECT name FROM example WHERE id = ?', [2]))->toBe('second');
    $statement = $pdo->prepare('SELECT name FROM example WHERE id = ?');
    $statement->execute([1]);
    expect($statement->fetchColumn())->toBe('first');
    expect($pdo->query('SELECT COUNT(*) FROM example')->fetchColumn())->toBe(2);
    $pdo->exec("UPDATE example SET name = 'updated' WHERE id = 1");

    $collector = new PDOCollector($pdo);
    expect($collector->getConnections()['default'])->toBe($pdo);
    $data = $collector->collect();
    expect($data['nb_statements'])->toBe(8)
        ->and(array_column($data['statements'], 'sql'))->toBe([
            'CREATE TABLE example (id INTEGER PRIMARY KEY, name TEXT)',
            "INSERT INTO example (name) VALUES ('first')",
            "INSERT INTO example (name) VALUES ('second')",
            'SELECT COUNT(*) FROM example',
            'SELECT name FROM example WHERE id = 2',
            'SELECT name FROM example WHERE id = 1',
            'SELECT COUNT(*) FROM example',
            "UPDATE example SET name = 'updated' WHERE id = 1",
        ]);
    foreach ($data['statements'] as $statement) {
        expect($statement['is_success'])->toBeTrue()
            ->and($statement['duration'])->toBeGreaterThanOrEqual(0);
    }
    $collector->reset();
    expect($collector->collect()['nb_statements'])->toBe(0);
});

test('tracing preserves shared transaction state and reports commits and rollbacks', function (): void {
    $connection = tracedDatabase();
    $pdo = $connection->getNativeConnection();
    $connection->executeStatement('CREATE TABLE example (id INTEGER PRIMARY KEY)');
    $pdo->resetExecutedStatements();

    $connection->beginTransaction();
    expect($pdo->inTransaction())->toBeTrue();
    $pdo->exec('INSERT INTO example (id) VALUES (1)');
    $connection->rollBack();
    expect($pdo->inTransaction())->toBeFalse()
        ->and($connection->fetchOne('SELECT COUNT(*) FROM example'))->toBe(0);
    $connection->beginTransaction();
    $pdo->exec('INSERT INTO example (id) VALUES (2)');
    $connection->commit();
    expect($connection->fetchOne('SELECT COUNT(*) FROM example'))->toBe(1);
    expect(array_map(static fn ($statement) => $statement->getSql(), $pdo->getExecutedStatements()))->toBe([
        'Begin Transaction', 'INSERT INTO example (id) VALUES (1)', 'Rollback Transaction',
        'SELECT COUNT(*) FROM example', 'Begin Transaction', 'INSERT INTO example (id) VALUES (2)',
        'Commit Transaction', 'SELECT COUNT(*) FROM example',
    ]);
});

test('failed direct and prepared DBAL queries are traced without swallowing driver errors', function (): void {
    $connection = tracedDatabase();
    $connection->executeStatement('CREATE TABLE example (id INTEGER PRIMARY KEY)');
    $connection->executeStatement('INSERT INTO example (id) VALUES (1)');
    $pdo = $connection->getNativeConnection();
    $pdo->resetExecutedStatements();

    expect(fn () => $connection->fetchOne('SELECT * FROM missing_table'))->toThrow(Doctrine\DBAL\Exception\DriverException::class)
        ->and(fn () => $connection->executeStatement('INSERT INTO example (id) VALUES (?)', [1]))->toThrow(Doctrine\DBAL\Exception\DriverException::class);
    $data = (new PDOCollector($pdo))->collect();
    expect($data['nb_statements'])->toBe(2)
        ->and($data['nb_failed_statements'])->toBe(2);
});
