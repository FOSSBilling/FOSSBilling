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

namespace Box\Mod\Servicesolusvm;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function install()
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS `service_solusvm` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `cluster_id` bigint(20) DEFAULT NULL,
            `client_id` bigint(20) DEFAULT NULL,
            `vserverid` varchar(255) DEFAULT NULL,
            `virtid` varchar(255) DEFAULT NULL,
            `nodeid` varchar(255) DEFAULT NULL,
            `type` varchar(255) DEFAULT NULL,
            `node` varchar(255) DEFAULT NULL,
            `nodegroup` varchar(255) DEFAULT NULL,
            `hostname` varchar(255) DEFAULT NULL,
            `rootpassword` varchar(255) DEFAULT NULL,
            `username` varchar(255) DEFAULT NULL,
            `plan` varchar(255) DEFAULT NULL,
            `template` varchar(255) DEFAULT NULL,
            `ips` varchar(255) DEFAULT NULL,
            `hvmt` varchar(255) DEFAULT NULL,
            `custommemory` varchar(255) DEFAULT NULL,
            `customdiskspace` varchar(255) DEFAULT NULL,
            `custombandwidth` varchar(255) DEFAULT NULL,
            `customcpu` varchar(255) DEFAULT NULL,
            `customextraip` varchar(255) DEFAULT NULL,
            `issuelicense` varchar(255) DEFAULT NULL,
            `mainipaddress` varchar(255) DEFAULT NULL,
            `extraipaddress` varchar(255) DEFAULT NULL,
            `consoleuser` varchar(255) DEFAULT NULL,
            `consolepassword` varchar(255) DEFAULT NULL,
            `config` text,
            `created_at` varchar(35) DEFAULT NULL,
            `updated_at` varchar(35) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `client_id_idx` (`client_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
        $this->di['db']->exec($sql);
    }

    public function uninstall()
    {
        $this->di['db']->exec("DROP TABLE IF EXISTS `service_solusvm`");
    }

    public function getCartProductTitle($product, array $data)
    {
        return __('Virtual private server :title',
                  array(
                      ':title'    => $product->title,
                      ':template' => $data['template'],
                      ':hostname' => $data['hostname']));
    }

    public function validateOrderData(array $data)
    {
        if (!isset($data['hostname']) || empty($data['hostname'])) {
            throw new \Box_Exception('Please enter VPS hostname.', null, 7101);
        }

        $ValidHostnameRegex = "/^(([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9])$/";
        if (!preg_match($ValidHostnameRegex, $data['hostname'])) {
            throw new \Box_Exception('Hostname :hostname is not valid.', array(':hostname' => $data['hostname']), 7102);
        }

        if (!isset($data['template']) || empty($data['template'])) {
            throw new \Box_Exception('Please select VPS template.', null, 7103);
        }
    }

    public function updateMasterConfig($cluster_id, $data)
    {
        if (!isset($data['id'])) {
            throw new \Box_Exception('SolusVM API ID is missing.', null, 7201);
        }

        if (!isset($data['key'])) {
            throw new \Box_Exception('SolusVM API KEY is missing.', null, 7202);
        }

        if (!isset($data['ipaddress'])) {
            throw new \Box_Exception('SolusVM API ipaddress is missing.', null, 7203);
        }

        if (!isset($data['secure'])) {
            throw new \Box_Exception('SolusVM API secure is missing.', null, 7204);
        }

        if (!isset($data['port'])) {
            throw new \Box_Exception('SolusVM API port is missing.', null, 7204);
        }

        $sql = "
            UPDATE extension_meta 
            SET meta_value = :config
            WHERE rel_type = 'cluster'
            AND extension = 'mod_servicesolusvm' 
            AND rel_id = :cluster_id
            AND meta_key = 'config'
        ";

        $c      = array(
            'id'        => $data['id'],
            'key'       => $data['key'],
            'ipaddress' => $data['ipaddress'],
            'secure'    => $data['secure'],
            'port'      => $data['port'],
            'usertype'  => 'admin',
        );
        $params = array(
            'config'     => json_encode($c),
            'cluster_id' => $cluster_id,
        );
        $this->di['db']->exec($sql, $params);
    }

    /**
     * @param integer $cluster_id
     */
    public function getMasterConfig($cluster_id)
    {
        $sql    = "
            SELECT meta_value 
            FROM extension_meta 
            WHERE extension = 'mod_servicesolusvm' 
            AND rel_type = 'cluster'
            AND rel_id = :cluster_id
            AND meta_key = 'config'
        ";
        $config = $this->di['db']->getCell($sql, array('cluster_id' => $cluster_id));
        if (!$config) {
            $config           = array();
            $meta             = $this->di['db']->dispense('extension_meta');
            $meta->extension  = 'mod_servicesolusvm';
            $meta->rel_type   = 'cluster';
            $meta->rel_id     = $cluster_id;
            $meta->meta_key   = 'config';
            $meta->meta_value = json_encode($config);
            $meta->created_at = date('Y-m-d H:i:s');
            $meta->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($meta);
        } else {
            $config = json_decode($config, 1);
        }
        if (!isset($config['secure'])) {
            $config['secure'] = null;
        }
        if (!isset($config['port'])) {
            $config['port'] = null;
        }

        return $config;
    }

    public function setSolusUserPassword($client, $username, $password)
    {
        $meta             = $this->di['db']->dispense('extension_meta');
        $meta->extension  = 'mod_servicesolusvm';
        $meta->client_id  = $client->id;
        $meta->meta_key   = 'solusvm_username';
        $meta->meta_value = $username;
        $meta->created_at = date('Y-m-d H:i:s');
        $meta->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($meta);

        $meta             = $this->di['db']->dispense('extension_meta');
        $meta->extension  = 'mod_servicesolusvm';
        $meta->client_id  = $client->id;
        $meta->meta_key   = 'solusvm_password';
        $meta->meta_value = $password;
        $meta->created_at = date('Y-m-d H:i:s');
        $meta->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($meta);
    }

    public function getSolusUserPassword($client)
    {
        $sql      = "
            SELECT meta_value 
            FROM extension_meta 
            WHERE client_id = :cid 
            AND extension = 'mod_servicesolusvm' 
            AND meta_key = :key
        ";
        $username = $this->di['db']->getCell($sql, array('cid' => $client->id, 'key' => 'solusvm_username'));
        $password = $this->di['db']->getCell($sql, array('cid' => $client->id, 'key' => 'solusvm_password'));

        if (!$username) {
            $username = $client->email;
            $password = $this->di['tools']->generatePassword(8, 2);
            $this->setSolusUserPassword($client, $username, $password);
        }

        return array($username, $password);
    }

    /**
     * @param $order
     * @return void
     */
    public function create($order)
    {
        $c = json_decode($order->config, 1);
        $this->validateOrderData($c);
        $model             = $this->di['db']->dispense('service_solusvm');
        $model->client_id  = $order->client_id;
        $model->hostname   = strtolower($c['hostname']);
        $model->template   = $c['template'];
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return $model;
    }

    /**
     * @param $order
     * @return void
     */
    public function activate($order, $model)
    {
        if (!is_object($model)) {
            throw new \Box_Exception('Could not activate order. Service was not created', null, 7456);
        }

        $client  = $this->di['db']->load('client', $order->client_id);
        $product = $this->di['db']->load('product', $order->product_id);
        if (!$product) {
            throw new \Box_Exception('Could not activate order because ordered product does not exists', null, 7457);
        }

        $pconfig = json_decode($product->config, 1);

        list($username, $password) = $this->getSolusUserPassword($client);

        try {
            $this->_getApi()->client_checkexists($username);
        } catch (\Exception $exc) {
            $this->_getApi()->client_create($username, $password, $client->email, $client->first_name, $client->last_name, $client->company);
        }

        if (!isset($pconfig['node'])) {
            throw new \Box_Exception('Could not activate order. Product :id configuration is missing node parameter', array(':id' => $product->id), 7458);
        }

        if (!isset($pconfig['plan'])) {
            throw new \Box_Exception('Could not activate order. Product :id configuration is missing plan parameter', array(':id' => $product->id), 7459);
        }

        if (!isset($pconfig['vtype'])) {
            throw new \Box_Exception('Could not activate order. Product :id configuration is missing virtualization type parameter', array(':id' => $product->id), 7460);
        }

        if (!isset($pconfig['ips'])) {
            throw new \Box_Exception('Could not activate order. Product :id configuration is missing ips amount parameter', array(':id' => $product->id), 7461);
        }

        $type      = $pconfig['vtype'];
        $node      = $pconfig['node'];
        $nodegroup = $this->di['array_get']($pconfig, 'nodegroup', null);

        $template = $model->template;
        $hostname = $model->hostname;
        $plan     = $pconfig['plan'];
        $ips      = $pconfig['ips'];

        $hvmt            = $this->di['array_get']($pconfig, 'hvmt', null);
        $custommemory    = $this->di['array_get']($pconfig, 'custommemory', null);
        $customdiskspace = $this->di['array_get']($pconfig, 'customdiskspace', null);
        $custombandwidth = $this->di['array_get']($pconfig, 'custombandwidth', null);
        $customcpu       = $this->di['array_get']($pconfig, 'customcpu', null);
        $customextraip   = $this->di['array_get']($pconfig, 'customextraip', null);
        $issuelicense    = $this->di['array_get']($pconfig, 'issuelicense', null);

        $result = $this->_getApi()->vserver_create($type, $node, $nodegroup, $hostname, $password, $username, $plan, $template, $ips, $hvmt, $custommemory, $customdiskspace, $custombandwidth, $customcpu, $customextraip, $issuelicense);

        $model->cluster_id      = 1; //for future if ever BoxBilling supports multiple master servers
        $model->vserverid       = $result['vserverid'];
        $model->virtid          = $result['virtid'];
        $model->nodeid          = $result['nodeid'];
        $model->type            = $type;
        $model->plan            = $plan;
        $model->node            = $node;
        $model->nodegroup       = $nodegroup;
        $model->hostname        = $result['hostname'];
        $model->rootpassword    = ''; //$result['rootpassword'];
        $model->username        = $username;
        $model->ips             = $ips;
        $model->hvmt            = $hvmt;
        $model->consoleuser     = $result['consoleuser'];
        $model->consolepassword = ''; //$result['consolepassword'];
        $model->custommemory    = $custommemory;
        $model->customdiskspace = $customdiskspace;
        $model->custombandwidth = $custombandwidth;
        $model->customcpu       = $customcpu;
        $model->customextraip   = $customextraip;
        $model->issuelicense    = $issuelicense;
        $model->mainipaddress   = $result['mainipaddress'];
        $model->extraipaddress  = $this->di['array_get']($result, 'extraipaddress', null);
        $model->updated_at      = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        //pass params to event hook
        return array(
            'rootpassword'    => $result['rootpassword'],
            'consolepassword' => $result['consolepassword'],
        );
    }

    /**
     * Suspend VPS
     *
     * @param $order
     * @return boolean
     */
    public function suspend($order, $model)
    {
        $this->_getApi()->vserver_suspend($model->vserverid);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    /**
     * @param $order
     * @return boolean
     */
    public function unsuspend($order, $model)
    {
        $this->_getApi()->vserver_unsuspend($model->vserverid);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    /**
     * @param $order
     * @return boolean
     */
    public function cancel($order, $model)
    {
        return $this->suspend($order, $model);
    }

    /**
     *
     * @param $order
     * @return boolean
     */
    public function uncancel($order, $model)
    {
        return $this->unsuspend($order, $model);
    }

    /**
     * @param $order
     * @return boolean
     */
    public function delete($order, $model)
    {
        if (is_object($model)) {
            $this->_getApi()->vserver_terminate($model->vserverid);
            $this->di['db']->trash($model);
        }

        return true;
    }

    public function reboot($order, $model)
    {
        $this->_getApi()->vserver_reboot($model->vserverid);

        return true;
    }

    public function boot($order, $model)
    {
        $this->_getApi()->vserver_boot($model->vserverid);

        return true;
    }

    public function shutdown($order, $model)
    {
        $this->_getApi()->vserver_shutdown($model->vserverid);

        return true;
    }

    public function status($order, $model)
    {
        $result = $this->_getApi()->vserver_status($model->vserverid);

        return $result['statusmsg'];
    }

    public function info($vserverid)
    {
        $result = $this->_getApi()->vserver_infoall($vserverid);

        return $result;
    }

    public function set_root_password($order, $model, $params = array())
    {
        if (!isset($params['password']) || strlen($params['password']) < 4) {
            throw new \Box_Exception('Root password must be longer than 4 symbols', null, 8789);
        }
        $pass = $params['password'];
        $this->_getApi()->vserver_rootpassword($model->vserverid, $pass);

        $model->rootpassword = ''; //$pass;
        $this->di['db']->store($model);

        return true;
    }

    public function set_plan($order, $model, $params = array())
    {
        if (!isset($params['plan']) || empty($params['plan'])) {
            throw new \Box_Exception('Plan name must not be empty', null, 8790);
        }
        $this->_getApi()->vserver_change($model->vserverid, $params['plan']);
        $model->plan = $params['plan'];
        $this->di['db']->store($model);

        return true;
    }

    public function set_hostname($order, $model, $params = array())
    {
        if (!isset($params['hostname']) || empty($params['hostname'])) {
            throw new \Box_Exception('Hostname must not be empty', null, 8791);
        }
        $this->_getApi()->vserver_hostname($model->vserverid, $params['hostname']);
        $model->hostname = $params['hostname'];
        $this->di['db']->store($model);

        return true;
    }

    public function rebuild($order, $model, $params = array())
    {
        if (!isset($params['template']) || empty($params['template'])) {
            throw new \Box_Exception('Template must not be empty', null, 8792);
        }
        $this->_getApi()->vserver_rebuild($model->vserverid, $params['template']);
        $model->template = $params['template'];
        $this->di['db']->store($model);

        return true;
    }

    public function addip($order, $model, $params = array())
    {
        $this->_getApi()->vserver_addip($model->vserverid);

        return true;
    }

    public function network_disable($order, $model, $params = array())
    {
        return $this->_getApi()->vserver_network_disable($model->vserverid);
    }

    public function network_enable($order, $model, $params = array())
    {
        return $this->_getApi()->vserver_network_enable($model->vserverid);
    }

    public function tun_disable($order, $model, $params = array())
    {
        return $this->_getApi()->vserver_tun_disable($model->vserverid);
    }

    public function tun_enable($order, $model, $params = array())
    {
        return $this->_getApi()->vserver_tun_enable($model->vserverid);
    }

    public function pae_disable($order, $model, $params = array())
    {
        return $this->_getApi()->vserver_pae($model->vserverid, 'off');
    }

    public function pae_enable($order, $model, $params = array())
    {
        return $this->_getApi()->vserver_pae($model->vserverid, 'on');
    }

    public function client_list()
    {
        return $this->_getApi()->client_list();
    }

    public function node_virtualservers($nodeid)
    {
        return $this->_getApi()->node_virtualservers($nodeid);
    }

    public function client_change_password($order, $model, $params = array())
    {
        if (!isset($params['password']) || strlen($params['password']) < 4) {
            throw new \Box_Exception('Password must be longer than 4 symbols', null, 8790);
        }
        $this->_getApi()->client_updatepassword($model->username, $params['password']);

        $sql = "
            UPDATE extension_meta 
            SET meta_value = :pass
            WHERE client_id = :cid 
            AND extension = 'mod_servicesolusvm' 
            AND meta_key = :key
        ";
        $this->di['db']->exec($sql, array('cid' => $model->client_id, 'key' => 'solusvm_password', 'pass' => $params['password']));

        return true;
    }

    public function getVirtualizationTypes($data)
    {
        return array(
            "openvz"  => 'OpenVz',
            "xen"     => 'Xen',
            "xen hvm" => 'Xen HVM',
            "kvm"     => 'KVM',
        );
    }

    public function getNodes($type, $by)
    {
        if ($by == 'id') {
            $result = $this->_getApi()->node_idlist($type);
        } else {
            $result = $this->_getApi()->listnodes($type);
        }

        $list = explode(',', $result['nodes']);
        $res  = array();
        foreach ($list as $p) {
            $res[$p] = $p;
        }

        return $res;
    }

    public function getTemplates($type)
    {
        $templates = $this->_getApi()->listtemplates($type);
        $list = explode(',', $templates['templates']);
        if($type == 'kvm' && isset($templates['templateskvm'])) {
            $list = explode(',', $templates['templateskvm']);
        }
        if($type == 'xen hvm' && isset($templates['templateshvm'])) {
            $list = explode(',', $templates['templateshvm']);
        }

        $res = array();
        foreach ($list as $p) {
            $res[$p] = $p;
        }

        return $res;
    }

    public function getPlans($type = 'openvz')
    {
        $plans = $this->_getApi()->listplans($type);
        $list  = explode(',', $plans['plans']);
        $res   = array();
        foreach ($list as $p) {
            $res[$p] = $p;
        }

        return $res;
    }

    public function testConnection()
    {
        $this->_getApi()->listnodegroups();

        return true;
    }

    private function _getApi()
    {
        if (APPLICATION_ENV == 'testing') {
            return new \Vps_SolusvmMock($this->getMasterConfig(1));
        }

        return $this->di['solusvm']($this->getMasterConfig(1));
    }

    public function toApiArray($model, $deep = false, $identity = null)
    {
        $c = $this->getMasterConfig(1);
        if ($c['secure']) {
            if ($c["port"]) {
                $cport = $c["port"];
            } else {
                $cport = "5656";
            }
            $url = "https://" . $c['ipaddress'] . ":" . $cport;
        } else {
            if ($c["port"]) {
                $cport = $c["port"];
            } else {
                $cport = "5353";
            }
            $url = "http://" . $c['ipaddress'] . ":" . $cport;
        }

        $client = $this->di['db']->load('client', $model->client_id);
        list($username, $password) = $this->getSolusUserPassword($client);

        return array(
            'id'              => $model->id,
            'vserverid'       => $model->vserverid,
            'virtid'          => $model->virtid,
            'hostname'        => $model->hostname,
            'plan'            => $model->plan,
            'template'        => $model->template,
            'mainipaddress'   => $model->mainipaddress,
            'rootpassword'    => $model->rootpassword,
            'consoleuser'     => $model->consoleuser,
            'consolepassword' => $model->consolepassword,

            'custommemory'    => $model->custommemory,
            'customdiskspace' => $model->customdiskspace,
            'custombandwidth' => $model->custombandwidth,
            'customcpu'       => $model->customcpu,

            'master_url'      => $url,
            'username'        => $username,
            'password'        => $password,
        );
    }
}

