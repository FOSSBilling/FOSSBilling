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

class Patch43 implements PatchInterface
{
    public function getVersion(): int
    {
        return 43;
    }

    public function apply(Patcher $patcher): void
    {
        $fileActions = [
            Path::join(PATH_LIBRARY, 'GeoLite2-Country.mmdb') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);
    }
}
