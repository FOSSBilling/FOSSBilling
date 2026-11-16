<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch153 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableExists('invoice_gateway_charge_attempt')) {
            $patcher->executeSql('CREATE TABLE `invoice_gateway_charge_attempt` (
                `id` VARCHAR(32) NOT NULL,
                `invoice_id` BIGINT NOT NULL,
                `gateway_id` BIGINT NOT NULL,
                `state` VARCHAR(16) NOT NULL,
                `payment_id` VARCHAR(255) DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `resolution` JSON DEFAULT NULL,
                `resolved_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `gateway_charge_attempt_invoice_idx` (`invoice_id`, `state`),
                KEY `gateway_charge_attempt_pending_idx` (`state`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        }
    }
}
