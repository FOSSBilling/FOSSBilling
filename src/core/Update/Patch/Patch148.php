<?php

declare(strict_types=1);

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch148 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('subscription', 'gateway_cancellation_confirmation')) {
            $patcher->executeSql('ALTER TABLE `subscription` ADD COLUMN `gateway_cancellation_confirmation` VARCHAR(64) DEFAULT NULL');
        }
    }
}
