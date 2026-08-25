<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use FOSSBilling\Config;
use FOSSBilling\Exception;
use FOSSBilling\Tools;

class DriverManagerFactory
{
    private static ?Connection $sharedConnection = null;

    /**
     * Returns a single process-wide shared database connection.
     *
     * The EntityManager, DBAL, and PDO services all reuse this connection so
     * that they participate in the same transaction scope. Only use a separate
     * connection (via {@see self::getConnection()}) when isolation is required.
     */
    public static function getSharedConnection(): Connection
    {
        return self::$sharedConnection ??= self::getConnection();
    }

    /**
     * List of supported database drivers for Doctrine DBAL connections.
     *
     * MariaDB is served by the same 'pdo_mysql' driver as MySQL; DBAL detects
     * the actual platform (MySQL vs MariaDB) from the server version at
     * connection time, so it doesn't need a driver name of its own.
     *
     * @var string[]
     */
    public const SUPPORTED_DRIVERS = [
        'pdo_mysql',
        'pdo_pgsql',
        'pdo_sqlite',
    ];

    /**
     * List of supported charset values for MySQL/MariaDB connections.
     *
     * @var string[]
     */
    public const SUPPORTED_CHARSETS = [
        'utf8',
        'utf8mb4',
        'latin1',
    ];

    /**
     * Default TCP port per driver, used when the config doesn't specify one.
     *
     * @var array<string, int>
     */
    private const DEFAULT_PORTS = [
        'pdo_mysql' => 3306,
        'pdo_pgsql' => 5432,
    ];

    /**
     * Returns the database configuration in the shape expected by Doctrine.
     *
     * Older FOSSBilling versions used db.type=mysql and some installs may not
     * have been rewritten by the config patcher before a database connection is
     * requested during an upgrade.
     */
    public static function getDatabaseConfig(): array
    {
        $dbConfig = Config::getProperty('db', []);
        if (!is_array($dbConfig)) {
            throw new Exception('Database configuration is invalid.');
        }

        $dbConfig['driver'] = self::normalizeDriver($dbConfig['driver'] ?? $dbConfig['type'] ?? 'pdo_mysql');

        // pdo_sqlite connects to a file path (or memory) rather than a host:port, so a port is meaningless.
        if ($dbConfig['driver'] !== 'pdo_sqlite') {
            $defaultPort = self::DEFAULT_PORTS[$dbConfig['driver']] ?? self::DEFAULT_PORTS['pdo_mysql'];
            $dbConfig['port'] = Tools::normalizePort($dbConfig['port'] ?? null, $defaultPort);
        }

        return $dbConfig;
    }

    /**
     * Creates and returns a Doctrine DBAL Connection instance.
     *
     * @param array      $driverOptions optional driver-specific options
     * @param array|null $dbConfig      connect using this config instead of the app's configured
     *                                  `db` block - for callers that run before config.php exists
     *                                  (the installer) or otherwise need to target a specific,
     *                                  not-yet-configured database. Shaped like {@see self::getDatabaseConfig()}'s
     *                                  return value, but is used as given - normalization (driver
     *                                  aliases, default ports) is the caller's responsibility.
     *
     * @throws Exception if required database configuration keys are missing or the driver is unsupported
     */
    public static function getConnection(array $driverOptions = [], ?array $dbConfig = null): Connection
    {
        $dbConfig ??= self::getDatabaseConfig();

        if (!in_array($dbConfig['driver'], self::SUPPORTED_DRIVERS, true)) {
            throw new Exception('Unsupported database driver :driver. Supported drivers are: :supported.', [':driver' => $dbConfig['driver'], ':supported' => implode(', ', self::SUPPORTED_DRIVERS)]);
        }

        $connectionParams = self::buildConnectionParams($dbConfig, $driverOptions);

        $connection = DriverManager::getConnection($connectionParams);
        self::applySessionSettings($connection, $dbConfig);

        return $connection;
    }

    /**
     * Builds the DBAL connection parameter array for the configured driver.
     *
     * Connection parameter shape differs per driver: pdo_mysql and pdo_pgsql
     * both connect over host/port with a dbname, while pdo_sqlite connects to
     * a file path (or an in-memory database) and has no concept of host,
     * port, or credentials.
     *
     * @throws Exception if a key required by the configured driver is missing
     */
    private static function buildConnectionParams(array $dbConfig, array $driverOptions): array
    {
        $driver = $dbConfig['driver'];

        if ($driver === 'pdo_sqlite') {
            $params = [
                'driver' => $driver,
                'driverOptions' => $driverOptions,
            ];

            if (!empty($dbConfig['memory'])) {
                $params['memory'] = true;

                return $params;
            }

            $path = $dbConfig['path'] ?? $dbConfig['name'] ?? null;
            if (!$path) {
                throw new Exception('Database configuration missing required key: :key.', [':key' => 'path']);
            }
            $params['path'] = $path;

            return $params;
        }

        $requiredKeys = ['host', 'port', 'name', 'user', 'password'];
        foreach ($requiredKeys as $key) {
            if (!isset($dbConfig[$key])) {
                throw new Exception('Database configuration missing required key: :key.', [':key' => $key]);
            }
        }

        $params = [
            'driver' => $driver,
            'host' => $dbConfig['host'],
            'port' => $dbConfig['port'],
            'dbname' => $dbConfig['name'],
            'user' => $dbConfig['user'],
            'password' => $dbConfig['password'],
            'driverOptions' => $driverOptions,
        ];

        if ($driver === 'pdo_mysql') {
            $charset = $dbConfig['charset'] ?? 'utf8';
            if (!in_array($charset, self::SUPPORTED_CHARSETS, true)) {
                $charset = 'utf8';
            }
            $params['charset'] = $charset;
        } elseif (isset($dbConfig['charset'])) {
            // MySQL's charset vocabulary (utf8/utf8mb4/latin1) doesn't apply to other platforms,
            // so only pass one through when the operator has explicitly configured it (e.g. 'UTF8' for PostgreSQL).
            $params['charset'] = $dbConfig['charset'];
        }

        return $params;
    }

    /**
     * Apply per-connection MySQL/MariaDB session settings.
     *
     * PostgreSQL and SQLite connections are left at server/library defaults;
     * neither has a directly equivalent session-configuration story worth
     * replicating here.
     */
    private static function applySessionSettings(Connection $connection, array $dbConfig): void
    {
        if (($dbConfig['driver'] ?? null) !== 'pdo_mysql') {
            return;
        }

        // Set server default charset for newly created tables. Connection charset is handled by DBAL via DSN.
        $connection->executeStatement('SET character_set_server = utf8');

        // Only override session timeouts when explicitly configured, otherwise preserve server defaults.
        if (isset($dbConfig['interactive_timeout'])) {
            $connection->executeStatement('SET SESSION interactive_timeout = ' . (int) $dbConfig['interactive_timeout']);
        }

        if (isset($dbConfig['wait_timeout'])) {
            $connection->executeStatement('SET SESSION wait_timeout = ' . (int) $dbConfig['wait_timeout']);
        }

        // Get the timezone offset in the PDO format
        $datetime = new \DateTime('now');
        $offset = $datetime->format('P');
        $connection->executeStatement("SET time_zone = '{$offset}'");
    }

    private static function normalizeDriver(string $driver): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => 'pdo_mysql',
            'pgsql', 'postgres', 'postgresql' => 'pdo_pgsql',
            'sqlite', 'sqlite3' => 'pdo_sqlite',
            default => $driver,
        };
    }
}
