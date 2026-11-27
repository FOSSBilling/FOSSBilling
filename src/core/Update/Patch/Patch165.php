<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

/**
 * Mass mail messages are sent in the background, a slice per cron run, and
 * record how far each send has got.
 */
class Patch165 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('mod_massmailer', 'send_total')) {
            $patcher->executeSql('ALTER TABLE `mod_massmailer` ADD COLUMN `send_total` INT DEFAULT NULL');
        }
        if (!$patcher->tableHasColumn('mod_massmailer', 'send_queued')) {
            $patcher->executeSql('ALTER TABLE `mod_massmailer` ADD COLUMN `send_queued` INT NOT NULL DEFAULT 0');
        }
        if (!$patcher->tableHasColumn('mod_massmailer', 'send_failed')) {
            $patcher->executeSql('ALTER TABLE `mod_massmailer` ADD COLUMN `send_failed` INT NOT NULL DEFAULT 0');
        }
        if (!$patcher->tableHasColumn('mod_massmailer', 'send_last_client_id')) {
            $patcher->executeSql('ALTER TABLE `mod_massmailer` ADD COLUMN `send_last_client_id` BIGINT DEFAULT NULL');
        }
    }
}
