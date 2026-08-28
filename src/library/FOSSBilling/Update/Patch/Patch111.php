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

class Patch111 implements PatchInterface
{
    public function getVersion(): int
    {
        return 111;
    }

    public function apply(Patcher $patcher): void
    {
        // The Serviceapikey module (PR #4055) added the ServiceApiKey Doctrine entity but
        // never gave it a structure.sql counterpart, so the service_apikey table was never
        // created on any MySQL install — fresh or upgraded.
        if (!$patcher->tableExists('service_apikey')) {
            $patcher->executeSql(
                'CREATE TABLE `service_apikey` (
                    `id` BIGINT NOT NULL AUTO_INCREMENT,
                    `client_id` BIGINT DEFAULT NULL,
                    `api_key` VARCHAR(255) DEFAULT NULL,
                    `config` TEXT,
                    `created_at` DATETIME DEFAULT NULL,
                    `updated_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `client_id_idx` (`client_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }
    }
}
