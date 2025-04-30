<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Spam checking module management.
 */

namespace Box\Mod\Antispam\Api;

use FOSSBilling\InformationException;

class Admin extends \Api_Abstract
{
    /**
     * Adds an IP address to the client block-list
     *
     * @return array
     */
    public function block_ip($data)
    {
        $required = [
            'ip' => 'IP address is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (filter_var($data['ip'], FILTER_VALIDATE_IP)) {
            $config = $this->di['mod_config']('antispam');
            $blocked_ips = $this->getIpList();
            if (in_array($data['ip'], $blocked_ips)) {
                throw new InformationException(':placeholder: is already blocked.', [':placeholder:' => $data['ip']]);
            }
            $blocked_ips[] = $data['ip'];

            $config['blocked_ips'] = $this->stringifyList($blocked_ips);
            error_log('After: ' . $config['blocked_ips']);
            $config['ext'] = 'mod_antispam';

            $this->di['mod_service']('extension')->setConfig($config);
        } else {
            throw new InformationException(':placeholder: is not a valid IP address.', [':placeholder:' => $data['ip']]);
        }

        return true;
    }

    private function getIpList()
    {
        $config = $this->di['mod_config']('antispam');
        error_log('Before: ' . $config['blocked_ips']);
        $blocked_ips = explode(PHP_EOL, $config['blocked_ips']);
        $blocked_ips = array_map(trim(...), $blocked_ips);

        return array_map(trim(...), $blocked_ips);
    }

    private function stringifyList(array $list)
    {
        foreach ($list as $entry) {
            if (isset($result)) {
                $result = $result . PHP_EOL . $entry;
            } else {
                $result = $entry;
            }
        }

        return $result;
    }
}
