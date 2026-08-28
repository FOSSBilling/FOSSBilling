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

class Patch76 implements PatchInterface
{
    public function getVersion(): int
    {
        return 76;
    }

    public function apply(Patcher $patcher): void
    {
        // The `activity_client_email` table was missing an `updated_at` column,
        // but the Doctrine entity for it now expects one. Add the column for
        // installations that were created before the entity was migrated.
        if (!$patcher->tableHasColumn('activity_client_email', 'updated_at')) {
            $patcher->executeSql('ALTER TABLE `activity_client_email` ADD COLUMN `updated_at` datetime DEFAULT NULL AFTER `created_at`');
        }
    }
}
