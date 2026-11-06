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

class Patch131 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Custom pages gained an updated_at column (TimestampTrait) without which
        // sitemap <lastmod> falls back to omitting the tag on installs the portable
        // sync never healed - same pattern as Patch119/Patch120 (see #4392, #4553).
        // The table only exists when the extension was activated (install() creates
        // it), so skip absent tables; guards make reruns no-ops. Non-MySQL drivers
        // get the column from the portable schema sync.
        if (!$patcher->tableExists('custom_pages')) {
            return;
        }

        if (!$patcher->tableHasColumn('custom_pages', 'updated_at')) {
            $patcher->executeSql('ALTER TABLE `custom_pages` ADD COLUMN `updated_at` datetime DEFAULT NULL');
        }
    }
}
