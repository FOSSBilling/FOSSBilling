<?php

declare(strict_types=1);

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch149 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('invoice', 'cron_renewal_expiries')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD COLUMN `cron_renewal_expiries` JSON NULL');
        }
    }
}
