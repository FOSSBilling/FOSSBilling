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

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Proxy\ProxyFactory;
use FOSSBilling\Environment;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class EntityManagerFactory
{
    public static function create(): EntityManager
    {
        $finder = new Finder();
        $finder->directories()->in(PATH_MODS . '/*/Entity')->depth('== 0');
        $moduleEntityPaths = iterator_to_array($finder);

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: $moduleEntityPaths,
            isDevMode: Environment::isDevelopment()
        );

        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER)); // Consistency with already existing RedBean tables

        // Enable native lazy loading if PHP version supports it (8.4+).
        if (PHP_VERSION_ID > 80400) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(Path::join(PATH_CACHE, 'doctrine', 'proxies'));
            $config->setProxyNamespace('FOSSBilling\Doctrine\Proxies');

            if (Environment::isDevelopment()) {
                $config->setAutoGenerateProxyClasses(true);
            } else {
                $config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS);
            }
        }

        $connection = DriverManagerFactory::getConnection();

        return new EntityManager($connection, $config);
    }
}
