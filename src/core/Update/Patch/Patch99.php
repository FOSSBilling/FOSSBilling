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

class Patch99 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Lets admins restrict a TLD's registration period to an explicit set of years
        // (e.g. "1,2,5,10") instead of any integer at or above min_years.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/2075
        if (!$patcher->tableHasColumn('tld', 'periods')) {
            $patcher->executeSql('ALTER TABLE `tld` ADD COLUMN `periods` VARCHAR(255) DEFAULT NULL AFTER `min_years`');
        }
    }
}
