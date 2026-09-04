<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Creates the initial database schema for a fresh install, on any supported platform (MySQL/
 * MariaDB, PostgreSQL, SQLite), generated directly from Doctrine entity metadata rather than a
 * hand-maintained SQL dump. `install/sql/structure.sql` is no longer used to create a fresh
 * install's schema - it remains only as the frozen definition existing, pre-cutover MySQL
 * installs are upgraded from via {@see \FOSSBilling\Core\UpdatePatcher}'s legacy patches.
 *
 * This only works because the entity mapping is already portable: no `columnDefinition`, no
 * `unsigned` options, no native enum types, safe `AUTO` id generation (see the DB-portability
 * audit this followed). A schema generated this way is only as portable as that mapping stays.
 *
 * Deliberately does not materialize every entity's table. Only core modules ({@see
 * \FOSSBilling\Core\Module::CORE_MODULES}) and the fixed set of extensions `content.sql` pre-seeds as
 * installed ({@see ModuleEntityScope}) get their tables here - everything else (custom_pages,
 * mod_massmailer, service_apikey, and any future extension) gets its table only when actually
 * activated, via that module's own `install()` hook calling {@see SchemaSynchronizer::syncEntities()}
 * - the same mechanism a runtime activation already triggers through
 * `Box\Mod\Extension\Service::activateExistingExtension()`. Eagerly creating every entity's table
 * regardless of activation state used to be relied on as an accidental safety net for those
 * modules' own (formerly non-portable) install() hooks; now that those hooks are portable in
 * their own right, a fresh install's schema matches what "installed" actually means from the
 * very first request, on every platform.
 */
final class SchemaInstaller
{
    public static function createSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = array_values(array_filter(
            $entityManager->getMetadataFactory()->getAllMetadata(),
            static function ($classMetadata): bool {
                $module = ModuleEntityScope::moduleForEntityClass($classMetadata->getName());

                return $module === null || ModuleEntityScope::isEagerAtInstall($module);
            },
        ));
        if ($metadata === []) {
            return;
        }

        (new SchemaTool($entityManager))->createSchema($metadata);
    }
}
