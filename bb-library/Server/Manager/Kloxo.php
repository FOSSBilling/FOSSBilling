<?php
/**
 * BoxBilling
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file LICENSE.txt
 * It is also available through the world-wide-web at this URL:
 * http://www.boxbilling.com/LICENSE.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@boxbilling.com so we can send you a copy immediately.
 *
 * @copyright Copyright (c) 2010-2012 BoxBilling (http://www.boxbilling.com)
 * @license   http://www.boxbilling.com/LICENSE.txt
 * @version   $Id$
 */
class Server_Manager_Kloxo extends Server_Manager
{
	public function init()
    {
        if (!extension_loaded('curl')) {
            throw new Server_Exception('cURL extension is not enabled');
        }

        if($this->_config['secure']) {
            $this->_config['port'] = 7777;
        } else {
            $this->_config['port'] = 7778;
        }
	}

    public static function getForm()
    {
        return array(
            'label'     =>  'Kloxo',
        );
    }

    public function getLoginUrl()
    {
        $host     = $this->_config['host'];
        return 'http://'.$host.':' . $this->_config['port'] . '/login/';
    }

    public function getResellerLoginUrl()
    {
        return $this->getLoginUrl();
    }
    
    private function _makeRequest($params)
    {
		$host = 'http';
		if ($this->_config['secure']) {
			$host .= 's';
		}
		$host .= '://' . $this->_config['host'] . ':'.$this->_config['port'].'/webcommand.php';
    	$host .= '?login-class=client&login-name=' . urlencode($this->_config['username']);
    	$host .= '&login-password=' . urlencode($this->_config['password']);
    	$host .= '&output-type=json';

    	foreach ($params as $key => $value) {
    		$host .= '&' . $key . '=' . urlencode($value);
    	}

    	$ch = curl_init ();
    	curl_setopt ($ch, CURLOPT_URL, $host);
    	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
    	curl_setopt ($ch, CURLOPT_TIMEOUT, 1);
		$result = curl_exec($ch);

		if (curl_errno ($ch)) {
			throw new Server_Exception('Error connecting to Kloxo server: ' . curl_errno ($ch) . ' - ' . curl_error ($ch));
		}

		return json_decode(trim($result), 1);
    }

	public function testConnection()
    {
		$params = array(
			'action'	=>	'simplelist',
			'resource'	=>	'process',
		);

		$result = $this->_makeRequest($params);

		if (isset($result['return']) && $result['return'] == 'success') {
			return true;
		}

		return false;
	}
    
    public function synchronizeAccount(Server_Account $a)
    {
        $this->getLog()->info('Synchronizing account with server '.$a->getUsername());
        return $a;
    }

	public function createAccount(Server_Account $a) {
		$p = $a->getPackage();
		$resourcePlan = $this->_getResourcePlan($p);
		$dnsTemplate = $this->_getDnsTemplate($p);

        $client = $a->getClient();

		$params = array(
			'action'			=>	'add',
			'class'				=>	'client',
			'name'				=>	$a->getUsername(),
			'v-plan_name'		=>	$resourcePlan,
			'v-type'			=>	$a->getReseller() ? 'reseller' : 'customer',
			'v-contactemail'	=>	$client->getEmail(),
			'v-send_welcome_f'	=>	'off',
			'v-domain_name'		=>	$a->getDomain(),
			'v-dnstemplate_name'=>	$dnsTemplate,
			'v-password'		=>	$a->getPassword(),
		);

		$result = $this->_makeRequest($params);

		if (isset($result['return']) && $result['return'] == 'error') {
			throw new Server_Exception('Kloxo error: ' . $result['message']);
		}

		return true;
	}

	public function suspendAccount(Server_Account $a, $suspend = true) {
		$params = array(
			'class'		=>	'client',
			'name'		=>	$a->getUsername(),
			'action'	=>	'update',
			'subaction'	=>	$suspend ? 'disable' : 'enable',
		);

		$result = $this->_makeRequest($params);

		if (isset($result['return']) && $result['return'] == 'error') {
			throw new Server_Exception('Kloxo error: ' . $result['message']);
		}

		return true;
	}

	public function unsuspendAccount(Server_Account $a) {
		return $this->suspendAccount($a, false);
	}

	public function cancelAccount(Server_Account $a) {
		$params = array(
			'action'	=>	'delete',
			'class'		=>	'client',
			'name'		=>	$a->getUsername(),
		);

		$result = $this->_makeRequest($params);

		if (isset($result['return']) && $result['return'] == 'error') {
			throw new Server_Exception('Kloxo error: ' . $result['message']);
		}

		return true;
	}

	public function changeAccountPackage(Server_Account $a, Server_Package $p)
    {
		throw new Server_Exception('Changes to account can only be made in Kloxo');
	}

	public function changeAccountPassword(Server_Account $a, $new) {
		$params = array(
			'class'		=>	'client',
			'name'		=>	$a->getUsername(),
			'action'	=>	'update',
			'subaction'	=>	'change_password',
			'v-password'=>	$new,
		);

		$result = $this->_makeRequest($params);

		if (isset($result['return']) && $result['return'] == 'error') {
			throw new Server_Exception('Kloxo error: ' . $result['message']);
		}

		return true;
	}

    public function changeAccountUsername(Server_Account $a, $new)
    {
        throw new Server_Exception('Server manager does not support username changes');
    }
    
    public function changeAccountDomain(Server_Account $a, $new)
    {
        throw new Server_Exception('Server manager does not support domain changes');
    }

    public function changeAccountIp(Server_Account $a, $new)
    {
        throw new Server_Exception('Server manager does not support ip changes');
    }
    
	private function _getResourcePlan(Server_Package $p)
	{
		$params = array(
			'action'	=>	'simplelist',
			'resource'	=>	'resourceplan',
		);

		$result = $this->_makeRequest($params);

		if (isset($result['return']) && $result['return'] == 'error') {
			throw new Server_Exception('Can\'t find resource plan with name "' . $p->getName() .'". Go to Kloxo and create Resource Plan with this name');
		}

		$name = str_replace(' ', '_', $p->getName());
		foreach ($result['result'] as $key => $value) {
			if ($value == $name) {
				return $key;
			}
		}

		throw new Server_Exception('Can\'t find resource plan with name "' . $p->getName() .'". Go to Kloxo and create Resource Plan with this name');
	}

	private function _getDnsTemplate(Server_Package $p) {
		$params = array(
			'action'	=>	'simplelist',
			'resource'	=>	'dnstemplate',
		);

		$result = $this->_makeRequest($params);

		if (isset($result['return']) && $result['return'] == 'error') {
			throw new Server_Exception('Can\'t find Dns template with name "' . $p->getName() .'". Go to Kloxo and create Dns template with this name');
		}

		$name = str_replace(' ', '_', $p->getName());
		foreach ($result['result'] as $key => $value) {
			if ($value == $name . '.dnst') {
				return $key;
			}
		}

		throw new Server_Exception('Can\'t find Dns template with name "' . $p->getName() .'". Go to Kloxo and create Dns template with this name');
	}
}