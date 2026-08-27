<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Doctrine\DriverManagerFactory;
use FOSSBilling\Exception\BaseException as Exception;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Writes the given `db` config array into the real config.php (mirroring the
 * pattern in UpdateFinalizationTest) so Config::getProperty('db', ...) picks
 * it up. Restored in afterEach below.
 */
function withDbConfig(array $dbConfig, callable $callback): mixed
{
    $filesystem = new Filesystem();
    $config = FOSSBilling\System\Config::getConfig();
    $config['db'] = $dbConfig;
    $filesystem->dumpFile(PATH_CONFIG, '<?php return ' . var_export($config, true) . ';');
    clearstatcache(true, PATH_CONFIG);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(PATH_CONFIG, true);
    }

    return $callback();
}

/**
 * Invokes DriverManagerFactory's private buildConnectionParams() without opening a real
 * connection, so mysql/pgsql parameter shape can be asserted without a reachable server.
 */
function buildConnectionParams(array $dbConfig, array $driverOptions = []): array
{
    $method = new ReflectionMethod(DriverManagerFactory::class, 'buildConnectionParams');

    return $method->invoke(null, $dbConfig, $driverOptions);
}

beforeEach(function (): void {
    $filesystem = new Filesystem();
    $this->driverManagerFactoryOriginalConfig = $filesystem->readFile(PATH_CONFIG);
});

afterEach(function (): void {
    $filesystem = new Filesystem();
    $filesystem->dumpFile(PATH_CONFIG, $this->driverManagerFactoryOriginalConfig);
    clearstatcache(true, PATH_CONFIG);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(PATH_CONFIG, true);
    }
});

test('getDatabaseConfig normalizes legacy driver aliases', function (string $alias, string $expected): void {
    withDbConfig(['driver' => $alias, 'host' => 'localhost', 'name' => 'db', 'user' => 'u', 'password' => 'p'], function () use ($expected): void {
        expect(DriverManagerFactory::getDatabaseConfig()['driver'])->toBe($expected);
    });
})->with([
    ['mysql', 'pdo_mysql'],
    ['mariadb', 'pdo_mysql'],
    ['pgsql', 'pdo_pgsql'],
    ['postgres', 'pdo_pgsql'],
    ['postgresql', 'pdo_pgsql'],
    ['sqlite', 'pdo_sqlite'],
    ['sqlite3', 'pdo_sqlite'],
    ['pdo_mysql', 'pdo_mysql'],
    ['pdo_pgsql', 'pdo_pgsql'],
    ['pdo_sqlite', 'pdo_sqlite'],
]);

test('getDatabaseConfig falls back to the legacy db.type key', function (): void {
    withDbConfig(['type' => 'mysql', 'host' => 'localhost', 'name' => 'db', 'user' => 'u', 'password' => 'p'], function (): void {
        expect(DriverManagerFactory::getDatabaseConfig()['driver'])->toBe('pdo_mysql');
    });
});

test('getDatabaseConfig applies the correct default port per driver', function (): void {
    withDbConfig(['driver' => 'pdo_mysql', 'host' => 'localhost', 'name' => 'db', 'user' => 'u', 'password' => 'p'], function (): void {
        expect(DriverManagerFactory::getDatabaseConfig()['port'])->toBe(3306);
    });
    withDbConfig(['driver' => 'pdo_pgsql', 'host' => 'localhost', 'name' => 'db', 'user' => 'u', 'password' => 'p'], function (): void {
        expect(DriverManagerFactory::getDatabaseConfig()['port'])->toBe(5432);
    });
});

test('getDatabaseConfig does not assign a port for pdo_sqlite', function (): void {
    withDbConfig(['driver' => 'pdo_sqlite', 'memory' => true], function (): void {
        expect(DriverManagerFactory::getDatabaseConfig())->not->toHaveKey('port');
    });
});

test('getConnection rejects an unsupported driver', function (): void {
    withDbConfig(['driver' => 'pdo_oracle', 'host' => 'localhost', 'name' => 'db', 'user' => 'u', 'password' => 'p'], function (): void {
        DriverManagerFactory::getConnection();
    });
})->throws(Exception::class, 'Unsupported database driver');

test('buildConnectionParams requires host/name/user/password for pdo_pgsql', function (): void {
    buildConnectionParams(['driver' => 'pdo_pgsql', 'port' => 5432, 'name' => 'db', 'user' => 'u', 'password' => 'p']);
})->throws(Exception::class);

test('buildConnectionParams builds a host-based param set for pdo_pgsql', function (): void {
    $params = buildConnectionParams([
        'driver' => 'pdo_pgsql',
        'host' => 'db.example.test',
        'port' => 5432,
        'name' => 'fossbilling',
        'user' => 'fb',
        'password' => 'secret',
    ]);

    expect($params)->toMatchArray([
        'driver' => 'pdo_pgsql',
        'host' => 'db.example.test',
        'port' => 5432,
        'dbname' => 'fossbilling',
        'user' => 'fb',
        'password' => 'secret',
    ])
        ->and($params)->not->toHaveKey('charset');
});

test('buildConnectionParams only sets charset for pdo_pgsql when explicitly configured', function (): void {
    $params = buildConnectionParams([
        'driver' => 'pdo_pgsql',
        'host' => 'db.example.test',
        'port' => 5432,
        'name' => 'fossbilling',
        'user' => 'fb',
        'password' => 'secret',
        'charset' => 'UTF8',
    ]);

    expect($params['charset'])->toBe('UTF8');
});

test('buildConnectionParams falls back to a supported charset for pdo_mysql', function (): void {
    $params = buildConnectionParams([
        'driver' => 'pdo_mysql',
        'host' => 'db.example.test',
        'port' => 3306,
        'name' => 'fossbilling',
        'user' => 'fb',
        'password' => 'secret',
        'charset' => 'not-a-real-charset',
    ]);

    expect($params['charset'])->toBe('utf8');
});

test('buildConnectionParams builds a path-based param set for pdo_sqlite', function (): void {
    $params = buildConnectionParams(['driver' => 'pdo_sqlite', 'path' => '/tmp/fossbilling-test.sqlite']);

    expect($params)->toBe([
        'driver' => 'pdo_sqlite',
        'driverOptions' => [],
        'path' => '/tmp/fossbilling-test.sqlite',
    ]);
});

test('buildConnectionParams builds an in-memory param set for pdo_sqlite', function (): void {
    $params = buildConnectionParams(['driver' => 'pdo_sqlite', 'memory' => true]);

    expect($params)->toBe([
        'driver' => 'pdo_sqlite',
        'driverOptions' => [],
        'memory' => true,
    ]);
});

test('buildConnectionParams requires a path when pdo_sqlite is not in-memory', function (): void {
    buildConnectionParams(['driver' => 'pdo_sqlite']);
})->throws(Exception::class);

test('getConnection actually connects for an in-memory pdo_sqlite database', function (): void {
    withDbConfig(['driver' => 'pdo_sqlite', 'memory' => true], function (): void {
        $connection = DriverManagerFactory::getConnection();

        expect($connection->fetchOne('SELECT 1'))->toEqual(1);
    });
});

test('getConnection actually connects for a file-based pdo_sqlite database', function (): void {
    $path = sys_get_temp_dir() . '/fossbilling-driver-manager-factory-test-' . bin2hex(random_bytes(4)) . '.sqlite';

    try {
        withDbConfig(['driver' => 'pdo_sqlite', 'path' => $path], function () use ($path): void {
            $connection = DriverManagerFactory::getConnection();
            $connection->executeStatement('CREATE TABLE t (id INTEGER PRIMARY KEY)');

            expect($connection->fetchOne('SELECT COUNT(*) FROM t'))->toEqual(0)
                ->and((new Filesystem())->exists($path))->toBeTrue();
        });
    } finally {
        (new Filesystem())->remove($path);
    }
});
