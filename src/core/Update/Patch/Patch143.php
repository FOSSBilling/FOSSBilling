<?php

declare(strict_types=1);

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch143 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('client_balance', 'gateway_transaction_id')) {
            $patcher->executeSql('ALTER TABLE `client_balance` ADD COLUMN `gateway_transaction_id` BIGINT DEFAULT NULL');
        }
    }
}
