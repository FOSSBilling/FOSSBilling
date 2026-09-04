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

class Patch112 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Adds an admin-configurable per-TLD flag to require the transfer code (EPP/auth
        // code) during domain transfer checkout, instead of silently accepting a blank
        // value that only fails later at the registrar. See issue #2335.
        if (!$patcher->tableHasColumn('tld', 'require_transfer_code')) {
            $patcher->executeSql('ALTER TABLE `tld` ADD COLUMN `require_transfer_code` tinyint(1) DEFAULT NULL AFTER `allow_transfer`');
        }
    }
}
