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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Creates the initial database schema for PostgreSQL and SQLite installs, generated directly from
 * Doctrine entity metadata rather than a hand-maintained SQL dump. MySQL/MariaDB installs still use
 * `install/sql/structure.sql` (a proven, unchanged path for the platform every existing install
 * already runs on) - this class exists for the platforms that dump was never written for.
 *
 * This only works because the entity mapping is already portable: no `columnDefinition`, no
 * `unsigned` options, no native enum types, safe `AUTO` id generation (see the DB-portability
 * audit this followed). A schema generated this way is only as portable as that mapping stays.
 */
final class SchemaInstaller
{
    public static function createSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        if ($metadata === []) {
            return;
        }

        (new SchemaTool($entityManager))->createSchema($metadata);
    }
}
