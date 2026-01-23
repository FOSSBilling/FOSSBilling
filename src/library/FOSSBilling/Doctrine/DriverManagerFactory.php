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

class DriverManagerFactory
{
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
     * Creates and returns a Doctrine DBAL Connection instance.
     *
     * @param array $driverOptions optional driver-specific options
     *
     * @throws Exception if required database configuration keys are missing or the driver is unsupported
     */
    public static function getConnection(array $driverOptions = []): Connection
    {
        $dbConfig = Config::getProperty('db');

        $requiredKeys = ['driver', 'host', 'port', 'name', 'user', 'password'];
        foreach ($requiredKeys as $key) {
            if (!isset($dbConfig[$key])) {
                throw new Exception('Database configuration missing required key: :key.', [':key' => $key]);
            }
        }

        if (!in_array($dbConfig['driver'], self::SUPPORTED_DRIVERS, true)) {
            throw new Exception('Unsupported database driver :driver. Supported drivers are: :supported.', [':driver' => $dbConfig['driver'], ':supported' => implode(', ', self::SUPPORTED_DRIVERS)]);
        }

        $charset = $dbConfig['charset'] ?? 'utf8';
        if (!in_array($charset, self::SUPPORTED_CHARSETS, true)) {
            $charset = 'utf8';
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

        return DriverManager::getConnection($connectionParams);
    }
}
