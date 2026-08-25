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
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Proxy\ProxyFactory;
use FOSSBilling\Cache\CacheFactory;
use FOSSBilling\Environment;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class EntityManagerFactory
{
    public static function create(?Connection $connection = null): EntityManager
    {
        $moduleEntityPaths = self::moduleEntityPaths();

        // ORMSetup uses this as the metadata, query, AND result cache. Always pass an explicit,
        // non-null pool here: if `cache` is omitted, Doctrine silently probes for APCu/Memcached/Redis
        // on localhost with no authentication (see ORMSetup::createCacheInstance()), which would let
        // whatever happens to be installed on the host override the admin's configured cache driver.
        //
        // ORMSetup::createConfig() only ever applies a $cacheNamespaceSeed when it builds its OWN
        // cache instance internally - see its private createCacheInstance(), which returns $cache
        // as-is, unused seed and all, whenever one is passed in (as we always do here, via
        // CacheFactory, to honor the admin's configured driver). So Doctrine's ClassMetadata cache
        // would otherwise never invalidate when entity attributes change, persisting stale mappings
        // (missing columns/indexes, renamed fields, ...) across deploys until something else
        // wipes the cache outright (UpdateFinalization::clearCache() covers real upgrades, but
        // not e.g. local development or a hot-deployed release with no new UpdatePatcher patch).
        // Baking the seed into the pool's own namespace instead reproduces what Doctrine's
        // internal cache construction would have done, so an entity file change is picked up on
        // its own, the same way a fresh install or migration already would be - regardless of
        // which cache backend (filesystem/Redis/Memcached) is actually configured.
        $cache = CacheFactory::create(self::metadataCacheNamespace($moduleEntityPaths));

        $config = ORMSetup::createAttributeMetadataConfig(
            paths: $moduleEntityPaths,
            isDevMode: Environment::isDevelopment(),
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
     * The CacheFactory namespace create() above stores the Doctrine metadata/query/result cache
     * under. Exposed so {@see \FOSSBilling\Config::setConfig()} and
     * {@see \Box\Mod\System\Service::clearCache()} can clear this specific pool directly -
     * CacheFactory::clearAll()'s own fixed namespace list can't reach it, since (see below) this
     * one changes identity whenever entity files do.
     *
     * @param list<string>|null $moduleEntityPaths pass the already-computed list from create() to
     *                                             avoid re-running the Finder; omit to compute it fresh
     */
    public static function metadataCacheNamespace(?array $moduleEntityPaths = null): string
    {
        return CacheFactory::NAMESPACE_DOCTRINE . '_' . hash('xxh128', self::getCacheNamespaceSeed($moduleEntityPaths ?? self::moduleEntityPaths()));
    }

    /**
     * @return list<string>
     */
    private static function moduleEntityPaths(): array
    {
        // Each module's own Entity/ folder itself, not its contents: in(PATH_MODS . '/*/Entity')
        // would search *inside* every Entity/ folder for matches, which - since those folders
        // hold only PHP files, no subdirectories - silently finds nothing. Metadata lookups for
        // one already-known entity class never needed this (they resolve via reflection on the
        // class directly), so this went unnoticed until something needed *every* entity at once
        // (schema generation, migrations-diff tooling).
        $finder = new Finder();
        $finder->directories()->in(PATH_MODS)->depth('== 1')->name('Entity');

        return array_values(array_map(
            static fn (\SplFileInfo $directory): string => $directory->getPathname(),
            iterator_to_array($finder)
        ));
    }

    /**
     * Build a cache namespace seed that changes when local entity definitions change - hashed
     * into the metadata cache's namespace in create() above, so a stale cache never survives
     * an entity attribute edit (a new/renamed column, a new index, ...) regardless of whether
     * anything else along the way happens to clear PATH_CACHE outright.
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
