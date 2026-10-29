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

class Patch46 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Normalize legacy values before converting to restrictive ENUM columns.
        $q = 'UPDATE `client`
                SET `gender` = NULL
                WHERE `gender` IS NOT NULL
                AND `gender` NOT IN (\'male\', \'female\', \'nonbinary\', \'other\');';
        $patcher->executeSql($q);

        // Change gender column to ENUM type
        $q = 'ALTER TABLE `client`
                MODIFY COLUMN `gender` ENUM(\'male\', \'female\', \'nonbinary\', \'other\') DEFAULT NULL;';

        $patcher->executeSql($q);
    }
}
