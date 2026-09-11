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

class Patch87 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Carries the same attachment (e.g. a PDF invoice) into the sent-email activity log, so
        // resending a logged email (client or admin "resend") can reattach it.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/1724
        if (!$patcher->tableHasColumn('activity_client_email', 'attachment_name')) {
            $patcher->executeSql('ALTER TABLE `activity_client_email` ADD COLUMN `attachment_name` varchar(255) DEFAULT NULL');
        }

        if (!$patcher->tableHasColumn('activity_client_email', 'attachment_content')) {
            $patcher->executeSql('ALTER TABLE `activity_client_email` ADD COLUMN `attachment_content` longblob DEFAULT NULL');
        }

        if (!$patcher->tableHasColumn('activity_client_email', 'attachment_mime')) {
            $patcher->executeSql('ALTER TABLE `activity_client_email` ADD COLUMN `attachment_mime` varchar(100) DEFAULT NULL');
        }
    }
}
