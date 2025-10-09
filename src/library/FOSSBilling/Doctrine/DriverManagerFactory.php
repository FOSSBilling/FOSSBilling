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

class DriverManagerFactory
{
    public static function getConnection(array $driverOptions = []): Connection
    {
        $dbConfig = Config::getProperty('db');
        $connectionParams = [
            'driver'   => 'pdo_mysql',
            'host'     => $dbConfig['host'],
            'port'     => $dbConfig['port'],
            'dbname'   => $dbConfig['name'],
            'user'     => $dbConfig['user'],
            'password' => $dbConfig['password'],
            'driverOptions' => $driverOptions,
            'charset'  => 'utf8',
        ];

        return DriverManager::getConnection($connectionParams);
    }
}
