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

class Patch71 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Ensure the invoice table has the gateway_id, text_1, and text_2
        // columns. These have been part of structure.sql for a long time, but
        // databases upgraded from very old installations (e.g. BoxBilling era)
        // may be missing them, which produces PHP "Undefined array key"
        // warnings in Invoice\Service::toApiArray().
        if (!$patcher->tableHasColumn('invoice', 'gateway_id')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD COLUMN `gateway_id` int(11) DEFAULT NULL');
        }

        if (!$patcher->tableHasColumn('invoice', 'text_1')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD COLUMN `text_1` text');
        }

        if (!$patcher->tableHasColumn('invoice', 'text_2')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD COLUMN `text_2` text');
        }
    }
}
