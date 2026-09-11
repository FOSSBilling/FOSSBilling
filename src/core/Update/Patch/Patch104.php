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

class Patch104 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Legacy RedBeanPHP installs stored `0` rather than NULL for clients with no
        // group, since group ids start at 1. The Client entity's ClientGroup
        // association only tolerates NULL, so Doctrine throws "Entity of type
        // '...ClientGroup' for IDs id(0) was not found" the moment it tries to load
        // the association for these clients.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/4160
        $patcher->executeSql('UPDATE `client` SET `client_group_id` = NULL WHERE `client_group_id` = 0;');
    }
}
