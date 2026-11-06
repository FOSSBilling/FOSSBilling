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

class Patch86 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Allows queued emails (e.g. invoice notifications) to carry a file attachment such as a PDF copy.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/1724
        if (!$patcher->tableHasColumn('email_queue', 'attachment_name')) {
            $patcher->executeSql('ALTER TABLE `email_queue` ADD COLUMN `attachment_name` varchar(255) DEFAULT NULL');
        }

        if (!$patcher->tableHasColumn('email_queue', 'attachment_content')) {
            $patcher->executeSql('ALTER TABLE `email_queue` ADD COLUMN `attachment_content` longblob DEFAULT NULL');
        }

        if (!$patcher->tableHasColumn('email_queue', 'attachment_mime')) {
            $patcher->executeSql('ALTER TABLE `email_queue` ADD COLUMN `attachment_mime` varchar(100) DEFAULT NULL');
        }
    }
}
