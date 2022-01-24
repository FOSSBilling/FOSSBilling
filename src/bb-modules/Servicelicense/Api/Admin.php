<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Servicelicense\Api;
/**
 *Service license management 
 */
class Admin extends \Api_Abstract
{
    /**
     * Get available licensing plugins
     * 
     * @param array $data
     * @return array
     */
    public function plugin_get_pairs(array $data)
    {
        $plugins = $this->getService()->getLicensePlugins();
        $result = array();
        foreach($plugins as $plugin) {
            $filename = $plugin['filename'];
            $result[$filename] = $filename;
        }
        return $result;
    }

    /**
     * Update license parameters. Set which validation rules must be applied
     * for license
     * 
     * @param int $order_id - License irder id
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
     * @return boolean 
     */
    public function update($data)
    {
        $s = $this->_getService($data);
        
       return $this->getService()->update($s, $data);
    }
    
    /**
     * Reset license validation rules.
     * 
     * @param int $order_id - License service order id
     * @return boolean 
     */
    public function reset($data)
    {
        $s = $this->_getService($data);
        return $this->getService()->reset($s);
    }

    /**
     * @param array $data
     * @return \Model_ServiceLicense
     * @throws \Box_Exception
     */
    public function _getService(array $data)
    {
        $required = array('order_id' => 'Order id is required');
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->getExistingModelById('clientOrder', $data['order_id'], 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if(!$s instanceof \Model_ServiceLicense) {
            throw new \Box_Exception('Order is not activated');
        }
        return $s;
    }
}