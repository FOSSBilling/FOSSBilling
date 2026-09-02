<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;

/**
 * hasDatabaseTables() reads the real db config via Config::getProperty() and (for
 * mysql/pgsql) opens a raw PDO connection before Doctrine/the DI container is available,
 * mirroring how LoadCoreUpdateLockTest exercises load.php's other bootstrap guards. These
 * tests swap the real config.php's `db` block, always restoring it afterward.
 */
function withHasDatabaseTablesConfig(array $dbConfig, Closure $callback): mixed
{
    $filesystem = new Filesystem();
    $original = $filesystem->readFile(PATH_CONFIG);
    $config = FOSSBilling\Core\System\Config::getConfig();
    $config['db'] = $dbConfig;
    $filesystem->dumpFile(PATH_CONFIG, '<?php return ' . var_export($config, true) . ';');
    clearstatcache(true, PATH_CONFIG);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(PATH_CONFIG, true);
    }

    try {
        return $callback();
    } finally {
        $filesystem->dumpFile(PATH_CONFIG, $original);
        clearstatcache(true, PATH_CONFIG);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(PATH_CONFIG, true);
        }
    }
}

test('hasDatabaseTables assumes true for an unsupported/missing driver', function (): void {
    withHasDatabaseTablesConfig(['driver' => 'pdo_oracle', 'host' => 'localhost', 'name' => 'db'], function (): void {
        expect(hasDatabaseTables())->toBeTrue();
    });
});

test('hasDatabaseTables is false for a sqlite path that does not exist on disk yet', function (): void {
    $path = sys_get_temp_dir() . '/fossbilling-has-tables-test-missing-' . bin2hex(random_bytes(4)) . '.sqlite';

    withHasDatabaseTablesConfig(['driver' => 'pdo_sqlite', 'path' => $path], function (): void {
        expect(hasDatabaseTables())->toBeFalse();
    });
});

test('hasDatabaseTables is false for an in-memory sqlite configuration', function (): void {
    // An in-memory database is always empty on every process start, so it can never be
    // "already installed" - without an explicit check, buildDatabaseProbeDsn() has no path to
    // probe and returns null (no path, and no "memory" case of its own), which is otherwise
    // treated as "assume already installed".
    withHasDatabaseTablesConfig(['driver' => 'pdo_sqlite', 'memory' => true], function (): void {
        expect(hasDatabaseTables())->toBeFalse();
    });
});

test('hasDatabaseTables is false for an existing sqlite file with no tables', function (): void {
    $path = sys_get_temp_dir() . '/fossbilling-has-tables-test-empty-' . bin2hex(random_bytes(4)) . '.sqlite';
    (new PDO('sqlite:' . $path))->exec('SELECT 1'); // creates the (empty) database file on disk

    try {
        withHasDatabaseTablesConfig(['driver' => 'pdo_sqlite', 'path' => $path], function (): void {
            expect(hasDatabaseTables())->toBeFalse();
        });
    } finally {
        (new Filesystem())->remove($path);
    }
});

test('hasDatabaseTables is true for an existing sqlite file with at least one table', function (): void {
    $path = sys_get_temp_dir() . '/fossbilling-has-tables-test-populated-' . bin2hex(random_bytes(4)) . '.sqlite';
    (new PDO('sqlite:' . $path))->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');

    try {
        withHasDatabaseTablesConfig(['driver' => 'pdo_sqlite', 'path' => $path], function (): void {
            expect(hasDatabaseTables())->toBeTrue();
        });
    } finally {
        (new Filesystem())->remove($path);
    }
});

test('hasDatabaseTables assumes true when host/name are missing for a host-based driver', function (): void {
    withHasDatabaseTablesConfig(['driver' => 'pdo_pgsql'], function (): void {
        expect(hasDatabaseTables())->toBeTrue();
    });
});

test('hasDatabaseTables assumes true when the configured host-based database is unreachable', function (): void {
    // Loopback + an unassigned port gives an immediate connection-refused rather than a
    // network timeout, so this stays fast instead of hanging on an unroutable address.
    withHasDatabaseTablesConfig([
        'driver' => 'pdo_pgsql',
        'host' => '127.0.0.1',
        'name' => 'fossbilling',
        'user' => 'fb',
        'password' => 'secret',
        'port' => 1,
    ], function (): void {
        expect(hasDatabaseTables())->toBeTrue();
    });
});
