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

class Patch33 implements PatchInterface
{
    public function getVersion(): int
    {
        return 33;
    }

    public function apply(Patcher $patcher): void
    {
        // Patch to remove the old FileCache class that was replaced with Symfony's Cache component.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/1184
        $fileActions = [
            Path::join(PATH_LIBRARY, 'FileCache.php') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);
    }
}
