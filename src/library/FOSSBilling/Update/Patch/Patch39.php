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
        // The Serbian language was incorrectly placed into a folder named `srp` by Crowdin which is now corrected for via the locale repo and as such we need to delete the old directory.
        // @see https://github.com/FOSSBilling/locale/issues/212
        $fileActions = [
            Path::join(PATH_LANGS, 'srp') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);
    }
}
