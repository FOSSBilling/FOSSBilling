<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH. All Rights Reserved.

/**
 * Client for Plesk API-RPC
 */
class Server_Manager_Plesk extends Server_Manager
{public function init() {
        if (!extension_loaded('curl')) {
            throw new Server_Exception('cURL extension is not enabled');
        }

	}

    public static function getForm()
    {
        return array(
            'label'     =>  'Plesk',
        );
    }

	public function getLoginUrl()
	{
        $protocol = 'https://';
		if (!$this->_config['secure']) {
            $protocol = 'http://';
        }
        return $protocol.$this->_config['host'] . ':8443/enterprise/control/agent.php';
	}

    public function getResellerLoginUrl()
    {
        return $this->getLoginUrl();
    }
    /**
     * Perform API request
     *
     * @param string $request
     * @return string
     */
    

    /**
     * Retrieve list of headers needed for request
     *
     * @return array
     */
   

    public function testConnection()
    {
    	$params = array (
    		'server'	=>	array(
    			'get'	=>	array(
    				'stat'	=>	'',
    			),
    		),
    	);

        $response = $this->_makeRequest($params);

        if (isset($response->server->get->result->status) && $response->server->get->result->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->client->get->result->errcode . ' - ' .
    									   $response->client->get->result->errtext);
    	}


    	if (isset($response->server->get->result->status) && $response->server->get->result->status == 'ok') {
    			return true;
    	}

    	throw new Server_Exception('Connection to server failed');

    	return false;
    }
    
    public function synchronizeAccount(Server_Account $a)
    {
        $this->getLog()->info('Synchronizing account with server '.$a->getUsername());
        return $a;
    }

    public function createAccount(Server_Account $a)
    {
    	$this->getLog()->info('Creating account '.$a->getUsername());

    	if ($a->getReseller()) {
    		$ips = $this->_getIps();
    		foreach($ips['exclusive'] as $key => $ip) {
	    		if (!$ip['empty']) {
    				unset ($ips['exclusive'][$key]);
    			}
    		}

    		if (count($ips['exclusive']) == 0) {
    			throw new Server_Exception('Out of free IP adresses');
    		}

    		$ips['exclusive'] = array_values($ips['exclusive']);
    		$rand = array_rand($ips['exclusive']);
    		$a->setIp($ips['exclusive'][$rand]['ip']);
    	}

    	$id = $this->_creatClient($a);
        $client = $a->getClient();
    	if (!$id) {
    		throw new Server_Exception('Failed to create new account');
    	} else {
            $client->setId((string)$id);
    	}
        $this->setSubscription($a);

    	if ($a->getReseller()) {
    		$this->_setIp($a);
    		$this->_changeIpType($a);
    	}

    	if ($a->getReseller()) {
    		$this->_addNs($a, $domainId);
    	}

    	return true;
    }

    public function setSubscription(Server_Account $a)
    {
        $p = $a->getPackage();
        $params = array (
            'webspace'	=>	array(
                'add'	=>	array(
                    'gen_setup'	=>	array(
                        'name' => $a->getDomain(),
                        'owner-login'	=>	$a->getUsername(),
                        'htype'			=>	'vrt_hst',
                        'ip_address' => $a->getIp()
                    ),
                    'hosting' => array(
                        'vrt_hst'	=>	array(
                            'property1'	=>	array(
                                'name'	=>	'ftp_login',
                                'value'	=>	$a->getUsername(),
                            ),
                            'property2'	=>	array(
                                'name'	=>	'ftp_password',
                                'value'	=>	$a->getPassword(),
                            ),
                            'property3'	=>	array(
                                'name'	=>	'php',
                                'value'	=>	$p->getHasPhp(),
                            ),
                            'property4'	=>	array(
                                'name'	=>	'ssl',
                                'value'	=>	$p->getHasSsl(),
                            ),
                            'property5'	=>	array(
                                'name'	=>	'cgi',
                                'value'	=>	$p->getHasCgi(),
                            ),
                            'ip_address' => $a->getIp(),
                        ),
                    ),
                    'limits'	=>	array(
                        //The max_db node is optional. Specifies the maximum number of MySQL databases available for the client. Data type: string.
                        'limit1'	=>	array(
                            'name'	=>	'max_db',
                            'value'	=>	$p->getMaxSql() ? $p->getMaxSql() : 0,
                        ),	//The max_maillists node is optional. Specifies the maximum number of mailing lists available for the client. Data type: string.
                        'limit2'	=>	array(
                            'name'	=>	'max_maillists',
                            'value'	=>	$p->getMaxEmailLists() ? $p->getMaxEmailLists() : 0,
                        ),	//The max_box node is optional. Specifies the maximum number of mailboxes allowed for the client. Data type: string.
                        'limit3' =>	array(
                            'name'	=>	'max_box',
                            'value'	=>	$p->getMaxPop() ? $p->getMaxPop() : 0,
                        ),	//The max_traffic node is optional. Specifies the limit (in bytes) on the traffic for the client. Data type: string.
                        'limit4' =>	array(
                            'name'	=>	'max_traffic',
                            'value'	=>	$p->getBandwidth() ? $p->getBandwidth() * 1024 * 1024: 0,
                        ),	//The disk_space node is optional. Specifies the limit on disk space (in bytes) for the client. Data type: string.
                        'limit5' =>	array(
                            'name'	=>	'disk_space',
                            'value'	=>	$p->getQuota() ? $p->getQuota() * 1024 * 1024 : 0,
                        ),	//The max_subdom node is optional. Specifies the maximum number of subdomains available for the client. Data type: string.
                        'limit6' =>	array(
                            'name'	=>	'max_subdom',
                            'value'	=>	$p->getMaxSubdomains() ? $p->getMaxSubdomains() : 0,
                        ),	//The max_dom node is optional. Specifies the limit on the number of domains for the client. Data type: string.
                        'limit7' => array(
                            'name'	=>	'max_subftp_users',
                            'value'	=>	$p->getMaxFtp() ? $p->getMaxFtp() : 0,
                        ),
                    ),
                    'permissions'	=>	array(
                        //The manage_subdomains node is optional. It allows/disallows the client to manage subdomains. Data type: Boolean.
                        'permission1'	=>	array(
                            'name'	=>	'manage_subdomains',
                            'value'	=>	$p->getMaxSubdomains() ? 'true' : 'false',
                        ),	//The change_limits node is optional. It allows/disallows the client to change the domain limits. Data type: Boolean.
                        'permission3'	=>	array(
                            'name'	=>	'manage_dns',
                            'value'	=>	'true'
                        ),	//The manage_crontab node is optional. It allows/disallows the client to manage the task scheduler. Data type: Boolean.
                        'permission4'	=>	array(
                            'name'	=>	'manage_crontab',
                            'value'	=>	$p->getHasCron() ? 'true' : 'false',
                        ),	//The manage_anonftp node is optional. It allows/disallows the client to manage Anonymous FTP. Data type: Boolean.
                        'permission5'	=>	array(
                            'name'	=>	'manage_anonftp',
                            'value'	=>	$p->getHasAnonymousFtp() ? 'true' : 'false',
                        ),	//The manage_sh_access node is optional. It allows/disallows the client to use shell access and provide it to users. Data type: Boolean.
                        'permission6'	=>	array(
                            'name'	=>	'manage_sh_access',
                            'value'	=>	$p->getHasShell() ? 'true' : 'false',
                        ),	//The manage_maillists node is optional. It allows/disallows the client to manage mailing lists. Data type: Boolean.
                        'permission7'	=>	array(
                            'name'	=>	'manage_maillists',
                            'value'	=>	$p->getMaxEmailLists() ? 'true' : 'false',
                        ),
                        'permission8'	=>	array(
                            'name'	=>	'remote_access_interface',
                            'value'	=>	'true'
                        ),	//The create_domains node is optional. It allows/disallows the client to create domains. Data type: Boolean.
                        'permission9'	=>	array(
                            'name'	=>	'create_domains',
                            'value'	=>	'true',
                        ),	//The manage_phosting node is optional. It allows/disallows the client to manage physical hosting. Data type: Boolean.
                        'permission10'	=>	array(
                            'name'	=>	'manage_phosting',
                            'value'	=>	'true',
                        ),	//The manage_quota node is optional. It allows/disallows the client to change the hard disk limit. Data type: Boolean.
                        'permission11'	=>	array(
                            'name'	=>	'manage_quota',
                            'value'	=>	$a->getReseller() ? 'true' : 'false',
                        ),	//The manage_not_chroot_shell node is optional. It allows/disallows the client to manage shell without changing the mount point of the UNIX root. Data type: Boolean.
                        'permission12'	=>	array(
                            'name'	=>	'manage_not_chroot_shell',
                            'value'	=>	$p->getHasShell() ? 'true' : 'false',
                        ),	//The manage_domain_aliases node is optional. It allows/disallows the client to manage domain aliases. Is used in Plesk for UNIX only. Data type: Boolean. Supported in API RPC 1.4.0.0 and higher.
                        'permission13'	=>	array(
                            'name'	=>	'manage_domain_aliases',
                            'value'	=>	'true',
                        ),	//The manage_subftp node is optional. It allows/disallows the client to manage additional FTP accounts (with access to the specified domain folders only) created on domains. Data type: Boolean. Supported beginning with version 1.4.1.0 of API RPC.
                        'permission14'	=>	array(
                            'name'	=>	'manage_subftp',
                            'value'	=>	$p->getMaxFtp() ? 'true' : 'false',
                        ),	//The manage_spamfilter node is optional. It allows/disallows Plesk Client to manage spam filter settings. Data type: Boolean. Makes sense for Plesk for UNIX only. The feature is supported by API RPC 1.4.2.0 and later.
                        'permission15'	=>	array(
                            'name'	=>	'manage_spamfilter',
                            'value'	=>	$p->getHasSpamFilter() ? 'true' : 'false',
                        ),
                        'permission16'	=>	array(
                            'name'	=>	'create_clients',
                            'value'	=>	$a->getReseller() ? 'true' : 'false',
                        ),
                    ),
                ),
            ),
        );

        $response = $this->_makeRequest($params);
        if (isset($response->system->status) && $response->system->status == 'error') {
            throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
                $response->system->errtext);
        }

    }

    public function createServicePlan(Server_Account $a)
    {
        
    }

    public function updateSubscription(Server_Account $a)
    {
        $p      = $a->getPackage();
        $params = array(
            'webspace' => array(
                'set' => array(
                    'filter' => array(
                        'name' => $a->getDomain(),
                    ),
                    'values' => array(
                        'limits'      => array(
                            //The max_db node is optional. Specifies the maximum number of MySQL databases available for the client. Data type: string.
                            'limit1' => array(
                                'name'  => 'max_db',
                                'value' => $p->getMaxSql() ? $p->getMaxSql() : 0,
                            ),    //The max_maillists node is optional. Specifies the maximum number of mailing lists available for the client. Data type: string.
                            'limit2' => array(
                                'name'  => 'max_maillists',
                                'value' => $p->getMaxEmailLists() ? $p->getMaxEmailLists() : 0,
                            ),    //The max_box node is optional. Specifies the maximum number of mailboxes allowed for the client. Data type: string.
                            'limit3' => array(
                                'name'  => 'max_box',
                                'value' => $p->getMaxPop() ? $p->getMaxPop() : 0,
                            ),    //The max_traffic node is optional. Specifies the limit (in bytes) on the traffic for the client. Data type: string.
                            'limit4' => array(
                                'name'  => 'max_traffic',
                                'value' => $p->getBandwidth() ? $p->getBandwidth() * 1024 * 1024 : 0,
                            ),    //The disk_space node is optional. Specifies the limit on disk space (in bytes) for the client. Data type: string.
                            'limit5' => array(
                                'name'  => 'disk_space',
                                'value' => $p->getQuota() ? $p->getQuota() * 1024 * 1024 : 0,
                            ),    //The max_subdom node is optional. Specifies the maximum number of subdomains available for the client. Data type: string.
                            'limit6' => array(
                                'name'  => 'max_subdom',
                                'value' => $p->getMaxSubdomains() ? $p->getMaxSubdomains() : 0,
                            ),    //The max_dom node is optional. Specifies the limit on the number of domains for the client. Data type: string.
                            'limit7' => array(
                                'name'  => 'max_subftp_users',
                                'value' => $p->getMaxFtp() ? $p->getMaxFtp() : 0,
                            ),
                        ),
                        'hosting'     => array(
                            'vrt_hst' => array(
                                'property1'  => array(
                                    'name'  => 'ftp_login',
                                    'value' => $a->getUsername(),
                                ),
                                'property2'  => array(
                                    'name'  => 'ftp_password',
                                    'value' => $a->getPassword(),
                                ),
                                'ip_address' => $a->getIp(),
                            ),
                        ),
                        'permissions' => array(
                            //The manage_subdomains node is optional. It allows/disallows the client to manage subdomains. Data type: Boolean.
                            'permission1'  => array(
                                'name'  => 'manage_subdomains',
                                'value' => $p->getMaxSubdomains() ? 'true' : 'false',
                            ),    //The change_limits node is optional. It allows/disallows the client to change the domain limits. Data type: Boolean.
                            'permission3'  => array(
                                'name'  => 'manage_dns',
                                'value' => 'true'
                            ),    //The manage_crontab node is optional. It allows/disallows the client to manage the task scheduler. Data type: Boolean.
                            'permission4'  => array(
                                'name'  => 'manage_crontab',
                                'value' => $p->getHasCron() ? 'true' : 'false',
                            ),    //The manage_anonftp node is optional. It allows/disallows the client to manage Anonymous FTP. Data type: Boolean.
                            'permission5'  => array(
                                'name'  => 'manage_anonftp',
                                'value' => $p->getHasAnonymousFtp() ? 'true' : 'false',
                            ),    //The manage_sh_access node is optional. It allows/disallows the client to use shell access and provide it to users. Data type: Boolean.
                            'permission6'  => array(
                                'name'  => 'manage_sh_access',
                                'value' => $p->getHasShell() ? 'true' : 'false',
                            ),    //The manage_maillists node is optional. It allows/disallows the client to manage mailing lists. Data type: Boolean.
                            'permission7'  => array(
                                'name'  => 'manage_maillists',
                                'value' => $p->getMaxEmailLists() ? 'true' : 'false',
                            ),
                            'permission9'  => array(
                                'name'  => 'create_domains',
                                'value' => 'true',
                            ),    //The manage_phosting node is optional. It allows/disallows the client to manage physical hosting. Data type: Boolean.
                            'permission10' => array(
                                'name'  => 'manage_phosting',
                                'value' => 'true',
                            ),    //The manage_quota node is optional. It allows/disallows the client to change the hard disk limit. Data type: Boolean.
                            'permission11' => array(
                                'name'  => 'manage_quota',
                                'value' => $a->getReseller() ? 'true' : 'false',
                            ),    //The manage_not_chroot_shell node is optional. It allows/disallows the client to manage shell without changing the mount point of the UNIX root. Data type: Boolean.
                            'permission12' => array(
                                'name'  => 'manage_not_chroot_shell',
                                'value' => $p->getHasShell() ? 'true' : 'false',
                            ),    //The manage_domain_aliases node is optional. It allows/disallows the client to manage domain aliases. Is used in Plesk for UNIX only. Data type: Boolean. Supported in API RPC 1.4.0.0 and higher.
                            'permission13' => array(
                                'name'  => 'manage_domain_aliases',
                                'value' => 'true',
                            ),    //The manage_subftp node is optional. It allows/disallows the client to manage additional FTP accounts (with access to the specified domain folders only) created on domains. Data type: Boolean. Supported beginning with version 1.4.1.0 of API RPC.
                            'permission14' => array(
                                'name'  => 'manage_subftp',
                                'value' => $p->getMaxFtp() ? 'true' : 'false',
                            ),    //The manage_spamfilter node is optional. It allows/disallows Plesk Client to manage spam filter settings. Data type: Boolean. Makes sense for Plesk for UNIX only. The feature is supported by API RPC 1.4.2.0 and later.
                            'permission15' => array(
                                'name'  => 'manage_spamfilter',
                                'value' => $p->getHasSpamFilter() ? 'true' : 'false',
                            ),
                        ),
                    ),
                ),
            ),
        );

        $response = $this->_makeRequest($params);
        if (isset($response->system->status) && $response->system->status == 'error') {
            throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
                $response->system->errtext);
        }
    }

    public function deleteSubscription(Server_Account $a)
    {
        $params = array (
            'webspace'	=>	array(
                'del'	=>	array(
                    'filter' => array(
                        'name' => $a->getDomain(),
                    ),
                ),
            ),
        );

        $response = $this->_makeRequest($params);
        if (isset($response->system->status) && $response->system->status == 'error') {
            throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
                $response->system->errtext);
        }

    }

    public function suspendAccount(Server_Account $a, $suspend = true)
    {
    	if ($a->getReseller()) {
    		$type = 'reseller';
    		$genInfo = 'gen-info';
    	} else {
    		$type = 'customer';
    		$genInfo = 'gen_info';
    	}

    	$params = array (
    		$type	=>	array(
    			'set'	=>	array(
    				'filter'	=>	array(
    					'login'	=>	$a->getUsername(),
    				),
    				'values'	=>	array(
    					$genInfo	=>	array(
    						'status'	=>	$suspend ? 16 : 0,
    					),
    				),
    			),
    		),
    	);

    	$response = $this->_makeRequest($params);

    	if (isset($response->system->status) && $response->system->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
    									   $response->system->errtext);
    	}

    	if (isset($response->{$type}->set->result->status) && $response->{$type}->set->result->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->{$type}->set->result->errcode . ' - ' .
    									   $response->{$type}->set->result->errtext);
   		}

   		if (isset($response->{$type}->set->result->status) && $response->{$type}->set->result->status == 'ok') {
   			return true;
   		} else {
   			return false;
   		}
    }

    public function unsuspendAccount(Server_Account $a)
    {
    	return $this->suspendAccount($a, false);
    }

    public function cancelAccount(Server_Account $a)
    {
    	if ($a->getReseller()) {
    		$type = 'reseller';
    	} else {
    		$type = 'customer';
    	}
		$params = array(
    		$type	=>	array(
    			'del'	=>	array(
    				'filter'	=>	array(
    					'login'	=>	$a->getUsername(),
    				),
    			),
    		),
    	);

        $response = $this->_makeRequest($params);

    	if (isset($response->system->status) && $response->system->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
    									   $response->system->errtext);
   		}

   		if (isset($response->{$type}->del->result->status) && $response->{$type}->del->result->status == 'error') {
   			throw new Server_Exception('Plesk error:' . $response->{$type}->del->result->errcode . ' - ' .
   										   $response->{$type}->del->result->errtext);
   		}

   		if (isset($response->{$type}->del->result->status) && $response->{$type}->del->result->status == 'ok') {
   			return true;
   		} else {
   			return false;
   		}
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
    	if ($a->getReseller()) {
    		$type = 'reseller';
    		$genInfo = 'gen-info';
    	} else {
    		$type = 'customer';
    		$genInfo = 'gen_info';
    	}

        $params = array(
    		$type	=>	array(
    			'set'	=>	array(
    				'filter'	=>	array(
    					'login'	=>	$a->getUsername(),
    				),
    				'values'	=>	array(
    					$genInfo	=>	array(
    						'passwd'	=>	$a->getPassword(),
    					),
    				),
    			),
    		),
    	);

        $response = $this->_makeRequest($params);

        if (isset($response->system->status) && $response->system->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
    									   $response->system->errtext);
   		}

    	if (isset($response->{$type}->set->result->status) && $response->{$type}->set->result->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->{$type}->set->result->errcode . ' - ' .
    									   $response->{$type}->set->result->errtext);
   		}

   		if (isset($response->{$type}->set->result->status) && $response->{$type}->set->result->status == 'ok') {
   			return true;
   		} else {
   			return false;
   		}
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
    
    private function _makeRequest($params) {
    	$headers = array(
    		'HTTP_AUTH_LOGIN: ' . $this->_config['username'],
    		'HTTP_AUTH_PASSWD: ' . $this->_config['password'],
    		'HTTP_PRETTY_PRINT: TRUE',
    		'Content-Type: text/xml'
    	);

    	$xml = $this->_arrayToXml($params, new SimpleXMLElement('<packet />'))
    				->asXML();
        error_log($xml);
    	$ch = curl_init ();
    	curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
    	curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
    	curl_setopt ($ch, CURLOPT_HTTPHEADER, $headers);
    	curl_setopt ($ch, CURLOPT_URL, $this->getLoginUrl());
    	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt ($ch, CURLOPT_POST, 1);
    	curl_setopt ($ch, CURLOPT_POSTFIELDS, '$params');

		$result = curl_exec($ch);

		if (curl_errno ($ch)) {
			throw new Server_Exception('cURL error: ' . curl_errno ($ch) . ' - ' . curl_error ($ch));
		}

		return $this->_parseResponse($result);
  }

    private function _arrayToXml(array $arr, SimpleXMLElement $xml) {
    	$numbers = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17);
    	foreach ($arr as $k => $v) {
        	if (is_array($v)) {
				$this->_arrayToXml($v, $xml->addChild(str_replace($numbers, '', $k)));
        	} else {
        		$xml->addChild(str_replace($numbers, '', $k), $v);
        	}
        }
		return $xml;
    }

    private function _parseResponse($result) {
		try {
            $xml = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (Exception $e) {
            throw new Server_Exception('simpleXmlException: '.$e->getMessage());
        }

        return $xml;
    }

    /**
     *
     * Creates new client account
     * @param Server_Account $a
     * @throws Server_Exception
     * @return integer client's plesk id
     */
    private function _creatClient(Server_Account $a) {
    	if ($a->getReseller()) {
    		$type = 'reseller';
    		$genInfo = 'gen-info';
    	} else {
    		$type = 'customer';
    		$genInfo = 'gen_info';
    	}

        $client = $a->getClient();
        $p = $a->getPackage();
    	$params = array(
    		$type	=>	array(
    			'add'		=>	array(
    				$genInfo	=>	array (
    					'cname'				=>	$client->getCompany(),
    					'pname'				=>	$client->getFullname(),
    					'login'				=>	$a->getUsername(),
    					'passwd'			=>	$a->getPassword(),
    					'status'			=>	0,					//active
    					'phone'				=>	$client->getTelephone(),
    					'fax'				=>	$client->getFax(),
    					'email'				=>	$client->getEmail(),
    					'address'			=>	$client->getAddress1(),
    					'city'				=>	$client->getCity(),
    					'state'				=>	$client->getState(),
    					'country'			=>	$client->getCountry(),
    				),
    			),
    		),
    	);

    	$response = $this->_makeRequest($params);

    	if (isset($response->system->status) && $response->system->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
    									   $response->system->errtext);
   		}

   		if (isset($response->{$type}->add->result->status) && $response->{$type}->add->result->status == 'error') {
   			throw new Server_Exception('Plesk error: ' . $response->{$type}->add->result->errcode . ' - ' .
   										   $response->{$type}->add->result->errtext);
   		}

   		if (isset($response->{$type}->add->result->status) && $response->{$type}->add->result->status == 'ok') {
   			return $response->{$type}->add->result->id;
   		}

   		return 0;
    }

    private function _getIps() {
    	$params = array(
    		'ip'	=>	array(
    			'get'	=>	'',
    		),
    	);

    	$response = $this->_makeRequest($params);
    	if (isset($response->system->status) && $response->system->status = 'error') {
			throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
    									   $response->system->errtext);
    	}
        if (isset($response->ip->get->result->status) && $response->ip->get->result->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->ip->get->result->errcode . ' - ' .
    									   $response->ip->get->result->errtext);
   		}

		$ips = array('shared' => array(), 'exclusive' => array());
		foreach($response->ip->get->result->addresses->ip_info as $ip) {
			$ips[(string)$ip->type][] = array(
									'ip'		=>	(string)$ip->ip_address,
									'empty'		=>	empty($ip->default) ? true : false,
								);
		}

		return $ips;
    }

    private function _setIp(Server_Account $a) {
        if ($a->getReseller()) {
    		$type = 'reseller';
    	} else {
    		$type = 'customer';
    	}

        $client = $a->getClient();
    	$params = array(
    		'customer'	=>	array(
    			'ippool_add_ip'	=>	array(
    				'client_id'		=>	$client->getId(),
    				'ip_address'	=>	$a->getIp(),
    			),
    		),
    	);

        $response = $this->_makeRequest($params);

        if (isset($response->system->status) && $response->system->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
    									   $response->system->errtext);
   		}

    	if (isset($response->{$type}->ippool_add_ip->result->status) && $response->{$type}->ippool_add_ip->result->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->{$type}->ippool_add_ip->result->errcode . ' - ' .
    									   $response->{$type}->ippool_add_ip->result->errtext);
   		}

   		if (isset($response->{$type}->ippool_add_ip->result->status) && $response->{$type}->ippool_add_ip->result->status == 'ok') {
   			return true;
   		} else {
   			return false;
   		}
    }

    private function _addNs(Server_Account $a, $domainId) {
    	$params = array (
    		'dns'	=>	array(
    		),
    	);
    	if ($a->getNs1()) {
			$params['dns']['add_rec1']	=	array(
				'domain_id'	=>	$domainId,
				'type'		=>	'NS',
				'host'		=>	'',
				'value'		=>	$a->getNs1(),
    		);
    	}
    	if ($a->getNs2()) {
			$params['dns']['add_rec2']	=	array(
				'domain_id'	=>	$domainId,
				'type'		=>	'NS',
				'host'		=>	'',
				'value'		=>	$a->getNs2(),
    		);
    	}
    	if ($a->getNs3()) {
			$params['dns']['add_rec3']	=	array(
				'domain_id'	=>	$domainId,
				'type'		=>	'NS',
				'host'		=>	'',
				'value'		=>	$a->getNs3(),
    		);
    	}
    	if ($a->getNs4()) {
			$params['dns']['add_rec4']	=	array(
				'domain_id'	=>	$domainId,
				'type'		=>	'NS',
				'host'		=>	'',
				'value'		=>	$a->getNs4(),
    		);
    	}

    	if (empty($params['dns'])) {
    		return true;
    	}

    	$oldNs = $this->_getNs($a, $domainId);
    	$this->_removeDns($oldNs);

        $response = $this->_makeRequest($params);

        if (isset($response->system->status) && $response->system->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
    									   $response->system->errtext);
   		}

   		foreach($response->dns->add_rec as $ns) {
    		if (isset($ns->result->status) && $ns->result->status == 'error') {
    			throw new Server_Exception('Plesk error: ' . $ns->result->errcode . ' - ' .
    										   $ns->result->errtext);
   			}
   		}

		return true;
    }

    private function _getNs(Server_Account $a, $domainId) {
    	$params = array(
    		'dns'	=>	array(
    			'get_rec'	=>	array(
    				'filter'	=>	array(
    					'domain_id'	=>	$domainId,
    				),
    			),
    		),
    	);

        $response = $this->_makeRequest($params);

        if (isset($response->system->status) && $response->system->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
    									   $response->system->errtext);
   		}

   		$ns = array();
   		foreach($response->dns->get_rec->result as $dns) {
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

        $response = $this->_makeRequest($params);

        if (isset($response->system->status) && $response->system->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
    									   $response->system->errtext);
   		}

   		foreach ($response->dns->del_rec->result as $result) {
    		if (isset($result->status) && $result->status == 'error') {
    			throw new Server_Exception('Plesk error: ' . $result->errcode . ' - ' .
    										   $result->errtext);
   			}
   		}

   		return true;
    }

    private function _modifyClient(Server_Account $a) {
    	if ($a->getReseller()) {
    		$type = 'reseller';
    		$genInfo = 'gen-info';
    	} else {
    		$type = 'customer';
    		$genInfo = 'gen_info';
    	}

        $client = $a->getClient();
    	$params = array(
    		$type	=>	array(
    			'set'		=>	array(
    				'filter'	=>	array(
    					'login'	=>	$a->getUsername(),
    				),
    				'values'	=>	array(
    					$genInfo	=>	array (
    						'cname'				=>	$client->getCompany(),
    						'pname'				=>	$client->getFullname(),
    						'login'				=>	$a->getUsername(),
    						'passwd'			=>	$a->getPassword(),
    						'status'			=>	0,					//active
    						'phone'				=>	$client->getTelephone(),
    						'fax'				=>	$client->getFax(),
    						'email'				=>	$client->getEmail(),
    						'address'			=>	$client->getAddress1(),
    						'city'				=>	$client->getCity(),
    						'state'				=>	$client->getState(),
    						'country'			=>	$client->getCountry(),
    					),
    				),
    			),
    		),
    	);

    	$response = $this->_makeRequest($params);

    	if (isset($response->system->status) && $response->system->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
    									   $response->system->errtext);
   		}

   		if (isset($response->{$type}->set->result->status) && $response->{$type}->set->result->status == 'error') {
   			throw new Server_Exception('Plesk error: ' . $response->{$type}->set->result->errcode . ' - ' .
   										   $response->{$type}->set->result->errtext);
   		}

   		if (isset($response->{$type}->set->result->status) && $response->{$type}->set->result->status == 'ok') {
   			return $response->{$type}->set->result->id;
   		}

   		return 0;
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

        $response = $this->_makeRequest($params);

        if (isset($response->system->status) && $response->system->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->system->errcode . ' - ' .
    									   $response->system->errtext);
   		}

    	if (isset($response->reseller->{'ippool-set-ip'}->result->status) &&
    		$response->reseller->{'ippool-set-ip'}->result->status == 'error') {
    		throw new Server_Exception('Plesk error: ' . $response->reseller->{'ippool-set-ip'}->result->errcode . ' - ' .
    									   $response->reseller->{'ippool-set-ip'}->result->errtext);
   		}

   		if (isset($response->reseller->{'ippool-set-ip'}->result->status) &&
   			$response->reseller->{'ippool-set-ip'}->result->status == 'ok') {
   			return true;
   		} else {
   			return false;
   		}
    }

    private $_host;
    private $_port;
    private $_protocol;
    private $_login;
    private $_password;
    private $_secretKey;

    /**
     * Create client
     *
     * @param string $host
     * @param int $port
     * @param string $protocol
     */
    public function __construct($host, $port = 8443, $protocol = 'https')
    {
        $this->_host = $host;
        $this->_port = $port;
        $this->_protocol = $protocol;
    }

    /**
     * Setup credentials for authentication
     *
     * @param string $login
     * @param string $password
     */
    public function setCredentials($login, $password)
    {
        $this->_login = $login;
        $this->_password = $password;
    }

    /**
     * Define secret key for alternative authentication
     *
     * @param string $secretKey
     */
    public function setSecretKey($secretKey)
    {
        $this->_secretKey = $secretKey;
    }

    /**
     * Perform API request
     *
     * @param string $request
     * @return string
     */
//    public function _makeRequest($request)
//    {
//        $curl = curl_init();
//
//        curl_setopt($curl, CURLOPT_URL, '"$this->_protocol://$this->_host:$this->_port/enterprise/control/agent.php"');
//        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($curl, CURLOPT_POST, true);
//        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
//        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
//        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->_getHeaders());
//        curl_setopt($curl, CURLOPT_POSTFIELDS, $this->_makeRequest($request));
//
//        $result = curl_exec($curl);
//
//        curl_close($curl);
//
//        return $result;
//    }

    /**
     * Retrieve list of headers needed for request
     *
     * @return array
     */
    private function _getHeaders()
    {
        $headers = array(
            "Content-Type: text/xml",
            "HTTP_PRETTY_PRINT: TRUE",
        );

        if ($this->_secretKey) {
            $headers[] = "KEY: $this->_secretKey";
        } else {
            $headers[] = "HTTP_AUTH_LOGIN: $this->_login";
            $headers[] = "HTTP_AUTH_PASSWD: $this->_password";
        }

        return $headers;
    }

}
