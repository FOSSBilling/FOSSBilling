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
 * @copyright Copyright (c) 2010-2020 BoxBilling (http://www.boxbilling.com)
 * @license   http://www.boxbilling.com/LICENSE.txt
 * @version   $Id$
 */
use \InfinityFree\MofhClient\Client;
class Server_Manager_MOFH extends Server_Manager
{
	public function init()
    {
		$this->MOFHclient = Client::create([
			'apiUsername' => $this->_config['username'],
			'apiPassword' => $this->_config['password'],
		]);

        if (!extension_loaded('curl')) {
            throw new Server_Exception('cURL extension is not enabled');
        }
	}

    public static function getForm()
    {
        return array(
            'label'     =>  'MyOwnFreeHost',
        );
    }

    public function getLoginUrl()
    {
		if($this->_config['secure']) {
            $protocol = 'https';
        } else {
			$protocol = 'http';
		}
		$host     = $this->_config['host'];
		return $protocol . '://' . $host;
    }

    public function getResellerLoginUrl()
    {
        return 'https://panel.myownfreehost.net/';
    }
    
	public function testConnection()
    {
		$request = $this->MOFHclient->availability([
			'domain' => 'boxbilling.com',
		]);

		$response = $request->send();

		if ($response->isSuccessful()) {
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

        $this->client = $a->getClient();

		$request = $this->MOFHclient->createAccount([
			'username' => $a->getId(),
			'password' => $a->getPassword(),
			'domain' => $a->getDomain(),
			'email' => $this->client->getEmail(),
			'plan' => 'boxbilling',
		]);

		$response = $request->send();

		$this->client = $a->getClient();
        $this->client->setId($a->getUsername());
        $this->client->setUsername($response->getVpUsername());

		if (!$response->isSuccessful()) {
			throw new Server_Exception($response->getMessage());
		}

		return true;
	}

	public function suspendAccount(Server_Account $a, $suspend = true) {

		$request = $this->MOFHclient->suspend([
			'username' => $a->getId(),
			'reason' => $a->getNote(),
			'linked' => false
		]);

		$response = $request->send();

		if (!$response->isSuccessful()) {
			throw new Server_Exception('MOFH error: ' . $response->getMessage());
		}

		return true;
	}

	public function unsuspendAccount(Server_Account $a) {

		$request = $this->MOFHclient->unsuspend([
			'username' => $a->getId()
		]);

		$response = $request->send();

		if (!$response->isSuccessful()) {
			throw new Server_Exception('MOFH error: ' . $response->getMessage());
		}

		return true;
	}

	public function cancelAccount(Server_Account $a)
	{
		throw new Server_Exception("Unfortunately, MyOwnFreeHost API does not support account termination. You may log into your reseller panel, and execute the action there.");
	}

	public function changeAccountPackage(Server_Account $a, Server_Package $p)
    {
		throw new Server_Exception("Unfortunately, MyOwnFreeHost API does not support changing account packages. You may log into your reseller panel, and execute the action there.");

	}

	public function changeAccountPassword(Server_Account $a, $new) {
		$request = $this->MOFHclient->password([
			'username' => $a->getId(),
			'password' => $new,
		]);
		
		$response = $request->send();

		if (!$response->isSuccessful()) {
			throw new Server_Exception('MOFH error: ' . $response->getMessage());
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
        throw new Server_Exception('Server manager does not support IP changes');
    }
    
	private function _getResourcePlan(Server_Package $p)
	{

	}

	private function _getDnsTemplate(Server_Package $p)
	{
		throw new Server_Exception('Server manager does not support DNS templates');
	}
}