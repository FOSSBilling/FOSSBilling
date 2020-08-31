<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Serviceboxbillinglicense\Api;
/**
 * BoxBilling license management
 */
class Admin extends \Api_Abstract
{
    private function _getService($data)
    {
        if(!isset($data['order_id'])) {
            throw new \Box_Exception('Order id is required');
        }
        
        $order = $this->di['db']->findOne('client_order',
                "id=:id 
                 AND service_type = 'boxbillinglicense'
                ", 
                array('id'=>$data['order_id']));
        
        if(!$order) {
            throw new \Box_Exception('BoxBilling license order not found');
        }
        
        $s = $this->di['db']->findOne('service_boxbillinglicense',
                'id=:id',
                array('id'=>$order->service_id));
        if(!$s) {
            throw new \Box_Exception('Order is not activated');
        }
        return array($order, $s);
    }
    
    /**
     * Update module configuration
     * 
     * @param int $order_id - order id
     * @return bool 
     */
    public function config_update($data)
    {
        $this->getService()->setModuleConfig($data);
        $this->di['logger']->info('Updated configuration information.');
        return true;
    }
    
    /**
     * Get module configuration
     * 
     * @param int $order_id - order id
     * @return bool 
     */
    public function config_get($data)
    {
        return $this->getService()->getModuleConfig($data);
    }
    
    /**
     * Get detailed license order info 
     * 
     * @param int $order_id - order id
     * 
     * @return array
     */
    public function order_info($data)
    {
        list(, $service) = $this->_getService($data);
        return $this->getService()->licenseDetails($service);
    }
    
    /**
     * Reset license information. Usually used when moving BoxBilling to
     * new server.
     * 
     * @param int $order_id - order id
     * 
     * @return bool
     */
    public function order_reset($data)
    {
        list(, $service) = $this->_getService($data);
        $this->getService()->licenseReset($service);
        return true;
    }
    
    /**
     * Convenience method to become partner. After you become BoxBilling
     * partner you are able to sell licenses.
     * 
     * @return bool
     */
    public function become_partner()
    {
        return $this->getService()->becomePartner();
    }
    
    /**
     * Test connection to BoxBilling server. Used to test your configuration.
     * 
     * @return bool
     */
    public function test_connection()
    {
        $this->getService()->testApiConnection();
        return true;
    }
    
    /**
     * Update existing order service
     * This method used to change service data if order setup fails 
     * or have changed on remote server
     * 
     * @param int $order_id - order id
     * 
     * @return boolean 
     */
    public function update($data)
    {
        list(, $service) = $this->_getService($data);
        
        $service->oid = $this->di['array_get']($data, 'oid', $service->oid);
        $service->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($service);
        
        $this->di['logger']->info('Updated BoxBilling license %s details', $service->id);
        return true;
    }
}