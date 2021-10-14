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

namespace Box\Mod\Servicehosting\Api;
/**
 * Hosting service management 
 */
class Admin extends \Api_Abstract
{
    /**
     * Change hosting account plan
     * 
     * @param int $order_id - Hosting account order id
     * @param int $plan_id - New hosting plan id
     * 
     * @return boolean 
     */
    public function change_plan($data)
    {
        if(!isset($data['plan_id'])) {
            throw new \Box_Exception('plan_id is missing');
        }

        list($order, $s) = $this->_getService($data);
        $plan = $this->di['db']->getExistingModelById('ServiceHostingHp', $data['plan_id'], 'Hosting plan not found');
        
        $service = $this->getService();
        return (bool) $service->changeAccountPlan($order, $s, $plan);
    }
    
    /**
     * Change hosting account username
     * 
     * @param int $order_id - Hosting account order id
     * @param string $username - New username
     * 
     * @return boolean 
     */
    public function change_username($data)
    {
        list($order, $s) = $this->_getService($data);
        $service = $this->getService();
        return (bool) $service->changeAccountUsername($order, $s, $data);
    }
    
    /**
     * Change hosting account ip
     * 
     * @param int $order_id - Hosting account order id
     * @param string $username - New username
     * 
     * @return boolean 
     */
    public function change_ip($data)
    {
        list($order, $s) = $this->_getService($data);
        $service = $this->getService();
        return (bool) $service->changeAccountIp($order, $s, $data);
    }
    
    /**
     * Change hosting account domain
     * 
     * @param int $order_id - Hosting account order id
     * @param string $tld - Top level domain value, ie: .com
     * @param string $sld - Second level domain value, ie: domainname
     * 
     * @return boolean 
     */
    public function change_domain($data)
    {
        list($order, $s) = $this->_getService($data);
        $service = $this->getService();
        return (bool) $service->changeAccountDomain($order, $s, $data);
    }
    
    /**
     * Change hosting account password.
     * 
     * @param int $order_id - Hosting account order id
     * @param string $password - New account password
     * @param string $password_confirm - Must be same value as password field
     * 
     * @return boolean 
     */
    public function change_password($data)
    {
        list($order, $s) = $this->_getService($data);
        $service = $this->getService();
        return (bool) $service->changeAccountPassword($order, $s, $data);
    }
    
    /**
     * Synchronize account with server values.
     * 
     * @param int $order_id - Hosting account order id
     * 
     * @return boolean 
     */
    public function sync($data)
    {
        list($order, $s) = $this->_getService($data);
        $service = $this->getService();
        return (bool) $service->sync($order, $s);
    }
    
    /**
     * Update account information on BoxBilling database.
     * This does not send actions to real account on hosting server.
     * 
     * @param int $order_id - Hosting account order id
     * 
     * @optional string $username - Hosting account username
     * @optional string $ip - Hosting account ip
     * 
     * @return boolean 
     */
    public function update($data)
    {
        list(, $s) = $this->_getService($data);
        $service = $this->getService();
        return (bool) $service->update($s, $data);
    }

    /**
     * Get list of available server managers on system
     * 
     * @return array 
     */
    public function manager_get_pairs($data)
    {
        return $this->getService()->getServerManagers();
    }

    /**
     * Get list of available hosting servers on system
     * 
     * @return array
     */
    public function server_get_pairs($data)
    {
        return $this->getService()->getServerPairs();
    }

    /**
     * Get paginated list of servers
     * 
     * @return array
     */
    public function server_get_list($data)
    {
        $servers = $this->di['db']->find('ServiceHostingServer', 'ORDER BY id ASC');
        $serversArr = array();
        foreach ($servers as $server) {
            $serversArr[] = $this->getService()->toHostingServerApiArray($server, false, $this->getIdentity());
        }

        list($sql, $params) = $this->getService()->getServersSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $result = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

        $result['list'] = $serversArr;

        return $result;
    }

    /**
     * Create new hosting server 
     * 
     * @param string $name - server name
     * @param string $ip - server ip
     * @param string $manager - server manager code
     * 
     * @optional string $hostname - server hostname
     * @optional string $ns1 - default nameserver 1
     * @optional string $ns2 - default nameserver 2
     * @optional string $ns3 - default nameserver 3
     * @optional string $ns4 - default nameserver 4
     * @optional string $username - server API login username
     * @optional string $password - server API login password
     * @optional string $accesshash - server API login access hash 
     * @optional string $port - server API port
     * @optional bool $secure - flag to define whether to use secure connection (https) to server or not (http)
     * @optional bool $active - flag to enable/disable server
     * 
     * @return int - server id 
     * @throws \Box_Exception 
     */
    public function server_create($data)
    {
        $required = array(
            'name'    => 'Server name is missing',
            'ip'      => 'Server IP is missing',
            'manager' => 'Server manager is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        return (int) $service->createServer($data['name'], $data['ip'], $data['manager'], $data);
    }

    /**
     * Get server details
     * 
     * @param int $id - server id
     * @return array
     * 
     * @throws \Box_Exception 
     */
    public function server_get($data)
    {
        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingServer', $data['id'], 'Server not found');
        $service = $this->getService();
        return $service->toHostingServerApiArray($model, true, $this->getIdentity());
    }

    /**
     * Delete server
     * 
     * @param int $id - server id
     * @return boolean
     * @throws \Box_Exception 
     */
    public function server_delete($data)
    {
        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingServer', $data['id'], 'Server not found');
        return (bool) $this->getService()->deleteServer($model);
    }

    /**
     * Update server configuration
     * 
     * @param int $id - server id
     * 
     * @optional string $hostname - server hostname
     * @optional string $ns1 - default nameserver 1
     * @optional string $ns2 - default nameserver 2
     * @optional string $ns3 - default nameserver 3
     * @optional string $ns4 - default nameserver 4
     * @optional string $username - server API login username
     * @optional string $password - server API login password
     * @optional string $accesshash - server API login access hash 
     * @optional string $port - server API port
     * @optional bool $secure - flag to define whether to use secure connection (https) to server or not (http)
     * @optional bool $active - flag to enable/disable server
     * 
     * @return boolean
     * @throws \Box_Exception 
     */
    public function server_update($data)
    {
        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingServer', $data['id'], 'Server not found');
        $service = $this->getService();
        return (bool) $service->updateServer($model, $data);
    }

    /**
     * Test connection to server
     * 
     * @param int $id - server id
     * 
     * @return bool
     * @throws \Box_Exception 
     */
    public function server_test_connection($data)
    {
        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingServer', $data['id'], 'Server not found');

        return (bool) $this->getService()->testConnection($model);
    }

    /**
     * Get hoting plan pairs
     * 
     * @return array
     */
    public function hp_get_pairs($data)
    {
        return $this->getService()->getHpPairs();
    }

    /**
     * Get hostin plans paginated list
     * 
     * @return array
     */
    public function hp_get_list($data)
    {
        list ($sql, $params) = $this->getService()->getHpSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $model               = $this->di['db']->getExistingModelById('ServiceHostingHp', $item['id'], 'Post not found');
            $pager['list'][$key] = $this->getService()->toHostingHpApiArray($model, false, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Delete hosting plan
     * 
     * @param int $id - hosting plan id
     * 
     * @return boolean
     * @throws \Box_Exception 
     */
    public function hp_delete($data)
    {
        $required = array(
            'id'    => 'Hosting plan ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingHp', $data['id'], 'Hosting plan not found');

        return (bool) $this->getService()->deleteHp($model);
    }

    /**
     * Get hosting plan details
     * 
     * @param int $id - hosting plan id
     * 
     * @return array
     * @throws \Box_Exception 
     */
    public function hp_get($data)
    {
        $required = array(
            'id'    => 'Hosting plan ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingHp', $data['id'], 'Hosting plan not found');

        return $this->getService()->toHostingHpApiArray($model, true, $this->getIdentity());
    }

    /**
     * Update hosting plan details
     * 
     * @param int $id - hosting plan id
     * 
     * @optional string $name - hosting plan name. Used as identifier on server
     * 
     * @return bool
     * @throws \Box_Exception 
     */
    public function hp_update($data)
    {
        $required = array(
            'id'    => 'Hosting plan ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingHp', $data['id'], 'Hosting plan not found');

        $service = $this->getService();
        return (bool) $service->updateHp($model, $data);
    }

    /**
     * Update hosting plan details
     * 
     * @param string $name - hosting plan name. Used as identifier on server
     * 
     * @return int - new hosting plan id
     * @throws \Box_Exception 
     */
    public function hp_create($data)
    {
        $required = array(
            'name'    => 'Hosting plan name is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        return (int) $service->createHp($data['name'], $data);
    }

    public function _getService($data)
    {
        $required = array(
            'order_id'    => 'Order ID name is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
        $orderSerivce = $this->di['mod_service']('order');
        $s = $orderSerivce->getOrderService($order);
        if(!$s instanceof \Model_ServiceHosting) {
            throw new \Box_Exception('Order is not activated');
        }
        return array($order, $s);
    }
}