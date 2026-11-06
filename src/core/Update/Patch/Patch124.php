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

class Patch124 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Baseline the invoice journal for installs predating it: one entry
        // per invoice missing from the journal, typed by its current state. The portable
        // backfill covers non-MySQL drivers; both skip invoices that already
        // have journal rows, so reruns are no-ops.
        $patcher->backfillInvoiceJournal();
    }
}
