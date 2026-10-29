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

class Patch132 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Product category priority (#4499). Mirrors product.priority so categories
        // can be ordered explicitly instead of by insertion id. MySQL/MariaDB DDL
        // only - the data backfill lives in Patcher::backfillProductCategoryPriorities(),
        // which runs portably on every driver. Guarded so reruns are no-ops;
        // non-MySQL drivers get the column from the portable schema sync.
        if (!$patcher->tableHasColumn('product_category', 'priority')) {
            $patcher->executeSql('ALTER TABLE `product_category` ADD COLUMN `priority` BIGINT DEFAULT NULL');
        }
    }
}
