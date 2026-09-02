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

class Patch89 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Allows invoice notifications to be sent to an address separate from the client's login email.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/3833
        if (!$patcher->tableHasColumn('client', 'billing_email')) {
            $patcher->executeSql('ALTER TABLE `client` ADD COLUMN `billing_email` varchar(255) DEFAULT NULL AFTER `email`');
        }
    }
}
