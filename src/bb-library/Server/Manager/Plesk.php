<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

use PleskX\Api\Client;

class Server_Manager_Plesk extends Server_Manager
{
    public function init() {
        $this->_config['port'] = empty($this->_config['port']) ? 8443 : $this->_config['port'];

        $this->_client = new \PleskX\Api\Client($this->_config['host'], $this->_config['port']);
        $this->_client->setCredentials($this->_config['username'], $this->_config['password']);
	}

    public static function getForm()
    {
        return array(
            'label' => 'Plesk',
        );
    }

	public function getLoginUrl()
	{
        $protocol = $this->_config['secure'] ? 'https' : 'http';
        return $protocol . "://" . $this->_config['host'] . ':' . $this->_config['port'];
	}

    public function getResellerLoginUrl()
    {
        return $this->getLoginUrl();
    }

    public function testConnection()
    {
        $stats = $this->_client->server()->getStatistics();

        if ($stats->other->uptime < 0) {
            throw new Server_Exception('Connection to server failed');
        }
    }
    
    public function synchronizeAccount(Server_Account $a)
    {
        throw new Server_Exception('The server adapter does not support account synchronization');
    }

    public function createAccount(Server_Account $a)
    {
    	$this->getLog()->info('Creating account ' . $a->getUsername());

    	if ($a->getReseller()) {
    		$ips = $this->_getIps();
    		foreach($ips['exclusive'] as $key => $ip) {
	    		if (!$ip['empty']) {
    				unset ($ips['exclusive'][$key]);
    			}
    		}

            /*
    		if (count($ips['exclusive']) == 0) {
    			// Disabled. Resellers can also use shared IP addresses.
                // throw new Server_Exception('Out of free IP adresses');
            }
            */
                $ips['exclusive'] = array_values($ips['exclusive']);
    		    $rand = array_rand($ips['exclusive']);
    		    $a->setIp($ips['exclusive'][$rand]['ip']);
    	}

    	$id = $this->_createClient($a);
        $client = $a->getClient();
    	if (!$id) {
    		throw new Server_Exception('Failed to create new account');
    	} else {
            $client->setId((string)$id);
    	}
        $this->setSubscription($a);

        // We need to improve the way we handle the IP address before we should enable this.
        /*
    	if ($a->getReseller()) {
    		$this->_setIp($a);
    		$this->_changeIpType($a);
    		$this->_addNs($a, $domainId);
    	}
        */

    	return true;
    }

    public function setSubscription(Server_Account $a)
    {
        $this->getLog()->info('Setting subscription for account ' .  $a->getUsername());

        $this->_client->webspace()->request($this->_createSubscriptionProps($a, "add"));

    }

    // ??
    public function createServicePlan(Server_Account $a)
    {
        
    }

    public function updateSubscription(Server_Account $a)
    {
        $this->getLog()->info('Updating subscription for account ' . $a->getUsername());

        $this->_client->webspace()->request($this->_createSubscriptionProps($a, "set"));
    }

    public function deleteSubscription(Server_Account $a)
    {
        $result = $this->_client->webspace()->delete('name', $a->getDomain());
        
        return $result;
    }

    public function suspendAccount(Server_Account $a, $suspend = true)
    {
    	if ($a->getReseller()) {
            $result = $this->_client->reseller()->setProperties('login', $a->getUsername(), ['status' => 16]);
    	} else {
            $result = $this->_client->customer()->setProperties('login', $a->getUsername(), ['status' => 16]);
    	}

        return $result;
    }

    public function unsuspendAccount(Server_Account $a)
    {
    	if ($a->getReseller()) {
            $result = $this->_client->reseller()->setProperties('login', $a->getUsername(), ['status' => 0]);
    	} else {
            $result = $this->_client->customer()->setProperties('login', $a->getUsername(), ['status' => 0]);
    	}

        return $result;
    }

    public function cancelAccount(Server_Account $a)
    {
        if ($a->getReseller()) {
    		$result = $this->_client->reseller()->delete('login', $a->getUsername());
    	} else {
    		$result = $this->_client->customer()->delete('login', $a->getUsername());
    	}

        return $result;
    }

    public function changeAccountPackage(Server_Account $a, Server_Package $p)
    {
    	$id = $this->_modifyClient($a);
        $client = $a->getClient();
    	if (!$id) {
    		throw new Server_Exception('Can\'t modify client');
    	} else {
            $client->setId($id);
    	}

        $a->setPackage($p);
        $this->updateSubscription($a);

    	if ($a->getReseller()) {
    		$this->_addNs($a, $domainId);
    	}

    	return true;
    }

    public function changeAccountPassword(Server_Account $a, $new)
    {
    	$this->getLog()->info('Changing password for account ' . $a->getUsername());

    	if ($a->getReseller()) {
            $result = $this->_client->reseller()->setProperties('login', $a->getUsername(), ['passwd' => $a->getPassword()]);
    	} else {
            $result = $this->_client->customer()->setProperties('login', $a->getUsername(), ['passwd' => $a->getPassword()]);
    	}

    	return $result;
    }

    public function changeAccountUsername(Server_Account $a, $new)
    {
        throw new Server_Exception('Server manager does not support username changes');
    }
    
    public function changeAccountDomain(Server_Account $a, $new)
    {
        $this->getLog()->info('Updating domain for account ' . $a->getUsername());

        $a->setDomain($new);

        $params = [
            'set' => [
                'filter' => [
                    'owner-login' => $a->getUsername()
                ],
                'values' => [
                    'gen_setup' => [
                        'name' => $new
                    ]
                ]
            ]
        ];

        $this->_client->webspace()->request($params);   
    }

    public function changeAccountIp(Server_Account $a, $new)
    {
        throw new Server_Exception('Server manager does not support changing IP addresses');
    }

    private function _getIps() {
    	$response = $this->_client->ip()->get();

		$ips = array('shared' => array(), 'exclusive' => array());
		
        foreach($response as $ip) {
            $ips[(string)$ip->type][] = array(
				'ip'		=>	(string)$ip->ipAddress,
				'empty'		=>	empty($ip->default) ? true : false,
			);
		}

		return $ips;
    }

    private function _setIp(Server_Account $a, $new)
    {
    	$params = array(
    		'reseller'	=>	array(
    			'ippool-add-ip'	=>	array(
    				'reseller-id'   =>	$a->getUsername(),
    				'ip'    =>	array(
                        'ip-address' => $new
                    ),
    			),
    		),
    	);

        $response = $this->_client->request($params);
    }

    private function _addNs(Server_Account $a, $domainId) {
    	// Will be done in the future

		return true;
    }

    private function _getNs(Server_Account $a, $domainId)
    {
        $response = $this->_client->dns()->get('domain_id', $domainId);

   		$ns = array();

   		foreach ($response->dns->get_rec->result as $dns) {
   			if ($dns->data->type == 'NS') {
   				$ns[] = (string)$dns->id;
   			}
   		}

   		return $ns;
    }

    private function _removeDns($ns) {
		foreach($ns as $key => $id)	{
			$params['dns']['del_rec']['filter']['id' . $key] = $id;
		}
		if (empty($params)) {
			return true;
		}

        $response = $this->_client->request($params);

   		return true;
    }

    private function _modifyClient(Server_Account $a) {
        $client = $a->getClient();

        if ($a->getReseller()) {
    		$result = $this->_client->reseller()->setProperties('login', $a->getUsername(), $this->_createClientProps($a));
    	} else {
    		$result = $this->_client->customer()->setProperties('login', $a->getUsername(), $this->_createClientProps($a));
    	}

        return $result;
    }

    private function _changeIpType(Server_Account $a) {
        $client = $a->getClient();
    	$params = array(
    		'reseller'	=>	array(
    			'ippool-set-ip'	=>	array(
    				'reseller-id'	=>	$client->getId(),
    				'filter'	=>	array(
    					'ip-address'	=>	$a->getIp(),
    				),
    				'values'	=>	array(
    					'ip-type'	=>	'shared',
    				),
    			),
    		),
    	);

        $response = $this->_client->reseller()->request($params);

   		if (isset($response->reseller->{'ippool-set-ip'}->result->status) &&
   			$response->reseller->{'ippool-set-ip'}->result->status == 'ok') {
   			return true;
   		} else {
   			return false;
   		}
    }

    private function _createSubscriptionProps(Server_Account $a, $action) {
        $p = $a->getPackage();
        $props = array (
                $action	=>  array(
                    'gen_setup'	=>	array(
                        'name'          => $a->getDomain(),
                        'owner-login'	=>	$a->getUsername(),
                        'htype'			=>	'vrt_hst',
                        'ip_address'    => $a->getIp()
                    ),
                    'hosting' => array(
                        'vrt_hst'	=>	array(
                            'property'	=>	array(
                                array(
                                    'name'	=>	'ftp_login',
                                    'value'	=>	$a->getUsername(),
                                ),
                                array(
                                    'name'	=>	'ftp_password',
                                    'value'	=>	$a->getPassword(),
                                ),
                                array(
                                    'name'	=>	'php',
                                    'value'	=>	'true',
                                ),
                                array(
                                    'name'	=>	'ssl',
                                    'value'	=>	'true',
                                ),
                                array(
                                    'name'	=>	'cgi',
                                    'value'	=>	'true',
                                ),
                            ),
                            'ip_address' => $a->getIp(),
                        ),
                    ),
                    'limits'	=>	array(
                        'limit'	=> array(
                            array(
                                'name'	=>	'max_db',
                                'value'	=>	$p->getMaxSql() ? $p->getMaxSql() : 0,
                            ),
                            array(
                                'name'	=>	'max_maillists',
                                'value'	=>	$p->getMaxEmailLists() ? $p->getMaxEmailLists() : 0,
                            ),
                            array(
                                'name'	=>	'max_maillists',
                                'value'	=>	$p->getMaxEmailLists() ? $p->getMaxEmailLists() : 0,
                            ),
                            array(
                                'name'	=>	'max_box',
                                'value'	=>	$p->getMaxPop() ? $p->getMaxPop() : 0,
                            ),
                            array(
                                'name'	=>	'max_traffic',
                                'value'	=>	$p->getBandwidth() ? $p->getBandwidth() * 1024 * 1024: 0,
                            ),
                            array(
                                'name'	=>	'disk_space',
                                'value'	=>	$p->getQuota() ? $p->getQuota() * 1024 * 1024 : 0,
                            ),
                            array(
                                'name'	=>	'max_subdom',
                                'value'	=>	$p->getMaxSubdomains() ? $p->getMaxSubdomains() : 0,
                            ),
                            array(
                                'name'	=>	'max_subftp_users',
                                'value'	=>	$p->getMaxFtp() ? $p->getMaxFtp() : 0,
                            ),
                        ), 
                    ),
                    'permissions'	=>	array(
                        'permission'	=>	array(
                            array(
                                'name'	=>	'manage_subdomains',
                                'value'	=>	$p->getMaxSubdomains() ? 'true' : 'false',
                            ),
                            array(
                                'name'	=>	'manage_dns',
                                'value'	=>	'true'
                            ),
                            array(
                                'name'	=>	'manage_crontab',
                                'value'	=>	$p->getHasCron() ? 'true' : 'false',
                            ),
                            array(
                                'name'	=>	'manage_anonftp',
                                'value'	=>	$p->getHasAnonymousFtp() ? 'true' : 'false',
                            ),
                            array(
                                'name'	=>	'manage_sh_access',
                                'value'	=>	$p->getHasShell() ? 'true' : 'false',
                            ),
                            array(
                                'name'	=>	'manage_maillists',
                                'value'	=>	$p->getMaxEmailLists() ? 'true' : 'false',
                            ),
                            array(
                                'name'	=>	'create_domains',
                                'value'	=>	'true',
                            ),
                            array(
                                'name'	=>	'manage_phosting',
                                'value'	=>	'true',
                            ),
                            array(
                                'name'	=>	'manage_quota',
                                'value'	=>	$a->getReseller() ? 'true' : 'false',
                            ),
                            array(
                                'name'	=>	'manage_not_chroot_shell',
                                'value'	=>	$p->getHasShell() ? 'true' : 'false',
                            ),
                            array(
                                'name'	=>	'manage_domain_aliases',
                                'value'	=>	'true',
                            ),
                            array(
                                'name'	=>	'manage_subftp',
                                'value'	=>	$p->getMaxFtp() ? 'true' : 'false',
                            ),
                            array(
                                'name'	=>	'manage_spamfilter',
                                'value'	=>	$p->getHasSpamFilter() ? 'true' : 'false',
                            ),
                        ),
                    ),
                ),
            );

        return $props;
    }

    /**
     * Creates a new client account
     * @param Server_Account $a
     * @throws Server_Exception
     * @return integer client's Plesk id
     */
    private function _createClient(Server_Account $a) {
        $client = $a->getClient();

        $props = [
            'cname'				=>	$client->getCompany(),
    		'pname'				=>	$client->getFullname(),
    		'login'				=>	$a->getUsername(),
    		'passwd'			=>	$a->getPassword(),
    		'phone'				=>	$client->getTelephone(),
    		'fax'				=>	$client->getFax(),
    		'email'				=>	$client->getEmail(),
    		'address'			=>	$client->getAddress1(),
    		'city'				=>	$client->getCity(),
    		'state'				=>	$client->getState(),
            'description'       =>  "Created using FOSSBilling.",
        ];

        // We don't want to send these data if they are empty. Plesk will throw an error.
        $client->getCountry() ? $props['country'] = $client->getCountry() : null;

    	if ($a->getReseller()) {
    		$result = $this->_client->reseller()->create($props);
    	} else {
            $result = $this->_client->customer()->create($props);
    	}

        return true;
    }

    private function _createClientProps(Server_Account $a) {
        $props = [
            'cname'				=>	$client->getCompany(),
            'pname'				=>	$client->getFullname(),
    		'login'				=>	$a->getUsername(),
    		'passwd'			=>	$a->getPassword(),
    		'phone'				=>	$client->getTelephone(),
    		'fax'				=>	$client->getFax(),
    		'email'				=>	$client->getEmail(),
    		'address'			=>	$client->getAddress1(),
    		'city'				=>	$client->getCity(),
    		'state'				=>	$client->getState(),
        ];

        // Sending the country name blank causes an error. So we won't assign it to our request if it's blank.
        $client->getCountry() ? $props['country'] = $client->getCountry() : null;

        return $props;
    }
}
