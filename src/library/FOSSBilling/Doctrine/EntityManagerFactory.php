<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Doctrine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\DBAL\DriverManager;
use FOSSBilling\Config;
use FOSSBilling\Environment;

class EntityManagerFactory
{
    public static function create(): EntityManager
    {
        $dbc = Config::getProperty('db');
        $moduleEntityPaths = glob(PATH_MODS . '/*/Entity', GLOB_ONLYDIR);
        
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: $moduleEntityPaths,
            isDevMode: Environment::isDevelopment()
        );

        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER)); // Consistency with already existing RedBean tables

        $config->setProxyDir(PATH_CACHE . '/doctrine/proxies');
        $config->setProxyNamespace('FOSSBilling\Doctrine\Proxies');
        $config->setAutoGenerateProxyClasses(true);

        $connectionParams = [
            'driver'   => 'pdo_mysql',
            'host'     => $dbc['host'],
            'port'     => $dbc['port'],
            'dbname'   => $dbc['name'],
            'user'     => $dbc['user'],
            'password' => $dbc['password'],
            'charset'  => 'utf8',
        ];

        $connection = DriverManager::getConnection($connectionParams, $config);

        return new EntityManager($connection, $config);
    }
}
