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

namespace Box\Mod\Servicecentovacast;

class Service implements \Box\InjectionAwareInterface
{
    private $_salt = 'CksDmBH2tgBv1iKsRxcL';

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
        $sql="
        CREATE TABLE IF NOT EXISTS `service_centovacast` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `client_id` bigint(20) DEFAULT NULL,
        `server_id` int(20) DEFAULT NULL,
        `username` varchar(255) DEFAULT NULL,
        `pass` varchar(255) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        KEY `client_id_idx` (`client_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        $this->di['db']->exec($sql);
    }
    
    public function toApiArray($row)
    {
        if($row instanceof \RedBeanPHP\OODBBean) {
            $row = $row->export();
        }
        
        $row['pass'] = $this->di['crypt']->decrypt($row['pass'], $this->_salt);
        return $row;
    }
    
    public function getServers()
    {
        $sql="SELECT id, meta_value
            FROM extension_meta
            WHERE extension = 'mod_servicecentovacast'
            AND meta_key = 'server'
            ORDER BY id DESC
        ";
        $result = $this->di['db']->getAll($sql);
        $servers = array();
        foreach($result as $s) {
            $d = array();
            $d['id'] = $s['id'];
            $d = array_merge($d, json_decode($s['meta_value'], 1));
            $servers[$s['id']] = $d;
        }
        return $servers;
    }
    
    public function getServer($id)
    {
        $servers = $this->getServers();
        if(!isset($servers[$id])) {
            throw new \Exception('Centova Cast Server not found');
        }
        
        if(APPLICATION_ENV == 'testing') {
            $config = array();
            $config['url']      = 'http://castdemo.centova.com:2199';
            $config['secret']   = 'example';
            $config['ip']       = '123.123.123.123';
            $config['hostname'] = 'castdemo.centova.com';
            return $config;
        }
        
        return $servers[$id];
    }
    
    /**
     * @param $order
     * @return \RedBeanPHP\OODBBean
     */
    public function create($order)
    {
        $model = $this->di['db']->dispense('service_centovacast');
        $model->client_id    = $order->client_id;
        $model->created_at   = date('Y-m-d H:i:s');
        $model->updated_at   = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        return $model;
    }

    /**
     * @param $order
     * @return array
     * @see http://www.centova.com/docs/cast/centovacast_xml_api.php?manual=1
     */
    public function activate($order, $model)
    {
        if(!is_object($model)) {
            throw new \Box_Exception('Could not activate order. Service was not created', null, 7456);
        }
        
        $oc = json_decode($order->config, 1);
        
        $api = $this->di['api_admin'];
        $client = $api->client_get(array('id'=>$order->client_id));
        $product = $api->product_get(array('id'=>$order->product_id));
        $pc = $product['config'];

        $required = array(
            'server_id'     => 'CentovaCast product is not configured properly. Field server_id is missing',
            'maxclients'    => 'CentovaCast product is not configured properly. Field maxclients is missing',
            'maxbitrate'    => 'CentovaCast product is not configured properly. Field maxbitrate is missing',
            'transferlimit' => 'CentovaCast product is not configured properly. Field transferlimit is missing',
            'diskquota'     => 'CentovaCast product is not configured properly. Field diskquota is missing',
            'template'      => 'CentovaCast product is not configured properly. Field template is missing',
            'autostart'     => 'CentovaCast product is not configured properly. Field autostart is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $pc);
        
        $server         = $this->getServer($pc['server_id']);
        $username       = ( isset($oc['username']) && !empty($oc['username']) ) ? $oc['username'] : $this->_genUsername($client);
        $password       = ( isset($oc['password']) && !empty($oc['password']) ) ? $oc['password'] : $this->di['tools']->generatePassword();
        $sourcepassword = $this->di['tools']->generatePassword();
        
    	$params = array(
			'hostname'			=>	$server['hostname'],
			'ipaddress'			=>	$server['ip'],
			'port'				=>	'auto',
			'maxclients'		=>	$pc['maxclients'],
			'username'			=>	$username,
			'adminpassword'		=>	$password,
			'sourcepassword'	=>	$sourcepassword,
			'maxbitrate'		=>	$pc['maxbitrate'],
			'transferlimit'		=>	$pc['transferlimit'],
			'diskquota'			=>	$pc['diskquota'],
			'title'				=>	$client['company'],
			'organization'		=>	$client['company'],
			'genre'             =>	$client['company'],
			'email'             =>	$client['email'],
			'url'				=>	BB_URL,
			'usesource'			=>	'1',
			'introfile'			=>	'',
			'fallbackfile'		=>	'',
			'autorebuildlist'	=>	'0',
			'autostart'			=>	(bool)$pc['autostart'],
			'timezone'			=>	'auto',
			'allowproxy'		=>	'0',
			'charset'			=>	'ISO-8859-1',
			'servertype'		=>	$pc['servertype'],
			'sourcetype'		=>	$pc['sourcetype'],
			'template'			=>	$pc['template'],
    	);
        
        // call api if not import action
        if(!isset($oc['import']) || !$oc['import']) {
            $this->_apiSystemCall($server, 'provision', $params);
        }
        
        $model->server_id       = $pc['server_id'];
        $model->username        = $username;
        $model->pass            = $this->encryptPass($password);
        $model->updated_at      = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        
        return array_merge($params, array('server'=>$server));
    }

    private function _genUsername($client)
    {
        $u = preg_replace('/([^@]*).*/', '$1', $client['email']);
        $u = str_replace('.', "", $u);
        $sql="SELECT id FROM service_centovacast WHERE username = :u";
        $exists = $this->di['db']->getCell($sql, array('u'=>$u));
        if($exists) {
            $u = $u. rand(1, 100); 
        }
        return $u;
    }
    
    /**
     * Suspend order
     * 
     * @param $order
     * @return boolean
     */
    public function suspend($order, $model)
    {
        if(!is_object($model)) {
            throw new \Box_Exception('Could not suspend order. Service was not created', null, 7456);
        }
        $server = $this->getServer($model->server_id);
    	$params = array(
			'username'			=>	$model->username,
			'status'			=>	'disabled',
    	);
        $this->_apiSystemCall($server, 'setstatus', $params);
        
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
        if(!is_object($model)) {
            throw new \Box_Exception('Could not unsuspend order. Service was not created', null, 7456);
        }
        
        $server = $this->getServer($model->server_id);
    	$params = array(
			'username'			=>	$model->username,
			'status'			=>	'enabled',
    	);
        $this->_apiSystemCall($server, 'setstatus', $params);
        
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
        if(is_object($model)) {
            try {
                $server = $this->getServer($model->server_id);
                $params = array(
                    'username'			=>	$model->username,
                );
                $this->_apiSystemCall($server, 'terminate', $params);
            } catch(\Exception $e) {
                error_log($e->getMessage());
            }
            
            $this->di['db']->trash($model);
        }
        return true;
    }
    
    public function start($model)
    {
        $this->_apiServerCall($model, 'start');
        return true;
    }
    
    public function stop($model)
    {
        $this->_apiServerCall($model, 'stop');
        return true;
    }
    
    public function restart($model)
    {
        $this->_apiServerCall($model, 'restart');
        return true;
    }
    
    public function reload($model)
    {
        $this->_apiServerCall($model, 'reload');
        return true;
    }
    
    public function getaccount($model)
    {
        $res = $this->_apiServerCall($model, 'getaccount');
        return $res['account'];
    }
    
    public function getstatus($model)
    {
        $res = $this->_apiServerCall($model, 'getstatus');
        return $res['status'];
    }
    
    public function getsongs($model)
    {
        $res = $this->_apiServerCall($model, 'getsongs');
        return isset($res['songs']['row']) ? $res['songs']['row'] : array();
    }
    
    public function reconfigure($model, $params)
    {
        return $this->_apiServerCall($model, 'reconfigure', $params, true);
    }
    
    public function info($model)
    {
        $server = $this->getServer($model->server_id);
        $params = array(
            'username'			=>	$model->username,
        );
    	$res = $this->_apiSystemCall($server, 'info', $params);
        return $res['row'];
    }
    
    public function version($server)
    {
    	return $this->_apiSystemCall($server, 'version');
    }
    
    public function apiConnection($server)
    {
        $params = array(
            'username'  => 'all',
        );
    	return $this->_apiSystemCall($server, 'info', $params);
    }
    
    public function cpanelUrl($model)
    {
        $server = $this->getServer($model->server_id);
        return $server['url'];
    }
    
    /**
     * @param string $method
     */
    private function _apiServerCall($model, $method, $arguments = array(), $admin = false)
    {
        $account_username = $model->username;
        $account_password = $this->di['crypt']->decrypt($model->pass, $this->_salt);
        $server             = $this->getServer($model->server_id);
        
        $centovacast_url    = $server['url'];
        
        if($admin) {
            $account_password = 'admin|'.$server['secret'];
        }
        
        require_once(dirname(__FILE__).'/ccapiclient/ccapiclient.php');
        $server = new CCServerAPIClient($centovacast_url);
        $server->cc_initialize($centovacast_url);
        $server->call($method, $account_username, $account_password, $arguments);

        if (!$server->success) {
            throw new \Exception($server->error);
        }
        
        return $server->bb_data;
    }
    
    /**
     * @param string $method
     */
    private function _apiSystemCall($server, $method, $params = array())
    {
        require_once(dirname(__FILE__).'/ccapiclient.php');
        $centovacast_url    = $server['url'];
        $admin_password     = $server['secret'];
        $system = new CCSystemAPIClient($centovacast_url);
        $system->cc_initialize($centovacast_url);
        $system->call($method, $admin_password, $params);
        
        if (!$system->success) {
            throw new \Exception($system->error);
        }
        
        return $system->bb_data;
    }
    
    public function encryptPass($password)
    {
        return $this->di['crypt']->encrypt($password, $this->_salt);
    }
}
