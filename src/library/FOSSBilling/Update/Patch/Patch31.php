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

use FOSSBilling\System\Config;
use FOSSBilling\Update\Patcher;
use Symfony\Component\Filesystem\Path;

class Patch31 implements PatchInterface
{
    public function getVersion(): int
    {
        return 31;
    }

    public function apply(Patcher $patcher): void
    {
        // Patch to remove the old htaccess.txt file, and any old config.php backup.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/1075
        $fileActions = [
            Path::join(PATH_ROOT, 'htaccess.txt') => 'unlink',
            Path::join(PATH_ROOT, 'config.php.old') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);
    }
}
