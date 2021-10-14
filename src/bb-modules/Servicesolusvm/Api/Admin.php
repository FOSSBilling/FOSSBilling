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
 * Solusvm management
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
                 AND service_type = 'solusvm'
                ", 
                array(':id'=>$data['order_id']));
        
        if(!$order) {
            throw new \Box_Exception('Solusvm order not found');
        }
        
        $s = $this->di['db']->findOne('service_solusvm',
                'id=:id',
                array(':id'=>$order->service_id));
        if(!$s) {
            throw new \Box_Exception('Order is not activated');
        }
        return array($order, $s);
    }
    
    /**
     * Update master server configuration
     * @param int $cluster_id - cluster ID
     * @return bool
     */
    public function cluster_config_update($data)
    {
        $cluster_id = (int) $this->di['array_get']($data, 'cluster_id', 1);
        $this->getService()->updateMasterConfig($cluster_id, $data);
        $this->di['logger']->info('Updated SolusVM API configuration');
        return true;
    }

    /**
     * Return master server configuration
     * @param int $cluster_id - id of master server default = 1
     * @return array
     */
    public function cluster_config($data)
    {
        $cluster_id = (int) $this->di['array_get']($data, 'cluster_id', 1);
        return $this->getService()->getMasterConfig($cluster_id);
    }
    
    /**
     * Return virtualization types solusvm supports
     * @return array 
     */
    public function get_virtualization_types($data)
    {
        return $this->getService()->getVirtualizationTypes($data);
    }
    
    /**
     * Return nodes available on solusvm master server
     * @param string $by - list nodes by id or by name, default - name
     * @param string $type - virtualization type
     * @return array 
     */
    public function get_nodes($data)
    {
        $by = $this->di['array_get']($data, 'by', 'name');
        try {
            $type = $this->di['array_get']($data, 'type', 'openvz');
            $nodes = $this->getService()->getNodes($type, $by);
        } catch (\Exception $exc) {
            $nodes = array();
            if(BB_DEBUG) error_log($exc);
        }
        return $nodes;
    }
    
    /**
     * Return plans available on solusvm master server
     * @param string $type - virtualization type
     * @return array 
     */
    public function get_plans($data)
    {
        try {
            $type = $this->di['array_get']($data, 'type', 'openvz');
            $plans = $this->getService()->getPlans($type);
        } catch (\Exception $exc) {
            $plans = array();
            if(BB_DEBUG) error_log($exc);
        }
        return $plans;
    }
    
    /**
     * Return templates available on solusvm master server
     * @param string $type - virtualization type
     * @return array 
     */
    public function get_templates($data)
    {
        try {
            $type = $this->di['array_get']($data, 'type', 'openvz');
            $templates = $this->getService()->getTemplates($type);
        } catch (\Exception $exc) {
            $templates = array();
            if(BB_DEBUG) error_log($exc);
        }
        return $templates;
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
     * @return disabled|online|offline
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
     * Change VPS plan
     * @param int $order_id - order id
     * @param string $plan - new plan name
     * @return bool 
     */
    public function set_plan($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->set_plan($order, $vps, $data);
        $this->di['logger']->info('Changed VPS plan. Order ID #%s', $order->id);
        return true;
    }
    
    /**
     * Change VPS hostname
     * @param int $order_id - order id
     * @param string $hostname - new hostname for vps
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
     * Rebuild vps operating system with new template
     * @param int $order_id - order id
     * @param string $template - new template
     * @return bool 
     */
    public function rebuild($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->rebuild($order, $vps, $data);
        $this->di['logger']->info('Changed VPS template. Order ID #%s', $order->id);
        return true;
    }
    
    /**
     * Assign new IP from the pool
     * @param int $order_id - order id
     * @return bool 
     */
    public function addip($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->addip($order, $vps, $data);
        $this->di['logger']->info('Added IP for VPS. Order ID #%s', $order->id);
        return true;
    }
    
    /**
     * Disable network
     * @param int $order_id - order id
     * @return bool 
     */
    public function network_disable($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->network_disable($order, $vps, $data);
        $this->di['logger']->info('Disabled VPS network. Order ID #%s', $order->id);
        return true;
    }
    
    /**
     * Enable network
     * @param int $order_id - order id
     * @return bool 
     */
    public function network_enable($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->network_enable($order, $vps, $data);
        $this->di['logger']->info('Enabled VPS network. Order ID #%s', $order->id);
        return true;
    }
    
    /**
     * Disable tun
     * @param int $order_id - order id
     * @return bool 
     */
    public function tun_disable($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->tun_disable($order, $vps, $data);
        $this->di['logger']->info('Disabled VPS TUN. Order ID #%s', $order->id);
        return true;
    }
    
    /**
     * Enable tun
     * @param int $order_id - order id
     * @return bool 
     */
    public function tun_enable($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->tun_enable($order, $vps, $data);
        $this->di['logger']->info('Enabled VPS TUN. Order ID #%s', $order->id);
        return true;
    }
    
    /**
     * Disable PAE
     * @param int $order_id - order id
     * @return bool 
     */
    public function pae_enable($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->pae_enable($order, $vps, $data);
        $this->di['logger']->info('Enabled VPS PAE. Order ID #%s', $order->id);
        return true;
    }
    
    /**
     * Enable PAE
     * @param int $order_id - order id
     * @return bool 
     */
    public function pae_disable($data)
    {
        list($order, $vps) = $this->_getService($data);
        $this->getService()->pae_disable($order, $vps, $data);
        $this->di['logger']->info('Disabled VPS PAE. Order ID #%s', $order->id);
        return true;
    }
    
    /**
     * List clients on SolusVM server
     * @param bool $skip - skip imported clients, default - false
     * @return array
     */
    public function client_list($data)
    {
        $skip = (bool) $this->di['array_get']($data, 'skip', false);
        $clients = $this->getService()->client_list();
        
        if($skip) {
            //skip imported clients
            foreach($clients as $key=>$client) {
                if($this->di['db']->findOne('client', 'aid = :aid', array('aid'=>$client['id']))) {
                    unset($clients[$key]);
                }
            }
        }
        
        return $clients;
    }
    
    /**
     * List virtual server on SolusVM server
     * @param bool $node_id - node id to list virtul servers
     * @param bool $skip - skip imported servers, default - false
     * @return array
     */
    public function node_virtualservers($data)
    {
        $skip = (bool) $this->di['array_get']($data, 'skip', false);
        $node_id = $this->di['array_get']($data, 'node_id', 1);
        $servers = $this->getService()->node_virtualservers($node_id);
        
        if($skip) {
            //skip imported servers
            foreach($servers as $key=>$s) {
                if($this->di['db']->findOne('service_solusvm', 'vserverid = :vserverid', array('vserverid'=>$s['vserverid']))) {
                    unset($servers[$key]);
                }
            }
        }
        
        return $servers;
    }
    
    /**
     * Import selected servers to BoxBilling
     * @return string - log information regarding import process
     */
    public function import_servers($data)
    {
        $nodeid = $this->di['array_get']($data, 'node_id', null);
        $period = $this->di['array_get']($data, 'period', null);
        $product_id = $this->di['array_get']($data, 'product_id', null);
        $selected = $this->di['array_get']($data, 'servers', array());
        if(empty($nodeid)) {
            throw new \Box_Exception('Node is not selected for import.', null, 235);
        }
        
        if(empty($selected)) {
            throw new \Box_Exception('No servers selected for import.', null, 234);
        }

        if(empty($product_id)) {
            throw new \Box_Exception('Select product for orders.');
        }
        $product = $this->di['db']->load('product', $product_id);
        $pconfig = json_decode($product->config, 1);

        $required = array(
            'node'    => 'Product is not configured completely. Please provide solusvm node in product configuration page',
            'plan'    => 'Product is not configured completely. Please provide solusvm plan in product configuration page',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $log = '';
        $servers = $this->node_virtualservers($data);
        foreach ($servers as $server) {
            if(!in_array($server['vserverid'], $selected)) {
                continue;
            }
            
            try {
                $ss = $this->di['db']->findOne('service_solusvm', 'vserverid = :vserverid', array('vserverid'=>$server['vserverid']));
                if($ss) {
                    throw new \Exception(sprintf('Server is already imported'));
                }
                
                $client = $this->di['db']->findOne('client', 'aid = :aid', array('aid'=>$server['clientid']));
                if(!$client) {
                    throw new \Exception(sprintf('Client with alternative id %s was not found', $server['clientid']));
                }
                
                list($username, ) = $this->getService()->getSolusUserPassword($client);
                
                $odata = array(
                    'client_id'     => $client->id,
                    'product_id'    => $product_id,
                    'period'        => $period,
                    'activate'      => false,
                    'invoice_option'=> 'no-invoice',
                    'config'=> array(
                        'hostname'  =>  $server['hostname'],
                        'template'  =>  $server['template'],
                    ),
                );
                $id = $this->di['api_admin']->order_create($odata);
                
                // create service
                $model = $this->di['db']->dispense('service_solusvm');
                $model->cluster_id = 1; //for future if ever BoxBilling supports multiple master servers
                $model->client_id    = $client->id;
                $model->hostname     = $server['hostname'];
                $model->template     = $server['template'];
                $model->vserverid = $server['vserverid'];
                $model->virtid = $server['ctid-xid'];
                $model->nodeid = $nodeid;
                $model->type = $server['type'];
                $model->plan = $pconfig['plan'];
                $model->node = $pconfig['node'];
                $model->nodegroup = null;
                $model->rootpassword = null;
                $model->username = $username;
                $model->consoleuser = null;
                $model->consolepassword = null;
                $model->mainipaddress = $server['ipaddress'];
                $model->created_at   = date('Y-m-d H:i:s');
                $model->updated_at   = date('Y-m-d H:i:s');
                $this->di['db']->store($model);
                
                //activate order
                $o = $this->di['db']->load('client_order', $id);
                $o->service_id = $model->id;
                $o->status = 'active';
                $this->di['db']->store($o);        
                
                $log .= sprintf('Created order #%s for server #%s', $id, $server['vserverid']) . PHP_EOL;
            } catch(\Exception $e) {
                $log .= sprintf('Order for server #%s was not imported due to error "%s"', $server['vserverid'], $e->getMessage()).PHP_EOL;
            }
            
        }
        
        $this->di['logger']->info('Imported VPS from SolusVM to BoxBilling');
        return $log;
    }
    
    /**
     * Import selected clients to BoxBilling
     * @return string - log information regarding import process
     */
    public function import_clients($data)
    {
        $selected = $this->di['array_get']($data, 'clients', array());
        if(empty($selected)) {
            throw new \Box_Exception('No clients selected for import.', null, 233);
        }
        
        $clients = $this->client_list($data);
        if(empty($clients)) {
            throw new \Box_Exception('No clients found on SolusVM server.');
        }
        
        $log = '';
        foreach($clients as $client) {
            if(!in_array($client['id'], $selected)) {
                continue;
            }
            $password = substr(md5(random_bytes(13)), 0, 8);
            $cdata = array(
                'aid'           => $client['id'],
                'email'         => $client['email'],
                'company'       => $client['company'],
                'first_name'    => $client['firstname'],
                'last_name'     => $client['lastname'],
                'password'      => $password,
                'notes'         => 'Imported from SolusVM server',
                'created_at'    => date('Y-m-d H:i:s', strtotime($client['created'])),
            );
            try {
                $id = $this->di['api_admin']->client_create($cdata);
                $c = $this->di['db']->load('client', $id);
                $this->getService()->setSolusUserPassword($c, $client['username'], $password);
                $log .= sprintf('Imported client #%s', $client['id']) . PHP_EOL;
            } catch(\Exception $e) {
                $log .= sprintf('Client #%s was not imported due to error "%s"', $client['id'], $e->getMessage()).PHP_EOL;
            }
        }
        
        $this->di['logger']->info('Imported clients from SolusVM to BoxBilling');
        return $log;
    }
    
    /**
     * Test connection to master server
     * @param int $order_id - order id
     * @optional string $return - if value = bool - does not return error but returns bool value
     * @return bool 
     */
    public function test_connection($data)
    {
        $return = $this->di['array_get']($data, 'return', null);
        $can_connect = false;
        try {
            $this->getService()->testConnection($data);
            $can_connect = true;
        } catch (\Exception $exc) {
            if($return != 'bool') {
                throw $exc;
            }
        }
        return $can_connect;
    }
    
    /**
     * Update existing order service
     * This method used to change clients data if order setup fails
     * or you have changed data on solusVM server and you need to sync with
     * BoxBilling database
     * @return boolean 
     */
    public function update($data)
    {
        list(, $vps) = $this->_getService($data);

        $vps->plan            = $this->di['array_get']($data, 'plan', $vps->plan);
        $vps->template        = $this->di['array_get']($data, 'template', $vps->template);
        $vps->hostname        = $this->di['array_get']($data, 'hostname', $vps->hostname);
        $vps->mainipaddress   = $this->di['array_get']($data, 'mainipaddress', $vps->mainipaddress);
        $vps->custommemory    = $this->di['array_get']($data, 'custommemory', $vps->custommemory);
        $vps->customdiskspace = $this->di['array_get']($data, 'customdiskspace', $vps->customdiskspace);
        $vps->custombandwidth = $this->di['array_get']($data, 'custombandwidth', $vps->custombandwidth);
        $vps->customcpu       = $this->di['array_get']($data, 'customcpu', $vps->customcpu);
        $this->di['db']->store($vps);
        
        $this->di['logger']->info('Updated VPS service %s details', $vps->id);
        return true;
    }
}