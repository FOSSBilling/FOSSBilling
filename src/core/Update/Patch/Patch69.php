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

class Patch69 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('email_template', 'last_error')) {
            $patcher->executeSql('ALTER TABLE `email_template` ADD COLUMN `last_error` TEXT DEFAULT NULL');
        }

        if (!$patcher->tableHasColumn('email_template', 'error_checked_at')) {
            $patcher->executeSql('ALTER TABLE `email_template` ADD COLUMN `error_checked_at` DATETIME DEFAULT NULL');
        }
    }
}
