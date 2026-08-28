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

class Patch30 implements PatchInterface
{
    public function getVersion(): int
    {
        return 30;
    }

    public function apply(Patcher $patcher): void
    {
        // Patch to remove the old guzzlehttp package, as we no longer use it.
        $fileActions = [
            Path::join(PATH_VENDOR, 'guzzlehttp') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);
    }
}
