<?php

declare(strict_types=1);

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch144 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('invoice', 'issue_notification_state')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD COLUMN `issue_notification_state` SMALLINT NOT NULL DEFAULT 0');
        }
    }
}
