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

class Patch40 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Added `passwordLength` field to server managers
        $q = 'ALTER TABLE service_hosting_server ADD COLUMN `password_length` TINYINT DEFAULT NULL;';
        $patcher->executeSql($q);
    }
}
