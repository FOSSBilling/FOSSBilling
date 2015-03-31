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

namespace Box\Mod\Servicecentovacast\Api;

/**
 * CentovaCast management
 */
class Admin extends \Api_Abstract
{
    /**
     * Return centovacast servers
     * 
     * @return boolean 
     */
    public function servers()
    {
        return $this->getService()->getServers();
    }
    
    /**
     * Get server pairs
     * 
     * @return array
     */
    public function server_pairs()
    {
        $servers = $this->getService()->getServers();
        $result = array();
        foreach($servers as $server) {
            $result[$server['id']] = $server['hostname'];
        }
        return $result;
    }
    
    /**
     * Add new centovacast server
     * 
     * @param type $data
     * @return int - server id 
     */
    public function server_add($data)
    {
        $bean = $this->di['db']->dispense('extension_meta');
        $bean->extension = 'mod_servicecentovacast';
        $bean->meta_key = 'server';
        $bean->meta_value = json_encode($data);
        $bean->created_at = date('Y-m-d H:i:s');
        $bean->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($bean);
        
        $this->di['logger']->info('Added new CentovaCast server %s', $bean->id);
        return $bean->id;
    }
    
    /**
     * Get server
     * 
     * @param int $id - server id
     * @return type
     * @throws Exception 
     */
    public function server_get($data)
    {
        if(!isset($data['id'])) {
            throw new \Exception('Server id not passed');
        }
        return $this->getService()->getServer($data['id']);
    }
    
    /**
     * Update server
     * 
     * @param int $id - server id
     * @return bool
     * @throws Exception 
     */
    public function server_update($data)
    {
        $server = $this->server_get($data);
        $bean = $this->di['db']->findOne('extension_meta', 'id = :id', array('id'=>$server['id']));
        $bean->meta_value = json_encode($data);
        $bean->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($bean);
        
        $this->di['logger']->info('Updated CentovaCast server %s', $server['id']);
        return true;
    }
    
    /**
     * Remove server
     * 
     * @param int $id - server id
     * @return type
     * @throws Exception 
     */
    public function server_delete($data)
    {
        $server = $this->server_get($data);
        
        $sql="SELECT id FROM service_centovacast WHERE server_id = :sid LIMIT 1";
        $exists = $this->di['db']->getCell($sql, array('sid'=>$server['id']));
        if($exists) {
            throw new \Exception('Server is used for active order. It can not be removed.');
        }
        
        $sql="DELETE FROM extension_meta WHERE id = :id";
        $this->di['db']->exec($sql, array('id'=>$server['id']));
        $this->di['logger']->info('Removed CentovaCast server %s', $server['id']);
        return true;
    }
    
    /**
     * Test connection to server
     * 
     * @param int $id - server id
     * @return boolean
     * @throws Exception 
     */
    public function server_connection($data)
    {
        $server = $this->server_get($data);
        return $this->getService()->apiConnection($server);
    }
    
    /**
     * Update existing order service
     * 
     * @return boolean 
     */
    public function update($data)
    {
        list($order, $s) = $this->_getService($data);
        
        $s->username = $this->di['array_get']($data, 'username', $s->username);
        if(isset($data['pass'])) {
            $s->pass = $this->getService()->encryptPass($data['pass']);
        }
        
        $s->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($s);
        
        $this->di['logger']->info('Updated CentovaCast service %s details', $s->id);
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
     * @return array
     */
    public function getaccount($data)
    {
        try {
            list($order, $model) = $this->_getService($data);
            $result = $this->getService()->getaccount($model);
        } catch(\Exception $e) {
            error_log($e->getMessage());
            $result = array();
            if(!isset($data['try']) || !$data['try']) {
                throw $e;
            }
        }
        
        return $result;
    }
    
    /**
     * Returns the state (up or down) of one or more CentovaCast streaming 
     * server accounts. This can be used to monitor streams to see if any 
     * have crashed. (Note that CentovaCast's cron job automatically monitors 
     * and restarts crashed streaming servers as well.)

     * @param int $order_id - order id
     * @optional bool $try - do not throw an exception, return error message as a result
     * 
     * @return boolean 
     */
    public function info($data)
    {
        list($order, $model) = $this->_getService($data);
        
        try {
            $result = $this->getService()->info($model);
        } catch(\Exception $e) {
            $result = $e->getMessage();
            if(!isset($data['try']) || !$data['try']) {
                throw $e;
            }
        }
        
        return $result;
    }
    
    /**
     * Updates the settings for an existing client streaming server 
     * account in CentovaCast.
     * 
     * @param int $order_id - order id
     * @return bool 
     */
    public function reconfigure($data)
    {
        list($order, $model) = $this->_getService($data);
        return $this->getService()->reconfigure($model, $data);
    }
    
    private function _getService($data)
    {
        if(!isset($data['order_id'])) {
            throw new \Box_Exception('Order id is required');
        }
        
        $order = $this->di['db']->findOne('client_order',
                "id=:id 
                 AND service_type = 'centovacast'
                ", 
                array('id'=>$data['order_id']));
        
        if(!$order) {
            throw new \Box_Exception('Centova Cast order not found');
        }
        
        $s = $this->di['db']->findOne('service_centovacast',
                'id=:id',
                array('id'=>$order->service_id));
        if(!$s) {
            throw new \Box_Exception('Order :id is not activated', array(':id'=>$order->id));
        }
        return array($order, $s);
    }
}