<?php

declare(strict_types=1);

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch147 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('invoice', 'subscription_payment_draft')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD COLUMN `subscription_payment_draft` TINYINT(1) NOT NULL DEFAULT 0');
        }
    }
}
