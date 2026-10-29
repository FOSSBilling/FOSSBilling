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

class Patch35 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Adds the new "created_at" to the session table, to ensure sessions are destroyed after they reach their maximum age.
        $q = 'ALTER TABLE session ADD created_at int(11);';
        $patcher->executeSql($q);
    }
}
