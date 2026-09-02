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

use Box\Mod\Extension\Entity\Extension;
use Doctrine\DBAL\Connection;
use FOSSBilling\Module;

/**
 * Decides which entities' tables {@see SchemaInstaller} and the ambient
 * {@see SchemaSynchronizer::syncEntities()} call in {@see \FOSSBilling\UpdatePatcher::
 * applyCorePatches()} are allowed to eagerly create/maintain, versus which are left entirely to
 * their own module's `install()` hook (also via {@see SchemaSynchronizer::syncEntities()}) - the
 * same distinction {@see \Box\Mod\Extension\Service::isCoreModule()}/`isExtensionActive()` already
 * draw for permissions and activation checks, applied here to schema materialization instead.
 *
 * Every entity lives under `Box\Mod\{Module}\Entity\...` (verified: no exception exists in this
 * codebase), so the owning module is derivable from the class name alone - no separate manifest
 * declaration needed.
 */
final class ModuleEntityScope
{
    /**
     * Extensions installed by default on every fresh install - kept in sync with
     * `install/sql/content.sql`'s seeded `extension` rows deliberately, not derived from that
     * file at runtime, because this has to be known before any database (or that file's data)
     * exists yet, at the exact moment {@see SchemaInstaller::createSchema()} runs.
     */
    private const array DEFAULT_ACTIVE_EXTENSIONS = ['news', 'branding', 'redirect'];

    /**
     * Extracts the owning module name (lowercased, matching {@see Module::CORE_MODULES}'
     * casing) from an entity's fully-qualified class name, or null if it doesn't follow the
     * `Box\Mod\{Module}\Entity\...` convention every entity in this codebase does today.
     *
     * @param class-string $entityClass
     */
    public static function moduleForEntityClass(string $entityClass): ?string
    {
        if (preg_match('/^Box\\\\Mod\\\\([^\\\\]+)\\\\Entity\\\\/', $entityClass, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }

    /**
     * Whether entities owned by the given module should be eagerly materialized at fresh-install
     * time, before the `extension` table has any rows in it (or, for a from-scratch install,
     * before it even exists) - true for core modules and the fixed set of extensions
     * content.sql pre-seeds as installed.
     */
    public static function isEagerAtInstall(string $module): bool
    {
        return in_array($module, Module::CORE_MODULES, true)
            || in_array($module, self::DEFAULT_ACTIVE_EXTENSIONS, true);
    }

    /**
     * Fetches every extension module name currently marked installed, for use with
     * {@see self::isEagerNow()} - one query total, meant to be called once per gating pass (e.g.
     * once per {@see \FOSSBilling\UpdatePatcher::applyCorePatches()} run) and reused for every
     * entity being checked, rather than one query per entity. Queried directly against the
     * connection rather than through `Box\Mod\Extension\Service` to avoid that service's DI/
     * permission-check machinery for what is otherwise a plain column read - this mirrors
     * `ExtensionRepository::existsActiveByTypeAndName()`'s own filter exactly.
     *
     * @return list<string>
     */
    public static function installedExtensionModules(Connection $connection): array
    {
        if (!$connection->createSchemaManager()->tablesExist(['extension'])) {
            return [];
        }

        return $connection->fetchFirstColumn(
            'SELECT name FROM extension WHERE type = :type AND status = :status',
            ['type' => 'mod', 'status' => Extension::STATUS_INSTALLED],
        );
    }

    /**
     * Whether entities owned by the given module should be eagerly maintained by the *ambient*
     * sync ({@see \FOSSBilling\UpdatePatcher::applyCorePatches()}, run while a database update is
     * pending finalization) right now - true for core modules, and for any module present in
     * $installedExtensionModules (see {@see self::installedExtensionModules()}).
     *
     * @param list<string> $installedExtensionModules
     */
    public static function isEagerNow(string $module, array $installedExtensionModules): bool
    {
        return in_array($module, Module::CORE_MODULES, true)
            || in_array($module, $installedExtensionModules, true);
    }
}
