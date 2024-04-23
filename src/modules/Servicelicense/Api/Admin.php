<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicelicense\Api;

/**
 *Service license management.
 */
class Admin extends \Api_Abstract
{
    /**
     * Get available licensing plugins.
     */
    public function plugin_get_pairs(array $data): array
    {
        $plugins = $this->getService()->getLicensePlugins();
        $result = [];
        foreach ($plugins as $plugin) {
            $filename = $plugin['filename'];
            $result[$filename] = $filename;
        }

        return $result;
    }

    /**
     * Update license parameters. Set which validation rules must be applied
     * for license.
     *
     * @optional string $plugin - New license plugin name
     * @optional bool $validate_ip - True to validate IP; False - to allow all IPs for this license
     * @optional bool $validate_host - True to validate hostname; False - to allow all hostnames for this license
     * @optional bool $validate_path - True to validate install paths; False - to allow all paths for this license
     * @optional bool $validate_version - True to validate version; False - to allow all versions for this license
     * @optional array $ips - List of allowed IPs for this license
     * @optional array $hosts - List of allowed hosts for this license
     * @optional array $paths - List of allowed paths for this license
     * @optional array $versions - List of allowed versions for this license
     *
     * @return bool
     */
    public function update($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->update($s, $data);
    }

    /**
     * Reset license validation rules.
     *
     * @return bool
     */
    public function reset($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->reset($s);
    }

    /**
     * @return \Model_ServiceLicense
     *
     * @throws \FOSSBilling\Exception
     */
    public function _getService(array $data)
    {
        $required = ['order_id' => 'Order ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->getExistingModelById('clientOrder', $data['order_id'], 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof \Model_ServiceLicense) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return $s;
    }
}
