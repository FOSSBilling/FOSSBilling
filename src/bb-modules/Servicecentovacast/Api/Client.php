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

namespace Box\Mod\Servicecentovacast\Api;
/**
 * CentovaCast management
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
                 AND service_type = 'centovacast'
                ", 
                array('id'=>$data['order_id'], 'cid'=>$this->getIdentity()->id));
        
        if(!$order) {
            throw new \Box_Exception('Centova Cast order not found');
        }
        
        $s = $this->di['db']->findOne('service_centovacast',
                'id=:id AND client_id = :cid',
                array('id'=>$order->service_id, 'cid'=>$this->getIdentity()->id));
        if(!$s) {
            throw new \Box_Exception('Order :id is not activated', array(':id'=>$order->id));
        }
        return array($order, $s);
    }
    
    /**
     * Return control panel url for order
     * 
     * @param int $order_id - order id
     * @return string
     */
    public function control_panel_url($data)
    {
        try {
            list($order, $model) = $this->_getService($data);
            $result = $this->getService()->cpanelUrl($model);
        } catch(\Exception $e) {
            $result = $e->getMessage();
            if(!isset($data['try']) || !$data['try']) {
                throw $e;
            }
        }
        return $result;
    }
    
    /**
     * Starts a streaming server for a CentovaCast client account. 
     * If server-side streaming source support is enabled, 
     * the streaming source is started as well.
     * 
     * @param int $order_id - order id
     * @return boolean 
     */
    public function start($data)
    {
        list($order, $model) = $this->_getService($data);
        $this->getService()->start($model);
        $this->di['logger']->info('Started server %s', $order->id);
        return true;
    }
    
    /**
     * Stops a streaming server for a CentovaCast client account. 
     * If server-side streaming source support is enabled, 
     * the streaming source is stopped as well.
     * 
     * @param int $order_id - order id
     * @return boolean 
     */
    public function stop($data)
    {
        list($order, $model) = $this->_getService($data);
        $this->getService()->stop($model);
        $this->di['logger']->info('Stoped server %s', $order->id);
        return true;
    }
    
    /**
     * Stops, then re-starts a streaming server for a CentovaCast client account.
     * If server-side streaming source support is enabled, the streaming 
     * source is restarted as well.
     * 
     * @param int $order_id - order id
     * @return boolean 
     */
    public function restart($data)
    {
        list($order, $model) = $this->_getService($data);
        $this->getService()->restart($model);
        $this->di['logger']->info('Restarted server %s', $order->id);
        return true;
    }
    
    /**
     * Reloads the streaming server configuration for a CentovaCast client account. 
     * If server-side streaming source support is enabled, 
     * the configuration and playlist for the streaming source 
     * is reloaded as well.
     * 
     * @param int $order_id - order id
     * @return boolean 
     */
    public function reload($data)
    {
        list($order, $model) = $this->_getService($data);
        $this->getService()->reload($model);
        $this->di['logger']->info('Reloaded server %s', $order->id);
        return true;
    }
    
    /**
     * Retrieves the configuration for a CentovaCast client account. 
     * If server-side streaming source support is enabled, 
     * the configuration for the streaming source is returned as well.
     * 
     * @param int $order_id - order id
     * @optional bool $try - do not throw an exception, return error message as a result
     * 
     * @return boolean 
     */
    public function getaccount($data)
    {
        list($order, $model) = $this->_getService($data);
        
        try {
            $result = $this->getService()->getaccount($model);
        } catch(\Exception $e) {
            $result = $e->getMessage();
            if(!isset($data['try']) || !$data['try']) {
                throw $e;
            }
        }
        
        return $result;
    }
    
    /**
     * Retrieves status information from the streaming server for a 
     * CentovaCast client account.
     * 
     * @param int $order_id - order id
     * @optional bool $try - do not throw an exception, return error message as a result
     * 
     * @return boolean 
     */
    public function getstatus($data)
    {
        list($order, $model) = $this->_getService($data);
        
        try {
            $result = $this->getService()->getstatus($model);
        } catch(\Exception $e) {
            $result = $e->getMessage();
            if(!isset($data['try']) || !$data['try']) {
                throw $e;
            }
        }
        
        return $result;
    }
    
    /**
     * Retrieves a list of tracks that were recently broadcasted on a 
     * given CentovaCast client's streaming server.
     * 
     * @param int $order_id - order id
     * @optional bool $try - do not throw an exception, return error message as a result
     * 
     * @return boolean 
     */
    public function getsongs($data)
    {
        list($order, $model) = $this->_getService($data);
        
        try {
            $result = $this->getService()->getsongs($model);
        } catch(\Exception $e) {
            $result = $e->getMessage();
            if(!isset($data['try']) || !$data['try']) {
                throw $e;
            }
        }
        
        return $result;
    }
}