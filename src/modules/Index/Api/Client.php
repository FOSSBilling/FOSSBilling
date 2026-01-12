<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Index\Api;

class Client extends \Api_Abstract
{
    public function get_dashboard($data): array
    {
        $identity = $this->getIdentity();

        return $this->getService()->getDashboardData($identity);
    }
}
