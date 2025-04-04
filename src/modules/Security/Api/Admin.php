<?php

/**
 * Copyright 2022-2025 FOSSBilling
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
    public function ip_lookup(array $data): array
    {
        if (!isset($data['ip'])) {
            throw new InformationException('You must specify an IP address to lookup.');
        }

        return $this->getService()->lookupIP($data['ip']);
    }

    public function list_checks(array $data): array
    {
        $result = [];
        $checkInterfaces = $this->getService()->getAllChecks();
        foreach ($checkInterfaces as $id => $interface) {
            $result[] = [
                'id' => $id,
                'name' => $interface->getName(),
                'description' => $interface->getDescription(),
            ];
        }

        return $result;
    }

    public function run_check(array $data): array
    {
        if (!isset($data['id'])) {
            throw new InformationException('You must specify a check ID to run.');
        }

        return $this->getService()->runCheck($data['id']);
    }

    public function run_checks(array $data): array
    {
        return $this->getService()->runAllChecks();
    }
}
