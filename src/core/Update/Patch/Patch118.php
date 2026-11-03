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

class Patch118 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // The one-credit-note-per-invoice unique constraint shipped briefly and
        // was replaced by partial refunds, which need many credit notes per
        // original. Drop it where the schema sync created it; installs that
        // never synced it and fresh installs are unaffected.
        if ($patcher->tableHasIndex('invoice', 'invoice_credit_note_for_unique')) {
            $patcher->executeSql('ALTER TABLE `invoice` DROP INDEX `invoice_credit_note_for_unique`');
        }
    }
}
