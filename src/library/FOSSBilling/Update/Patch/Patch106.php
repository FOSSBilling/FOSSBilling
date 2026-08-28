<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Update\Patch;

use FOSSBilling\Update\Patcher;

class Patch106 implements PatchInterface
{
    public function getVersion(): int
    {
        return 106;
    }

    public function apply(Patcher $patcher): void
    {
        // Symfony stores application attributes in a different session format
        // from FOSSBilling's previous handler. This release deliberately does
        // not migrate session data, so invalidate all existing sessions before
        // changing the table to Symfony's schema.
        if (!$patcher->tableExists('session')) {
            return;
        }

        $patcher->executeSql('DELETE FROM `session`');

        if (!$patcher->tableHasColumn('session', 'lifetime')) {
            $patcher->executeSql('ALTER TABLE `session` ADD COLUMN `lifetime` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `content`');
        }

        // The table is empty, so these conversions do not need a data-copy
        // step and remain safe to repeat after a partially applied patch.
        $patcher->executeSql('ALTER TABLE `session` MODIFY `content` BLOB NOT NULL');
        $patcher->executeSql('ALTER TABLE `session` MODIFY `id` VARBINARY(128) NOT NULL');
        $patcher->executeSql('ALTER TABLE `session` MODIFY `modified_at` INT UNSIGNED NOT NULL');
        $patcher->executeSql('ALTER TABLE `session` MODIFY `lifetime` INT UNSIGNED NOT NULL');

        if ($patcher->tableHasIndex('session', 'unique_id')) {
            $patcher->executeSql('ALTER TABLE `session` DROP INDEX `unique_id`');
        }
        if (!$patcher->tableHasIndex('session', 'PRIMARY')) {
            $patcher->executeSql('ALTER TABLE `session` ADD PRIMARY KEY (`id`)');
        }
        if (!$patcher->tableHasIndex('session', 'session_lifetime_idx')) {
            $patcher->executeSql('ALTER TABLE `session` ADD INDEX `session_lifetime_idx` (`lifetime`)');
        }
    }
}
