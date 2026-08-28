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

class Patch93 implements PatchInterface
{
    public function getVersion(): int
    {
        return 93;
    }

    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('currency', 'format_pattern')) {
            $patcher->executeSql('ALTER TABLE `currency` ADD COLUMN `format_pattern` varchar(100) DEFAULT NULL AFTER `conversion_rate`');
        }

        if (!$patcher->tableHasColumn('currency', 'fraction_digits')) {
            $patcher->executeSql('ALTER TABLE `currency` ADD COLUMN `fraction_digits` smallint DEFAULT NULL AFTER `format_pattern`');
        }
    }
}
