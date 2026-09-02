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

use FOSSBilling\System\Config;
use FOSSBilling\Update\Patcher;

class Patch81 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Per-user timezone for clients and staff. NULL falls back to the system `i18n.timezone` config.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/1028
        if (!$patcher->tableHasColumn('client', 'timezone')) {
            $patcher->executeSql('ALTER TABLE `client` ADD COLUMN `timezone` VARCHAR(64) DEFAULT NULL AFTER `lang`');
        }
        if (!$patcher->tableHasColumn('admin', 'timezone')) {
            $patcher->executeSql('ALTER TABLE `admin` ADD COLUMN `timezone` VARCHAR(64) DEFAULT NULL AFTER `api_token`');
        }
    }
}
