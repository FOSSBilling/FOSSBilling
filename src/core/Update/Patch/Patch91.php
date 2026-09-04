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

class Patch91 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // ClientBalance did not declare its one-time payment capability, so the gateway
        // settings form hid the option and persisted allow_single = 0 whenever it was saved.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/3989
        $patcher->executeSql(
            'UPDATE pay_gateway SET allow_single = 1 WHERE gateway = :gateway AND allow_single = 0',
            ['gateway' => 'ClientBalance']
        );
    }
}
