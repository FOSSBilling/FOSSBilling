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

class Patch39 implements PatchInterface
{
    public function getVersion(): int
    {
        return 39;
    }

    public function apply(Patcher $patcher): void
    {
        // Superset of 38 + 39: Ispconfig3/Virtualmin managers + srp locale fix.
        // 38 is now a gap; this single patch deletes all 3 orphans idempotently.
        $fileActions = [
            Path::join(PATH_LIBRARY, 'Server', 'Manager', 'Ispconfig3.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Server', 'Manager', 'Virtualmin.php') => 'unlink',
            Path::join(PATH_LANGS, 'srp') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);
    }
}
