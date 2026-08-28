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

class Patch65 implements PatchInterface
{
    public function getVersion(): int
    {
        return 65;
    }

    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('service_downloadable', 'stored_filename')) {
            $patcher->executeSql('ALTER TABLE `service_downloadable` ADD COLUMN `stored_filename` VARCHAR(100) DEFAULT NULL AFTER `filename`;');
        }

        $patcher->downloadableStorageMigrationMap = [];
        $patcher->migrateDownloadableProductStorageKeys();
        $patcher->migrateDownloadableServiceStorageKeys();
        $patcher->migrateDownloadableOrderStorageKeys();
    }
}
