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

use \InfinityFree\MofhClient\Client;
class Server_Manager_MOFH extends Server_Manager
{
	// First, let's start with some information.
	//
	// MyOwnFreeHost has it's own (and weird) system of storing user identifiers.
	// When making API calls, you *must* use the 8-digit username that you've generated and submitted to MOFH while creating the account (a1b2c3d4)
	// Then, it generates *another* username, which is basically like "<3 lettered reseller identifier>_<another, and different 8 char integer>" (abc_12345678)
	// This makes everything weird, the user logs into the control panel with the abc_12345678 username, but we still need to store the other one somewhere
	// While making API calls, the username you submit is not the one which user uses during their sign-in process, it's the one *you* generated in the beginning.
	// Hence, you need to store it, or hash the domain as we did.
	//
	// We used the "domainToID()" function to generate a unique hash for every domain, and we can be sure that it won't change unless the domain was changed
	// Changing the domain is not supported by MyOwnFreeHost's API, so the domain probably won't be changed afterwards.

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

	public static function domainToID($domain)
    {
		// So, here's how this works.
		// We first generate a SHA-256 hash with the domain
		// And then, strip out the first 8 characters to
		// generate a unique ID.
		//
		// Let's understand it better with an example.
		// So, here's the SHA-256 hash of 'boxbilling.com':
		// 0d3019bc07e517c2158a2c7eaccdf286fdececa78de7f736f2d1d602522e80c7
		//
		// We get the first 8 characters out of that, and it produces:
		// 0d3019bc
		//
		// Since it's a hash, it doesn't matter whether portions are discarded,
		// but rather that the same input will produce the same hash.
		//
		// And the nice part, we don't need to store this in a database.
		// As long as it's the same domain, it will always generate the same hash.

		return mb_substr(hash('sha256', $domain), 0, 8);
    }

    public static function getForm()
    {
        return array(
			'label'		=>	'MyOwnFreeHost',
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
		return $protocol . '://cpanel.' . $host;
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
		$this->client = $a->getClient();

		$request = $this->MOFHclient->getDomainUser([
			'domain' => $a->getDomain(),
		]);

		// Let's send the request, and store the response.
		$response = $request->send();

		if ($response->isFound()) {

			if ($response->getStatus() == "ACTIVE") {
				$a->setSuspended(false);
			} else if ($response->getStatus() == "SUSPENDED") {
				$a->setSuspended(true);
			};
			
			// Setting the username to the one that is being used in the client area.
			$a->setUsername($response->getUsername());
		}

		// If something went wrong, we'll catch the error, and display it.
		if (!$response->isSuccessful()) {
			throw new Server_Exception($response->getMessage());
		}

        return $a;
    }

	/**
	* Create an account
	*
	* We'll use the provided Server_Account object to use the data from the order,
	* and call the MyOwnFreeHost API to create an account.
	* Then, we'll store the returned vPanel username to our local database.
	* 
	* @param Server_Account $a
	*/

	public function createAccount(Server_Account $a) {
		// Temporarily suspending the account while it's being set up.
		$a->setSuspended(true);

        $this->client = $a->getClient();

		$request = $this->MOFHclient->createAccount([
			'username' => $this->domainToID($a->getDomain()),
			'password' => $a->getPassword(),
			'domain' => $a->getDomain(),
			'email' => $this->client->getEmail(),
			'plan' => 'boxbilling',
		]);

		// Let's send the request, and store the response.
		$response = $request->send();

		// Setting the username to the one that is being used in the client area.
		$a->setUsername($response->getVpUsername());

		// If something went wrong, we'll catch the error, and display it.
		if (!$response->isSuccessful()) {
			throw new Server_Exception($response->getMessage());
		}

		// It's all done. We can now unsuspend the account.
		$a->setSuspended(false);

		return $a;
	}

	public function suspendAccount(Server_Account $a, $suspend = true) {

		$request = $this->MOFHclient->suspend([
			'username' => $this->domainToID($a->getDomain()),
			'reason' => "Triggered by BoxBilling.",
			'linked' => false
		]);

		$response = $request->send();

		// If something went wrong, we'll catch the error, and display it.
		if (!$response->isSuccessful()) {
			throw new Server_Exception('MOFH error: ' . $response->getMessage());
		}

		return true;
	}

	public function unsuspendAccount(Server_Account $a) {

		$request = $this->MOFHclient->unsuspend([
			'username' => $this->domainToID($a->getDomain())
		]);

		// Let's send the request, and store the response.
		$response = $request->send();

		// If something went wrong, we'll catch the error, and display it.
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
			'username' => $this->domainToID($a->getDomain()),
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