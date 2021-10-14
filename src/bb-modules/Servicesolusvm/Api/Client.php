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

namespace Box\Mod\Servicesolusvm\Api;
/**
 * Solusvm service management
 */
class Client extends \Api_Abstract
{
    
    private function _getService($data)
    {
        if(!isset($data['order_id'])) {
            throw new \Box_Exception('Order id is required');
        }
        
        $order = $this->di['db']->findOne('client_order',
                "id=:id 
                 AND client_id = :cid
                 AND service_type = 'solusvm'
                ", 
                array(':id'=>$data['order_id'], ':cid'=>$this->getIdentity()->id));
        
        if(!$order) {
            throw new \Box_Exception('Solusvm order not found');
        }
        
        $s = $this->di['db']->findOne('service_solusvm',
                'id=:id AND client_id = :cid',
                array(':id'=>$order->service_id, ':cid'=>$this->getIdentity()->id));
        if(!$s) {
            throw new \Box_Exception('Order is not activated');
        }
        return array($order, $s);
    }
    
    /**
     * Reboot VPS
     * @param int $order_id - order id
     * @return bool 
     */
    public function reboot($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->reboot($order, $vps, $data);
        $this->di['logger']->info('Rebooted VPS. Order ID #%s', $order->id);
        return true;
    }

    /**
     * Boot VPS
     * @param int $order_id - order id
     * @return bool 
     */
    public function boot($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->boot($order, $vps, $data);
        $this->di['logger']->info('Booted VPS. Order ID #%s', $order->id);
        return true;
    }

    /**
     * Shutdown VPS
     * @param int $order_id - order id
     * @return bool 
     */
    public function shutdown($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->shutdown($order, $vps, $data);
        $this->di['logger']->info('Shut down VPS. Order ID #%s', $order->id);
        return true;
    }

    /**
     * Get status VPS
     * @param int $order_id - order id
     * @return online|offline
     */
    public function status($data)
    {
        list($order, $vps) = $this->_getService($data);
        return $this->getService()->status($order, $vps, $data);
    }

    /**
     * Retrieve more information about vps from sulusvm server
     * @param int $order_id - order id
     * @return array
     */
    public function info($data)
    {
        list(, $vps) = $this->_getService($data);
        try {
            $result = $this->getService()->info($vps->vserverid);
        } catch(\Exception $exc) {
            error_log($exc);
            $result = array();
        }
        return $result;
    }
    
    /**
     * Change root password for VPS
     * @param int $order_id - order id
     * @param string $password - new password
     * @return bool 
     */
    public function set_root_password($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->set_root_password($order, $vps, $data);
        $this->di['logger']->info('Changed VPS root password. Order ID #%s', $order->id);
        return true;
    }

    /**
     * Change hostname for VPS
     * @param int $order_id - order id
     * @return bool 
     */
    public function set_hostname($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->set_hostname($order, $vps, $data);
        $this->di['logger']->info('Changed VPS hostname. Order ID #%s', $order->id);
        return true;
    }

    /**
     * Change client area password for solusvm user
     * @param int $order_id - order id
     * @param string $password - new password
     * @return bool 
     */
    public function change_password($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->client_change_password($order, $vps, $data);
        $this->di['logger']->info('Changed SolusVM client area password. Order ID #%s', $order->id);
        return true;
    }

    /**
     * Rebuild vps operating system with new template
     * @param int $order_id - order id
     * @param string $template - template idetification
     * @return bool 
     */
    public function rebuild($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->rebuild($order, $vps, $data);
        $this->di['logger']->info('Changed VPS template. Order ID #%s', $order->id);
        return true;
    }
}