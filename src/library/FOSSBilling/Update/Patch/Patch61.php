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

class Patch61 implements PatchInterface
{
    public function getVersion(): int
    {
        return 61;
    }

    public function apply(Patcher $patcher): void
    {
        $columns = [];

        if ($patcher->tableHasColumn('currency', 'format')) {
            $columns[] = 'DROP COLUMN format';
        }

        if ($patcher->tableHasColumn('currency', 'price_format')) {
            $columns[] = 'DROP COLUMN price_format';
        }

        if ($columns !== []) {
            $patcher->executeSql('ALTER TABLE currency ' . implode(', ', $columns));
        }
    }
}
