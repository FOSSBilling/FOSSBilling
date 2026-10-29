<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch125 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Client groups went multi-membership (#4387): the single
        // `client.client_group_id` column is replaced by the `client_group_members`
        // join table. Create it, copy existing assignments across, then drop
        // the column (which also drops its index). Only groups that still
        // exist are copied: orphaned IDs (e.g. deleted groups) must not
        // migrate. INSERT IGNORE plus guards make reruns no-ops. Non-MySQL
        // drivers get the table and assignment copy from syncPortableSchema();
        // only the legacy column drop remains MySQL-only.
        if (!$patcher->tableExists('client_group_members')) {
            $patcher->executeSql('CREATE TABLE `client_group_members` (`id` bigint(20) NOT NULL AUTO_INCREMENT, `client_id` bigint(20) NOT NULL, `client_group_id` bigint(20) NOT NULL, `created_at` datetime DEFAULT NULL, `updated_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `client_group_members_client_group` (`client_id`, `client_group_id`), KEY `client_group_members_group_idx` (`client_group_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        }

        if ($patcher->tableHasColumn('client', 'client_group_id')) {
            $patcher->executeSql(
                'INSERT IGNORE INTO `client_group_members` (`client_id`, `client_group_id`) '
                . 'SELECT c.`id`, c.`client_group_id` FROM `client` c '
                . 'INNER JOIN `client_group` g ON g.`id` = c.`client_group_id`'
            );

            // Installs created fresh while the legacy column was still mapped as
            // a Doctrine ManyToOne carry a real foreign key on it (SchemaTool
            // materializes JoinColumns, with an auto-generated name per install),
            // while long-upgraded installs have none. MariaDB/MySQL refuse to drop
            // a column whose index backs a foreign key (error 1553), so drop those
            // constraints first, looking the names up instead of assuming them.
            foreach ($patcher->getColumnForeignKeys('client', 'client_group_id') as $foreignKey) {
                $patcher->executeSql(sprintf('ALTER TABLE `client` DROP FOREIGN KEY `%s`', $patcher->quoteIdentifier($foreignKey)));
            }

            $patcher->executeSql('ALTER TABLE `client` DROP COLUMN `client_group_id`');
        }

        // Bring upgraded installs in line with fresh installs, whose SchemaTool-built
        // join table carries both foreign keys with cascade deletes: drop memberships
        // orphaned by later client/group deletions (which no constraint could stop),
        // then add any missing constraint. Rows from the copy above always satisfy
        // both keys by construction, so this cannot fail on migrated data.
        $patcher->executeSql('DELETE FROM `client_group_members` WHERE `client_id` NOT IN (SELECT `id` FROM `client`)');
        $patcher->executeSql('DELETE FROM `client_group_members` WHERE `client_group_id` NOT IN (SELECT `id` FROM `client_group`)');
        $patcher->addForeignKeyIfMissing('client_group_members', 'client_group_members_client_fk', 'client_id', 'client', 'id');
        $patcher->addForeignKeyIfMissing('client_group_members', 'client_group_members_group_fk', 'client_group_id', 'client_group', 'id');
    }
}
