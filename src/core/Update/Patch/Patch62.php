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

class Patch62 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $patcher->executeSql("UPDATE invoice_item SET period = NULL WHERE period IN ('0', '')");
        $patcher->executeSql("UPDATE client_order SET period = NULL WHERE period IN ('0', '')");
        $patcher->executeSql("UPDATE subscription SET period = NULL WHERE period IN ('0', '')");
        $patcher->executeSql("UPDATE transaction SET s_period = NULL WHERE s_period IN ('0', '')");
    }
}
