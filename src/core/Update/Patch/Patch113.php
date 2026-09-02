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

use FOSSBilling\Core\System\Config;
use FOSSBilling\Core\Update\Patcher;
use Symfony\Component\Filesystem\Path;

class Patch113 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // admin.salt is dead weight from a pre-password_hash() auth scheme - nothing in the
        // codebase reads or writes it (Config::getProperty('info.salt') is an unrelated
        // app-wide config value, not this per-admin column). Confirmed no other code path
        // depends on its presence before dropping it for real.
        if ($patcher->tableHasColumn('admin', 'salt')) {
            $patcher->executeSql('ALTER TABLE `admin` DROP COLUMN `salt`');
        }
    }
}
