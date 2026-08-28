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

use Box\Mod\Extension\Entity\Extension;
use FOSSBilling\Update\Patcher;
use Symfony\Component\Filesystem\Path;

class Patch66 implements PatchInterface
{
    public function getVersion(): int
    {
        return 66;
    }

    public function apply(Patcher $patcher): void
    {
        // The original removal/migration patches for these modules only handled their data
        // migrations. Installations that already ran those patches need a new patch level
        // to clean up stale extension records and module directories left on disk.
        $patcher->executeSql("DELETE FROM extension_meta WHERE extension IN ('mod_paidsupport', 'mod_servicemembership') OR (rel_type = 'mod' AND LOWER(rel_id) IN ('paidsupport', 'servicemembership'))");
        $patcher->executeSql("DELETE FROM extension WHERE type = 'mod' AND LOWER(name) IN ('paidsupport', 'servicemembership')");

        $patcher->executeFileActions([
            Path::join(PATH_MODS, 'Paidsupport') => 'unlink',
            Path::join(PATH_MODS, 'Servicemembership') => 'unlink',
        ]);
    }
}
