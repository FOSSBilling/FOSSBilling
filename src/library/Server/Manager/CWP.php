<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * CWP API.
 *
 * @see https://docs.control-webpanel.com/docs/developer-tools/api-manager
 */
class Server_Manager_CWP extends Server_Manager
{
	public function init()
	{
		if (empty($this->_config['ip'])) {
			throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'CWP', ':missing' => 'IP address'], 2001);
		}

		if (empty($this->_config['host'])) {
			throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'CWP', ':missing' => 'Hostname'], 2001);
		}

		if (empty($this->_config['accesshash'])) {
			throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'CWP', ':missing' => 'API Key / Access Hash'], 2001);
		} else {
			$this->_config['accesshash'] = trim($this->_config['accesshash']);
		}

		if (empty($this->_config['port'])) {
			$this->_config['port'] = '2304';
		}
	}

	public static function getForm()
	{
		return [
			'label' => 'CWP',
			'form' => [
				'credentials' => [
					'fields' => [
						[
							'name' => 'accesshash',
							'type' => 'text',
							'label' => 'API key',
							'placeholder' => 'API key you generated from within CWP.',
							'required' => true,
						],
					],
				],
			]
		];
	}

	/**
	 * We can actually generate a direct log-in link from CWP, but I'm not sure if that's a secure thing to do here.
	 */
	public function getLoginUrl(?Server_Account $account = null)
	{
		$host = $this->_config['host'];
		return 'https://' . $host . ':2083';
	}

	public function getResellerLoginUrl(?Server_Account $account = null)
	{
		$host = $this->_config['host'];
		return 'https://' . $host . ':2031';
	}

	/**
	 * CWP doesn't have a connection test function, so we ask what kind of server it is (EX: KVM / OpenVZ)
	 * No error means the connection worked and we can continue
	 */
	public function testConnection()
	{
		$data = [
			'action'  => 'list',
		];

		if ($this->makeAPIRequest('account', $data)) {
			return true;
		} else {
			throw new Server_Exception('Failed to connect to the :type: server. Please verify your credentials and configuration', [':type:' => 'CWP']);
		}
	}

	public function synchronizeAccount(Server_Account $a)
	{
		$this->getLog()->info('Synchronizing account with server ' . $a->getUsername());

		$data = [
			'action'  => 'list',
			'user'    => $a->getUsername()
		];

		$new = clone $a;
		$acc = $this->makeAPIRequest('accountdetail', $data);

		if ($acc['account_info']['state'] == 'suspended') {
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
	public function createAccount(Server_Account $account)
	{
		$this->getLog()->info('Creating account ' . $account->getUsername());

		$client = $account->getClient();
		$package = $account->getPackage()->getName();

		$ip = $this->_config['ip'];

		$data = [
			'action'       => 'add',
			'domain'       => $account->getDomain(),
			'user'         => $account->getUsername(),
			'pass'         => base64_encode($account->getPassword()),
			'email'        => $client->getEmail(),
			'package'      => $package,
			'server_ips'   => $ip,
			'encodepass'   => true
		];

		if ($account->getReseller()) {
			$data['reseller'] = 1;
		}

		if ($this->makeAPIRequest('account', $data)) {
			return true;
		} else {
			$placeholders = [':action:' => __trans('create account'), ':type:' => 'CWP'];
			throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
		}
	}

	public function suspendAccount(Server_Account $a)
	{
		$this->getLog()->info('Suspending account ' . $a->getUsername());

		$data = [
			'action'   => 'susp',
			'user'     => $a->getUsername()
		];

		if ($this->makeAPIRequest('account', $data)) {
			return true;
		} else {
			$placeholders = ['action' => __trans('suspend account'), 'type' => 'CWP'];
			throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
		}
	}

	public function unsuspendAccount(Server_Account $a)
	{
		$this->getLog()->info('Un-suspending account ' . $a->getUsername());

		$data = [
			'action'   => 'unsp',
			'user'     => $a->getUsername()
		];

		if ($this->makeAPIRequest('account', $data)) {
			return true;
		} else {
			$placeholders = [':action:' => __trans('unsuspend account'), ':type:' => 'CWP'];
			throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
		}
	}

	public function cancelAccount(Server_Account $a)
	{
		$this->getLog()->info('Canceling account ' . $a->getUsername());

		$client = $a->getClient();

		$data = [
			'action'  => 'del',
			'user'    => $a->getUsername(),
			'email'   => $client->getEmail()
		];

		if ($this->makeAPIRequest('account', $data)) {
			return true;
		} else {
			$placeholders = [':action:' => __trans('cancel account'), ':type:' => 'CWP'];
			throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
		}
	}

	public function changeAccountPackage(Server_Account $a, Server_Package $p)
	{
		$this->getLog()->info('Changing package on account ' . $a->getUsername());

		$package = $a->getPackage()->getName();

		$data = [
			'action'   => 'upd',
			'user'     => $a->getUsername(),
			'package'  => $package
		];

		if ($this->makeAPIRequest('changepack', $data)) {
			return true;
		} else {
			$placeholders = [':action:' => __trans('change account package'), ':type:' => 'CWP'];
			throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
		}
	}

	public function changeAccountPassword(Server_Account $a, $new)
	{
		$this->getLog()->info('Changing password on account ' . $a->getUsername());

		$data = [
			'action'  => 'udp',
			'user'    => $a->getUsername(),
			'pass'    => $new
		];

		if ($this->makeAPIRequest('changepass', $data)) {
			return true;
		} else {
			$placeholders = [':action:' => __trans('change account password'), ':type:' => 'CWP'];
			throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
		}
	}

	/**
	 * Function graveyard for things CWP doesn't support
	 */
	public function changeAccountUsername(Server_Account $a, $new): never
	{
		throw new Server_Exception(':type: does not support :action:', [':type:' => 'CWP', ':action:' => __trans('username changes')]);
	}

	public function changeAccountDomain(Server_Account $a, $new): never
	{
		throw new Server_Exception(':type: does not support :action:', [':type:' => 'CWP', ':action:' => __trans('changing the account domain')]);
	}

	public function changeAccountIp(Server_Account $a, $new): never
	{
		throw new Server_Exception(':type: does not support :action:', [':type:' => 'CWP', ':action:' => __trans('changing the account IP')]);
	}

	/**
	 * Makes the HTTP request to the server
	 */
	private function makeAPIRequest($func, $data)
	{
		$data['key'] = $this->_config['accesshash'];
		$host = $this->_config['host'];
		$port = $this->_config['port'];
		$url = 'https://' . $host . ":" . $port . '/v1/' . $func;

		$client = $this->getHttpClient()->withOptions([
			'verify_peer'	=> false,
			'verify_host'	=> false,
		]);

		$request = $client->request('POST', $url, [
			'body'	=> $data,
		]);

		$response = $request->toArray();

		$status = $response['status'] ?? 'Error';
		$result = $response['result'] ?? null;
		$msg = $response['msg'] ?? 'CWP did not return a message in it\'s response.';

		if ($status == 'OK' && $func != 'accountdetail') {
			return true;
		} elseif ($status !== 'OK') {
			error_log('CWP Server manager error. Status: ' . $status . '. Message: ' . $msg);
			return false;
		} else {
			return $result;
		}
	}
}
