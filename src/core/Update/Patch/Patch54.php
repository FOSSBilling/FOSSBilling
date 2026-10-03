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

class Patch54 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasIndex('api_request', 'api_request_ip_created')) {
            $patcher->executeSql('ALTER TABLE `api_request` ADD INDEX `api_request_ip_created` (`ip`, `created_at`);');
        }

        $fileActions = [
            Path::join(PATH_LIBRARY, 'Model', 'ClientPasswordResetTable.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ApiRequestTable.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ApiRequest.php') => 'unlink',
        ];
        $patcher->executeFileActions($fileActions);
    }
}
