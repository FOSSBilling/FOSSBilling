<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Security\Api;

use FOSSBilling\InformationException;

class Admin extends \Api_Abstract
{
    public function ip_lookup($data)
    {
        if (!isset($data['ip'])) {
            throw new InformationException('You must specify an IP address to lookup.');
        }

        return $this->getService()->lookupIP($data['ip']);
    }
}
