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

class Patch117 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Installs predating the news post description field miss the column
        // while the entity and repository already select it.
        if (!$patcher->tableHasColumn('post', 'description')) {
            $patcher->executeSql('ALTER TABLE `post` ADD COLUMN `description` TEXT DEFAULT NULL AFTER `title`');
        }
    }
}
