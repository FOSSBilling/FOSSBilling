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

class Patch32 implements PatchInterface
{
    public function getVersion(): int
    {
        return 32;
    }

    public function apply(Patcher $patcher): void
    {
        // Patch to remove the old phpmailer package, some leftover admin_default files, and old Box_ classes.
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/1091
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/1063
        $fileActions = [
            Path::join(PATH_VENDOR, 'phpmailer') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'images') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'scss', 'bb-deprecated.scss') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'scss', 'dataTable-deprecated.scss') => 'unlink',
            Path::join(PATH_THEMES, 'admin_default', 'assets', 'scss', 'main-deprecated.scss') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Mail.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Ftp.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'FileCacheExcption.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Zip.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Requirements.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Version.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Extension.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Cookie.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'ExceptionAuth.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Response.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Config.php') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);
    }
}
