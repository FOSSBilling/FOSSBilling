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

class Patch37 implements PatchInterface
{
    public function getVersion(): int
    {
        return 37;
    }

    public function apply(Patcher $patcher): void
    {
        // Patch to completely remove the outdated queue module.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/1777

        try {
            $ext_service = $patcher->di['mod_service']('extension');
            // If the queue extension exists, uninstall it.
            $queue_ext = $ext_service->getExtensionRepository()->findOneByTypeAndName('mod', 'queue');
            if ($queue_ext instanceof Extension) {
                $ext_service->deactivate($queue_ext);
                $ext_service->uninstall('mod', 'queue');
            }
        } catch (\Exception) {
        }

        // Finally, remove old queue module from the disk.
        $fileActions = [
            Path::join(PATH_MODS, 'Queue') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);
    }
}
