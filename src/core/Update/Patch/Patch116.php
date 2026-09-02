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
    public function apply(Patcher $patcher): void
    {
        // Superset of 115 + 116: library reorg orphans (FOSSBilling/*) + Box/* move (4b7bf9acf).
        // 115 is now a no-op gap; this single patch deletes all 39 orphans idempotently.
        $patcher->executeFileActions([
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'CentralAlerts.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Config.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Crypt.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Environment.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'ErrorPage.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Exception.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'ExtensionManager.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Fingerprint.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'InformationException.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'InjectionAwareInterface.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Logger.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Monolog.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Pagination.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'PaginationOptions.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Paginator.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'PasswordManager.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Requirements.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'SecurityCheckResult.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Session.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Tools.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Translate.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Version.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'i18n.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Validate.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Update.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'UpdatePatcher.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'UpdateFinalization.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'UpdateReadinessCheck.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Twig', 'Enum', 'AppArea.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Interfaces', 'ApiArrayInterface.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Interfaces', 'SecurityCheckInterface.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Interfaces', 'TimestampInterface.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Interfaces', 'WidgetProviderInterface.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Enums', 'ClientStatusEnum.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Enums', 'ClientOrderStatusEnum.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Enums', 'SecurityCheckResultEnum.php') => 'unlink',
            Path::join(PATH_ROOT, 'tests', 'Unit', 'FOSSBilling', 'ToolsTest.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'App.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'AppAdmin.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'AppClient.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Authorization.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Event.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'EventDispatcher.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'EventManager.php') => 'unlink',
        ]);

        $patcher->removeEmptyDirectories([
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Twig', 'Enum'),
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Interfaces'),
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Enums'),
            Path::join(PATH_LIBRARY, 'Box'),
        ]);
    }
}
