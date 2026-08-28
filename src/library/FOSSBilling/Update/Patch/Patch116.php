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

class Patch116 implements PatchInterface
{
    public function getVersion(): int
    {
        return 116;
    }

    public function apply(Patcher $patcher): void
    {
        $patcher->executeFileActions([
            Path::join(PATH_LIBRARY, 'Box', 'App.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'AppAdmin.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'AppClient.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Authorization.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Event.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'EventDispatcher.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'EventManager.php') => 'unlink',
        ]);

        $patcher->removeEmptyDirectories([
            Path::join(PATH_LIBRARY, 'Box'),
        ]);
    }
}
