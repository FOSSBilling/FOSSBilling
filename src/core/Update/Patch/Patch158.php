<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch158 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableExists('invoice_gateway_refund_attempt')) {
            $patcher->executeSql(
                'CREATE TABLE `invoice_gateway_refund_attempt` (
                    `id` VARCHAR(32) NOT NULL,
                    `invoice_id` BIGINT NOT NULL,
                    `fingerprint` VARCHAR(64) NOT NULL,
                    `state` VARCHAR(16) NOT NULL,
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `gateway_refund_attempt_invoice_idx` (`invoice_id`, `state`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }
}
