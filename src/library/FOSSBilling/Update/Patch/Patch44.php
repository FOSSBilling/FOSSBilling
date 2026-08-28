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

class Patch44 implements PatchInterface
{
    public function getVersion(): int
    {
        return 44;
    }

    public function apply(Patcher $patcher): void
    {
        // Add ipn_hash column to transaction table and index it for fast duplicate detection.
        $q = 'ALTER TABLE `transaction`
                ADD COLUMN `ipn_hash` VARCHAR(64) DEFAULT NULL,
                ADD INDEX `transaction_ipn_hash_idx` (`gateway_id`, `ipn_hash`(64));';
        $patcher->executeSql($q);
    }
}
