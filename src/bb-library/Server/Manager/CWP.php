<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * CWP API
 * @see https://docs.control-webpanel.com/docs/developer-tools/api-manager
 */
class Server_Manager_CWP extends Server_Manager
{
	public function init()
	{
		if (!extension_loaded('curl')) {
			throw new Server_Exception('cURL extension is not enabled');
		}

		if(empty($this->_config['ip'])) {
			throw new Server_Exception('Server manager "CWP" is not configured properly. IP address is not set!');
		}

		if(empty($this->_config['host'])) {
			throw new Server_Exception('Server manager "CWP" is not configured properly. Hostname is not set!');
		}

		if(empty($this->_config['accesshash'])) {
			throw new Server_Exception('Server manager "CWP" is not configured properly. API Key / Access Hash is not set!');
		} else {
			$this->_config['accesshash'] = preg_replace("'(\r|\n)'","",$this->_config['accesshash']);
		}

		if(!$this->_config['secure']){
			/**
			 * CWP will simply return an error if you try to connect via a HTTP connection
			 */
			throw new Server_Exception('Server manager "CWP" is not configured properly. CWP API will only accept a secure connection!');
		}
		/**
		 * Default API port. Not sure if it can be overridden, but I've opted to leave the option anyways.
		 */
		if(empty($this->_config['port'])){
			$this->_config['port'] = '2304';
		}
	}

	public static function getForm()
	{
		return array(
			'label'     =>  'CWP',
		);
	}

    /**
     * We can actually generate a direct log-in link from CWP, but I'm not sure if that's a secure thing to do here.
     */
	public function getLoginUrl()
	{
		$host = $this->_config['host'];
		return 'https://'.$host.':2083';
	}

	public function getResellerLoginUrl()
	{
		$host = $this->_config['host'];
		return 'https://'.$host.':2031';
	}

	/**
	 * CWP doesn't have a connection test function, so we ask what kind of server it is (EX: KVM / OpenVZ)
	 * No error means the connection worked and we can continue
	 */
	public function testConnection()
	{
		$APIKey = $this->_config['accesshash'];

		$host = $this->_config['host'];
		$port = $this->_config['port'];

		$data = array(
			'key'     => $APIKey,
			'action'  => 'list',
		);

        if(makeAPIRequest($host, $port, 'typeserver', $data)) {
            return true;
        } else {
            throw new Server_Exception('Failed to connect to server');
        }
	}
    
    /**
     * BoxBilling will only sync usernames and IP address, neither can be changed or synced from CWP
     * That makes this function useless, but we still set what we can incase they are ever used in the future
     */
	public function synchronizeAccount(Server_Account $a)
	{
		$this->getLog()->info('Synchronizing account with server '.$a->getUsername());

		$APIKey = $this->_config['accesshash'];

		$host = $this->_config['host'];
		$port = $this->_config['port'];

		$data = array(
			'key'     => $APIKey,
			'action'  => 'list',
			'user'    => $a->getUsername()
		);

		$new = clone $a;
		$acc = makeAPIRequest($host, $port, 'accountdetail', $data);

		if($acc['account_info']['state'] == 'suspended'){
		    $new->setSuspended(true);
		} else {
		   	$new->setSuspended(false);
		}
        
        $new->setPackage($acc['account_info']['package_name']);
        $new->setReseller($acc['account_info']['reseller']);
		return $new;
	}
    
    /**
     * Package name must match on both CWP and FOSSBilling!
     */
	public function createAccount(Server_Account $a)
	{
		$this->getLog()->info('Creating account '.$a->getUsername());

		$client = $a->getClient();
		$package = $a->getPackage()->getName();

		$APIKey = $this->_config['accesshash'];

		$host = $this->_config['host'];
		$port = $this->_config['port'];
		$ip = $this->_config['ip'];

		$data = array(
			'key'          => $APIKey,
			'action'       => 'add',
			'domain'       => $a->getDomain(),
			'user'         => $a->getUsername(),
			'pass'         => base64_encode($a->getPassword()),
			'email'        => $client->getEmail(),
			'package'      => $package,
			'server_ips'   => $ip,
			'encodepass'   => true
		);
		if($a->getReseller()) {
			$data['reseller'] = 1;
		}

        if(makeAPIRequest($host, $port, 'account', $data)) {
            return true;
        } else {
            throw new Server_Exception('Failed to create account!');
        }
	}

	public function suspendAccount(Server_Account $a)
	{
		$this->getLog()->info('Suspending account '.$a->getUsername());

		$client = $a->getClient();

		$APIKey = $this->_config['accesshash'];

		$host = $this->_config['host'];
		$port = $this->_config['port'];

		$data = array(
			'key'      => $APIKey,
			'action'   => 'susp',
			'user'     => $a->getUsername()
		);

        if(makeAPIRequest($host, $port, 'account', $data)) {
            return true;
        } else {
            throw new Server_Exception('Failed to suspend account!');
        }
	}

	public function unsuspendAccount(Server_Account $a)
	{
		$this->getLog()->info('Un-suspending account '.$a->getUsername());

		$client = $a->getClient();

		$APIKey = $this->_config['accesshash'];

		$host = $this->_config['host'];
		$port = $this->_config['port'];

		$data = array(
			'key'      => $APIKey,
			'action'   => 'unsp',
			'user'     => $a->getUsername()
		);

        if(makeAPIRequest($host, $port, 'account', $data)) {
            return true;
        } else {
            throw new Server_Exception('Failed to unsuspend account!');
        }
	}

	public function cancelAccount(Server_Account $a)
	{
		$this->getLog()->info('Canceling account '.$a->getUsername());

		$client = $a->getClient();

		$APIKey = $this->_config['accesshash'];

		$host = $this->_config['host'];
		$port = $this->_config['port'];

		$data = array(
			'key'     => $APIKey,
			'action'  => 'del',
			'user'    => $a->getUsername(),
			'email'   => $client->getEmail()
		);

        if(makeAPIRequest($host, $port, 'account', $data)) {
            return true;
        } else {
            throw new Server_Exception('Failed to cancel / delete account!');
        }
	}

	/**
	 * Probably works, but my CWP instance doesn't even return an error to this function let along change the package
	 * Ticket has been put in with CWP
	 * I will update this comment when the issue is resolved
	 */
	public function changeAccountPackage(Server_Account $a, Server_Package $p)
	{
		$this->getLog()->info('Changing package on account '.$a->getUsername());

		$package = $a->getPackage()->getName();

		$APIKey = $this->_config['accesshash'];

		$host = $this->_config['host'];
		$port = $this->_config['port'];

		$data = array(
			'key'      => $APIKey,
			'action'   => 'upd',
			'user'     => $a->getUsername(),
			'package'  => $package
		);

        if(makeAPIRequest($host, $port, 'changepack', $data)) {
            return true;
        } else {
            throw new Server_Exception('Failed to change the account package!');
        }
	}

	public function changeAccountPassword(Server_Account $a, $new)
	{
		$this->getLog()->info('Changing password on account '.$a->getUsername());

		$client = $a->getClient();

		$APIKey = $this->_config['accesshash'];

		$host = $this->_config['host'];
		$port = $this->_config['port'];

		$data = array(
			'key'     => $APIKey,
			'action'  => 'udp',
			'user'    => $a->getUsername(),
			'pass'    => $new
		);

        if(makeAPIRequest($host, $port, 'changepass', $data)) {
            return true;
        } else {
            throw new Server_Exception('Failed to change the account password!');
        }
	}

	/**
	 * Function graveyard for things CWP doesn't support
	 */
	public function changeAccountUsername(Server_Account $a, $new)
	{
		throw new Server_Exception('CWP Does not support username changes');
	}

	public function changeAccountDomain(Server_Account $a, $new)
	{
		throw new Server_Exception('CWP Does not support changing the primary domain name');
	}

	public function changeAccountIp(Server_Account $a, $new)
	{
		throw new Server_Exception('CWP Does not support changing the IP');
	}
}
	/**
	 * Makes the CURL request to the server
	 */
	function makeAPIRequest($host, $port, $func, $data)
	{
		$url = 'https://'.$host.":".$port.'/v1/'.$func;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt ($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt ($ch, CURLOPT_POST, 1);
		$response = json_decode(curl_exec($ch), true);
		curl_close($ch);

		if(!empty($response['status'])){
			$status = $response['status'];
		} else {
		    $status = 'Error';
		}

		if(!empty($response['result'])){
			$result = $response['result'];
		} else {
		    $result = null;
		}

		if($status == 'OK' && $func != 'accountdetail'){
			return true;
		} else {
			if($status == 'Error'){
		        return false;
			} else {
			    return $result;
			}
		}
	}
