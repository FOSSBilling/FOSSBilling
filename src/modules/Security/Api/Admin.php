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
    /**
     * Returns information on an IP address from the application's included database.
     */
    public function ip_lookup(array $data): array
    {
        if (!isset($data['ip'])) {
            throw new InformationException('You must specify an IP address to lookup.');
        }

        return $this->getService()->lookupIP($data['ip']);
    }

    /**
     * @return array<string|int, string>
     */
    private function getIpList()
    {
        $config = $this->di['mod_config']('antispam');
        $blocked_ips = explode(PHP_EOL, $config['blocked_ips']);
        $blocked_ips = array_map(trim(...), $blocked_ips);

        return array_filter($blocked_ips);
    }

    /**
     * Adds an IP address to the client block-list.
     */
    public function block_ip($data): bool
    {
        $required = [
            'ip' => 'IP address is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (filter_var($data['ip'], FILTER_VALIDATE_IP)) {
            $config = $this->di['mod_config']('security');
            $blocked_ips = $this->getIpList();
            if (in_array($data['ip'], $blocked_ips)) {
                throw new InformationException(':placeholder: is already blocked.', [':placeholder:' => $data['ip']]);
            }
            $blocked_ips[] = $data['ip'];

            $config['blocked_ips'] = implode(PHP_EOL, $blocked_ips);
            $config['block_ips'] = true;
            $config['ext'] = 'mod_antispam';

            $this->di['mod_service']('extension')->setConfig($config);
        } else {
            throw new InformationException(':placeholder: is not a valid IP address.', [':placeholder:' => $data['ip']]);
        }

        return true;
    }

    /**
     * Lists the available security checks that can be run.
     */
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

    /**
     * Run the specified check.
     */
    public function run_check(array $data): array
    {
        if (!isset($data['id'])) {
            throw new InformationException('You must specify a check ID to run.');
        }

        return $this->getService()->runCheck($data['id']);
    }

    /**
     * Runs all available checks.
     */
    public function run_checks(array $data): array
    {
        return $this->getService()->runAllChecks();
    }
}
