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

class Patch103 implements PatchInterface
{
    public function getVersion(): int
    {
        return 103;
    }

    public function apply(Patcher $patcher): void
    {
        // Money columns: replace legacy DOUBLE/VARCHAR storage with DECIMAL so
        // monetary values are stored exactly (matches the DECIMAL entity mappings).
        $decimalColumns = [
            'invoice' => ['credit', 'base_income', 'base_refund', 'refund'],
            'invoice_item' => ['price'],
            'subscription' => ['amount'],
            'client_order' => ['price', 'discount'],
            'transaction' => ['amount'],
        ];

        foreach ($decimalColumns as $table => $columns) {
            if (!$patcher->tableExists($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if ($patcher->tableHasColumn($table, $column)) {
                    $patcher->executeSql("ALTER TABLE `{$table}` MODIFY `{$column}` decimal(18,2) DEFAULT NULL");
                }
            }
        }

        // client.gender: replace the MySQL-only ENUM with a plain varchar. The
        // allowed values are now validated in the Client entity.
        if ($patcher->tableExists('client') && $patcher->tableHasColumn('client', 'gender')) {
            $patcher->executeSql('ALTER TABLE `client` MODIFY `gender` varchar(20) DEFAULT NULL');
        }

        // mod_massmailer: legacy module installs created the datetime columns as
        // varchar(35); align them with the DATETIME entity mapping and structure.sql.
        if ($patcher->tableExists('mod_massmailer')) {
            foreach (['sent_at', 'created_at', 'updated_at'] as $column) {
                if ($patcher->tableHasColumn('mod_massmailer', $column)) {
                    $patcher->executeSql("ALTER TABLE `mod_massmailer` MODIFY `{$column}` datetime DEFAULT NULL");
                }
            }
        }
    }
}
