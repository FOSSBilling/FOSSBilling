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

class Patch70 implements PatchInterface
{
    public function getVersion(): int
    {
        return 70;
    }

    public function apply(Patcher $patcher): void
    {
        $patcher->executeSql(
            "UPDATE client_order co
             LEFT JOIN invoice i ON i.id = co.unpaid_invoice_id AND i.status = 'unpaid'
             SET co.unpaid_invoice_id = NULL
             WHERE co.unpaid_invoice_id IS NOT NULL
               AND i.id IS NULL"
        );
    }
}
