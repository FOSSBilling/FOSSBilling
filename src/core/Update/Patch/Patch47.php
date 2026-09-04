<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch47 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Migrate "membership" product type to "custom" product type
        // This is part of removing the Servicemembership module
        // @see https://github.com/FOSSBilling/FOSSBilling/pull/3066

        // Migrate products to the 'custom' product type
        $q = 'UPDATE `product` SET `type` = "custom" WHERE `type` = "membership";';
        $patcher->executeSql($q);

        // Before migrating existing orders to the 'custom' product type,
        // set service_id to NULL for orders with service_type = "membership"
        $q = 'UPDATE `client_order` SET `service_id` = NULL WHERE `service_type` = "membership";';
        $patcher->executeSql($q);
        // Migrate existing orders to the 'custom' product type
        $q = 'UPDATE `client_order` SET `service_type` = "custom" WHERE `service_type` = "membership";';
        $patcher->executeSql($q);

        // Drop the service_membership table as it's no longer needed
        $q = 'DROP TABLE IF EXISTS `service_membership`;';
        $patcher->executeSql($q);
    }
}
