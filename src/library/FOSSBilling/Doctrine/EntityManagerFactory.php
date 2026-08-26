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
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Proxy\ProxyFactory;
use FOSSBilling\Cache\CacheFactory;
use FOSSBilling\System\Environment;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class EntityManagerFactory
{
    public static function create(?Connection $connection = null): EntityManager
    {
        $finder = new Finder();
        $finder->directories()->in(PATH_MODS . '/*/Entity')->depth('== 0');
        $moduleEntityPaths = array_map(
            static fn (\SplFileInfo $directory): string => $directory->getPathname(),
            iterator_to_array($finder)
        );
        $moduleEntityPaths = array_values($moduleEntityPaths);

        // ORMSetup uses this as the metadata, query, AND result cache. Always pass an explicit,
        // non-null pool here: if `cache` is omitted, Doctrine silently probes for APCu/Memcached/Redis
        // on localhost with no authentication (see ORMSetup::createCacheInstance()), which would let
        // whatever happens to be installed on the host override the admin's configured cache driver.
        $cache = CacheFactory::create(CacheFactory::NAMESPACE_DOCTRINE);

        $config = ORMSetup::createAttributeMetadataConfig(
            paths: $moduleEntityPaths,
            isDevMode: Environment::isDevelopment(),
            cacheNamespaceSeed: self::getCacheNamespaceSeed($moduleEntityPaths),
            cache: $cache,
        );

        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER)); // Consistency with already existing RedBean tables

        // Enable native lazy loading if PHP version supports it (8.4+).
        if (PHP_VERSION_ID >= 80400) {
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

        $connection ??= DriverManagerFactory::getSharedConnection();

        return new EntityManager($connection, $config);
    }

    /**
     * Build a cache namespace seed that changes when local entity definitions change.
     * This prevents stale production metadata caches from surviving reinstalls/upgrades.
     *
     * @param list<string> $entityDirectories
     */
    private static function getCacheNamespaceSeed(array $entityDirectories): string
    {
        if ($entityDirectories === []) {
            return PATH_ROOT;
        }

        $finder = new Finder();
        $finder->files()->in($entityDirectories)->name('*.php')->sortByName();

        $seed = [PATH_ROOT];
        foreach ($finder as $file) {
            $seed[] = sprintf('%s:%d:%d', $file->getPathname(), $file->getMTime(), $file->getSize());
        }

        return implode('|', $seed);
    }
}
