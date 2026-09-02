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

class Patch85 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Raises the client custom field cap from 10 to 20.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/3174
        for ($i = 11; $i <= 20; ++$i) {
            if (!$patcher->tableHasColumn('client', "custom_{$i}")) {
                $patcher->executeSql("ALTER TABLE `client` ADD COLUMN `custom_{$i}` text");
            }
        }
    }
}
