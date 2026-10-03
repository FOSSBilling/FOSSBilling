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
use Symfony\Component\Filesystem\Path;

class Patch128 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Remove obsolete files left behind by the core library reorganization.
        $patcher->executeFileActions([
            Path::join(PATH_LIBRARY, 'FOSSBilling') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box') => 'unlink',
        ]);
    }
}
