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

class Patch33 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $fileActions = [
            Path::join(PATH_VENDOR, 'guzzlehttp') => 'unlink',
            Path::join(PATH_ROOT, 'htaccess.txt') => 'unlink',
            Path::join(PATH_ROOT, 'config.php.old') => 'unlink',
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
            Path::join(PATH_LIBRARY, 'FileCache.php') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);
    }
}
