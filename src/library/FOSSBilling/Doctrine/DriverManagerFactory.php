<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use FOSSBilling\Exception\BaseException;
use FOSSBilling\System\Config;

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
     * @var string[]
     */
    public const SUPPORTED_DRIVERS = [
        'pdo_mysql',
    ];

    /**
     * List of supported charset values for database connections.
     *
     * @var string[]
     */
    public const SUPPORTED_CHARSETS = [
        'utf8',
        'utf8mb4',
        'latin1',
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
            throw new BaseException('Database configuration is invalid.');
        }

        $dbConfig['driver'] ??= self::normalizeDriver($dbConfig['type'] ?? 'pdo_mysql');
        $dbConfig['port'] = \FOSSBilling\Utils\Normalizer::normalizePort($dbConfig['port'] ?? null, 3306);

        return $dbConfig;
    }

    /**
     * Creates and returns a Doctrine DBAL Connection instance.
     *
     * @param array $driverOptions optional driver-specific options
     *
     * @throws BaseException if required database configuration keys are missing or the driver is unsupported
     */
    public static function getConnection(array $driverOptions = []): Connection
    {
        $dbConfig = self::getDatabaseConfig();

        $requiredKeys = ['driver', 'host', 'port', 'name', 'user', 'password'];
        foreach ($requiredKeys as $key) {
            if (!isset($dbConfig[$key])) {
                throw new BaseException('Database configuration missing required key: :key.', [':key' => $key]);
            }
        }

        if (!Driver::tryFrom($dbConfig['driver']) instanceof Driver) {
            throw new BaseException('Unsupported database driver :driver. Supported drivers are: :supported.', [':driver' => $dbConfig['driver'], ':supported' => implode(', ', self::SUPPORTED_DRIVERS)]);
        }

        $charset = $dbConfig['charset'] ?? 'utf8';
        if (!Charset::tryFrom($charset) instanceof Charset) {
            $charset = Charset::Utf8->value;
        }

        $connectionParams = [
            'driver' => $dbConfig['driver'],
            'host' => $dbConfig['host'],
            'port' => $dbConfig['port'],
            'dbname' => $dbConfig['name'],
            'user' => $dbConfig['user'],
            'password' => $dbConfig['password'],
            'driverOptions' => $driverOptions,
            'charset' => $charset,
        ];

        $connection = DriverManager::getConnection($connectionParams);
        self::applySessionSettings($connection, $dbConfig);

        return $connection;
    }

    /**
     * Apply per-connection MySQL session settings shared by all Doctrine connections.
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
            'mysql' => 'pdo_mysql',
            default => $driver,
        };
    }
}
