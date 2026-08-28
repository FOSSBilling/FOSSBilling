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
use Symfony\Component\Filesystem\Path;

class Patch38 implements PatchInterface
{
    public function getVersion(): int
    {
        return 38;
    }

    public function apply(Patcher $patcher): void
    {
        // We need to remove the old ISPConfig3 and Virtualmin server managers from disk or else the leftover files could prevent the "hosting plans and servers" page from being loaded.
        $fileActions = [
            Path::join(PATH_LIBRARY, 'Server', 'Manager', 'Ispconfig3.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Server', 'Manager', 'Virtualmin.php') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);
    }
}
