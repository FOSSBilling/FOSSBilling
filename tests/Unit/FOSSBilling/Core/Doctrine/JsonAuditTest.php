<?php

declare(strict_types=1);

use Box\Mod\System\Commands\JsonAudit as JsonAuditCommand;
use Doctrine\DBAL\DriverManager;
use FOSSBilling\Core\Doctrine\JsonAudit;
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
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $command->setDi(new Pimple\Container(['em' => $em]));
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

test('JSON audit quarantine listing is empty without a quarantine store', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY, config TEXT)');
    $connection->insert('product', ['id' => 1, 'config' => '{"label":"🚀"}']);

    $command = new JsonAuditCommand();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $command->setDi(new Pimple\Container(['em' => $em, 'crypt' => new FOSSBilling\Core\Security\Crypt()]));
    $tester = new CommandTester($command);
    expect($tester->execute(['--quarantined' => true]))->toBe(0)
        ->and($tester->getDisplay())->not->toContain('quarantined:');
});

function quarantineCommandContainer(Doctrine\DBAL\Connection $connection, ?FOSSBilling\Core\Security\Crypt $crypt = null): Pimple\Container
{
    return new Pimple\Container([
        'em' => FOSSBilling\Core\Doctrine\EntityManagerFactory::create($connection),
        'crypt' => $crypt ?? new FOSSBilling\Core\Security\Crypt(),
    ]);
}

function seedQuarantine(Doctrine\DBAL\Connection $connection, string $table = 'product', string $column = 'config', int $id = 1, string $original = '{broken-secret'): void
{
    $em = FOSSBilling\Core\Doctrine\EntityManagerFactory::create($connection);
    FOSSBilling\Core\Doctrine\JsonQuarantineStore::sync($em);
    (new FOSSBilling\Core\Doctrine\JsonQuarantineStore($connection, new FOSSBilling\Core\Security\Crypt()))->save(
        $table, $column, $id, false, (string) $id, 'invalid_json', $original
    );
    (new FOSSBilling\Core\Doctrine\JsonMigrationJournal($em, new FOSSBilling\Core\Security\Crypt()))->complete();
}

test('quarantine show prints the original verbatim', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    seedQuarantine($connection, original: '<info>not-a-tag</info>{"a":1}');

    $command = new Box\Mod\System\Commands\JsonQuarantineShow();
    $command->setDi(quarantineCommandContainer($connection));
    $tester = new CommandTester($command);
    expect($tester->execute(['--table' => 'product', '--column' => 'config', '--id' => '1']))->toBe(0)
        ->and($tester->getDisplay())->toContain('product.config id=1 quarantined:invalid_json', '<info>not-a-tag</info>{"a":1}');
    expect($tester->execute(['--table' => 'product', '--column' => 'config']))->toBe(2);
    expect($tester->execute(['--table' => 'product', '--column' => 'config', '--id' => '2']))->toBe(2);
    expect($tester->execute(['--table' => 'product', '--column' => 'addons', '--id' => '1']))->toBe(2);

    // Originals that fail decryption surface instead of printing a clean bill of health.
    $badCrypt = Mockery::mock(FOSSBilling\Core\Security\Crypt::class);
    $badCrypt->shouldReceive('decrypt')->andReturn(false);
    $command = new Box\Mod\System\Commands\JsonQuarantineShow();
    $command->setDi(quarantineCommandContainer($connection, $badCrypt));
    $tester = new CommandTester($command);
    expect(fn () => $tester->execute(['--table' => 'product', '--column' => 'config', '--id' => '1']))->toThrow(FOSSBilling\Core\Doctrine\JsonMigrationException::class);
});

test('quarantine restore validates before writing and drops the record', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE product (id BIGINT PRIMARY KEY, config TEXT)');
    $connection->insert('product', ['id' => 1, 'config' => null]);
    seedQuarantine($connection);
    $file = sys_get_temp_dir() . '/fb-quarantine-restore-' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($file, '{"label":"fixed"}');

    try {
        $command = new Box\Mod\System\Commands\JsonQuarantineRestore();
        $command->setDi(quarantineCommandContainer($connection));
        $tester = new CommandTester($command);
        // Invalid documents are refused without touching anything.
        file_put_contents($file, '{broken');
        expect($tester->execute(['--table' => 'product', '--column' => 'config', '--id' => '1', '--from-file' => $file]))->toBe(2)
            ->and($connection->fetchOne('SELECT config FROM product WHERE id = 1'))->toBeNull()
            ->and((new FOSSBilling\Core\Doctrine\JsonQuarantineStore($connection, new FOSSBilling\Core\Security\Crypt()))->list())->toHaveCount(1);

        // Missing files and unknown records are usage errors.
        expect($tester->execute(['--table' => 'product', '--column' => 'config', '--id' => '1', '--from-file' => $file . '.missing']))->toBe(2);
        expect($tester->execute(['--table' => 'product', '--column' => 'config', '--id' => '9', '--from-file' => $file]))->toBe(2);

        // A valid document is written back and the record dropped.
        file_put_contents($file, '{"label":"fixed"}');
        expect($tester->execute(['--table' => 'product', '--column' => 'config', '--id' => '1', '--from-file' => $file]))->toBe(0)
            ->and($connection->fetchOne('SELECT config FROM product WHERE id = 1'))->toBe('{"label":"fixed"}')
            ->and((new FOSSBilling\Core\Doctrine\JsonQuarantineStore($connection, new FOSSBilling\Core\Security\Crypt()))->list())->toBe([]);
    } finally {
        unlink($file);
    }
});

test('quarantine restore waits for migration completion', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE product (id BIGINT PRIMARY KEY, config TEXT)');
    $connection->insert('product', ['id' => 1, 'config' => null]);
    $em = FOSSBilling\Core\Doctrine\EntityManagerFactory::create($connection);
    FOSSBilling\Core\Doctrine\JsonQuarantineStore::sync($em);
    (new FOSSBilling\Core\Doctrine\JsonQuarantineStore($connection, new FOSSBilling\Core\Security\Crypt()))->save(
        'product', 'config', 1, false, '1', 'invalid_json', '{broken-secret'
    );
    $file = sys_get_temp_dir() . '/fb-quarantine-restore-' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($file, '{"label":"fixed"}');

    try {
        $command = new Box\Mod\System\Commands\JsonQuarantineRestore();
        $command->setDi(quarantineCommandContainer($connection));
        $tester = new CommandTester($command);
        expect($tester->execute(['--table' => 'product', '--column' => 'config', '--id' => '1', '--from-file' => $file]))->toBe(2)
            ->and($connection->fetchOne('SELECT config FROM product WHERE id = 1'))->toBeNull()
            ->and((new FOSSBilling\Core\Doctrine\JsonQuarantineStore($connection, new FOSSBilling\Core\Security\Crypt()))->list())->toHaveCount(1);
    } finally {
        unlink($file);
    }
});

test('quarantine restore refuses to overwrite a value set since', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE product (id BIGINT PRIMARY KEY, config TEXT)');
    $connection->insert('product', ['id' => 1, 'config' => '{"label":"someone-fixed-it"}']);
    seedQuarantine($connection);
    $file = sys_get_temp_dir() . '/fb-quarantine-restore-' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($file, '{"label":"fixed"}');

    try {
        $command = new Box\Mod\System\Commands\JsonQuarantineRestore();
        $command->setDi(quarantineCommandContainer($connection));
        $tester = new CommandTester($command);
        expect($tester->execute(['--table' => 'product', '--column' => 'config', '--id' => '1', '--from-file' => $file]))->toBe(2)
            ->and($connection->fetchOne('SELECT config FROM product WHERE id = 1'))->toBe('{"label":"someone-fixed-it"}')
            ->and((new FOSSBilling\Core\Doctrine\JsonQuarantineStore($connection, new FOSSBilling\Core\Security\Crypt()))->list())->toHaveCount(1);
    } finally {
        unlink($file);
    }
});

test('quarantine commands address the exact column when one row has several', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE product (id BIGINT PRIMARY KEY, config TEXT, plugin_config TEXT)');
    $connection->insert('product', ['id' => 1, 'config' => null, 'plugin_config' => null]);
    seedQuarantine($connection);
    seedQuarantine($connection, 'product', 'plugin_config', 1);
    $file = sys_get_temp_dir() . '/fb-quarantine-restore-' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($file, '{"label":"fixed"}');

    try {
        // Restoring one column leaves the other column's record untouched.
        $command = new Box\Mod\System\Commands\JsonQuarantineRestore();
        $command->setDi(quarantineCommandContainer($connection));
        $tester = new CommandTester($command);
        expect($tester->execute(['--table' => 'product', '--column' => 'config', '--id' => '1', '--from-file' => $file]))->toBe(0)
            ->and($connection->fetchOne('SELECT config FROM product WHERE id = 1'))->toBe('{"label":"fixed"}')
            ->and($connection->fetchOne('SELECT plugin_config FROM product WHERE id = 1'))->toBeNull();
        $remaining = (new FOSSBilling\Core\Doctrine\JsonQuarantineStore($connection, new FOSSBilling\Core\Security\Crypt()))->list();
        expect($remaining)->toHaveCount(1)
            ->and($remaining[0]['column'])->toBe('plugin_config');

        // Forgetting one column leaves the other column's record untouched.
        $command = new Box\Mod\System\Commands\JsonQuarantineForget();
        $command->setDi(quarantineCommandContainer($connection));
        $tester = new CommandTester($command);
        expect($tester->execute(['--table' => 'product', '--column' => 'config', '--id' => '1']))->toBe(2);
        expect($tester->execute(['--table' => 'product', '--column' => 'plugin_config', '--id' => '1']))->toBe(0)
            ->and((new FOSSBilling\Core\Doctrine\JsonQuarantineStore($connection, new FOSSBilling\Core\Security\Crypt()))->list())->toBe([]);
    } finally {
        unlink($file);
    }
});

test('quarantine forget drops the record without decrypting it and keeps the NULL', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE product (id BIGINT PRIMARY KEY, config TEXT)');
    $connection->insert('product', ['id' => 1, 'config' => null]);
    seedQuarantine($connection);

    $command = new Box\Mod\System\Commands\JsonQuarantineForget();
    $command->setDi(quarantineCommandContainer($connection));
    $tester = new CommandTester($command);
    expect($tester->execute(['--table' => 'product', '--column' => 'config', '--id' => '9']))->toBe(2);

    // A payload that fails decryption must not block dismissal once the NULL is accepted.
    $badCrypt = Mockery::mock(FOSSBilling\Core\Security\Crypt::class);
    $badCrypt->shouldReceive('decrypt')->andReturn(false);
    $command = new Box\Mod\System\Commands\JsonQuarantineForget();
    $command->setDi(quarantineCommandContainer($connection, $badCrypt));
    $tester = new CommandTester($command);
    expect($tester->execute(['--table' => 'product', '--column' => 'config', '--id' => '1']))->toBe(0)
        ->and($connection->fetchOne('SELECT config FROM product WHERE id = 1'))->toBeNull()
        ->and((new FOSSBilling\Core\Doctrine\JsonQuarantineStore($connection, new FOSSBilling\Core\Security\Crypt()))->list())->toBe([]);
});
