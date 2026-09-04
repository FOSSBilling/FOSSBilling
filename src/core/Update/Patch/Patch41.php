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

class Patch41 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Remove the  `manifest` column from the extensions table since it's no longer used
        $q = 'ALTER TABLE extension DROP COLUMN manifest;';
        $patcher->executeSql($q);
    }
}
