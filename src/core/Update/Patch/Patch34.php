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

class Patch34 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Adds the new "fingerprint" to the session table, to allow us to fingerprint devices and help prevent against attacks such as session hijacking.
        $q = 'ALTER TABLE session ADD fingerprint TEXT;';
        $patcher->executeSql($q);
    }
}
