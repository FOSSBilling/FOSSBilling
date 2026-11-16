<?php

declare(strict_types=1);

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch145 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('transaction', 'gateway_completion_state')) {
            $patcher->executeSql('ALTER TABLE `transaction` ADD COLUMN `gateway_completion_state` SMALLINT NOT NULL DEFAULT 0');
        }
        if (!$patcher->tableHasIndex('client_balance', 'client_balance_gateway_transaction_id_idx')) {
            $patcher->executeSql('CREATE INDEX `client_balance_gateway_transaction_id_idx` ON `client_balance` (`gateway_transaction_id`)');
        }
    }
}
