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

use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

/**
 * cPanel API
 * @see https://api.docs.cpanel.net/whm/introduction
 */
class Server_Manager_Whm extends Server_Manager
{
	public function init()
    {
        if(empty($this->_config['host'])) {
            throw new Server_Exception('Server manager "cPanel WHM" is not configured properly. Hostname is not set');
        }

        if(empty($this->_config['username'])) {
            throw new Server_Exception('Server manager "cPanel WHM" is not configured properly. Username is not set');
        }

        if(empty($this->_config['password']) && empty($this->_config['accesshash'])) {
            throw new Server_Exception('Server manager "cPanel WHM" is not configured properly. Authentication is not set');
        }
	}

    public function getLoginUrl()
    {
        $host = $this->_config['host'];
        return 'http://'.$host.'/cpanel';
    }

    public function getResellerLoginUrl()
    {
        $host = $this->_config['host'];
        return 'http://'.$host.'/whm';
    }
    
    public static function getForm()
    {
        return array(
            'label'	=>  'WHM (cPanel)',
			'form' => [
                'credentials' => [
                    'fields' => [
                        [
                            'name' => 'username',
                            'type' => 'text',
                            'label' => 'Username',
                            'placeholder' => 'Username to connect to the server',
                            'required' => true,
                        ],
                        [
                            'name' => 'accesshash',
                            'type' => 'text',
                            'label' => 'Access hash',
                            'placeholder' => 'Access hash to connect to the server',
                            'required' => true,
                        ],
                    ],
                ],
            ]
        );
    }

    public function testConnection()
    {
        $json = $this->_request('version');
        return true;
    }

    public function synchronizeAccount(Server_Account $a)
    {
        $this->getLog()->info(sprintf('Synchronizing account %s %s with server', $a->getDomain(), $a->getUsername()));

        $action = 'accountsummary';
		$var_hash = Array(
            'user'      => $a->getUsername(),
        );
		$result = $this->_request($action, $var_hash);
        if(!isset($result->acct[0])) {
            error_log('Could not synchronize account with cPanel server. Account does not exist.');
            return $a;
        }

        $acc = $result->acct[0];

        $new = clone $a;
        $new->setSuspended($acc->suspended);
        $new->setDomain($acc->domain);
        $new->setUsername($acc->user);
        $new->setIp($acc->ip);
        
        return $new;
    }

	public function createAccount(Server_Account $a)
    {
        $this->getLog()->info('Creating account '.$a->getUsername());

        $client = $a->getClient();
        $package = $a->getPackage();

        $this->_checkPackageExists($package, true);

        $action = 'createacct';
        $var_hash = array(
			'username'		=> $a->getUsername(),
            'domain'		=> $a->getDomain(),
			'password'		=> $a->getPassword(),
			'contactemail'  => $client->getEmail(),
			'plan'			=> $this->_getPackageName($package),
        	'useregns'		=> 0,
        );
            
        if($a->getReseller()) {
            $var_hash['reseller'] = 1;
        }
        
        $json = $this->_request($action, $var_hash);
        $result = ($json->result[0]->status == 1);

        // if this account is reseller account set ACL list
        if($result && $a->getReseller()) {

            $params = array(
                'user'          =>  $a->getUsername(),
                'makeowner'     =>  0,
            );
            $this->_request('setupreseller', $params);

            $params = array(
                'reseller'  =>  $a->getUsername(),
                'acllist'   =>  $package->getAcllist(),
            );
            $this->_request('setacls', $params);
        }

        return $result;
	}

	public function suspendAccount(Server_Account $a)
    {
        $this->getLog()->info('Suspending account '.$a->getUsername());

        $action = 'suspendacct';
		$var_hash = Array(
            'user'      => $a->getUsername(),
            'reason'    => $a->getNote(),
        );

		$this->_request($action, $var_hash);
        return true;
	}

	public function unsuspendAccount(Server_Account $a)
    {
        $this->getLog()->info('Activating account '.$a->getUsername());

        $action = 'unsuspendacct';
		$var_hash = Array(
            'user'      => $a->getUsername(),
        );

		$this->_request($action, $var_hash);
        return true;
	}

	public function cancelAccount(Server_Account $a)
    {
        $this->getLog()->info('Canceling account '.$a->getUsername());

        $action = 'removeacct';
		$var_hash = Array(
			'user'      => $a->getUsername(),
			'keepdns'   => 0,
		);

		$this->_request($action, $var_hash);
        return true;
	}

	public function changeAccountPackage(Server_Account $a, Server_Package $p)
    {
        $this->getLog()->info('Changing account '.$a->getUsername().' package');
        $this->_checkPackageExists($p, true);
        
		$var_hash = array(
			'user'              => $a->getUsername(),
			'pkg'               => $this->_getPackageName($p),
		);

		$this->_request('changepackage', $var_hash);
        return true;
    }

    public function changeAccountPassword(Server_Account $a, $new)
    {
        $this->getLog()->info('Changing account '.$a->getUsername().' password');

        $action = 'passwd';

		$var_hash = array(
			'user'              => $a->getUsername(),
			'pass'              => $new,
			'db_pass_update'	=> false,
		);

		$result = $this->_request($action, $var_hash);
        if(isset($result->passwd[0]) && $result->passwd[0]->status == 0) {
            throw new Server_Exception($result->passwd[0]->statusmsg);
        }
        return true;
    }

    public function changeAccountUsername(Server_Account $a, $new)
    {
        $this->getLog()->info('Changing account '.$a->getUsername().' username');
        
        $action = 'modifyacct';
		$var_hash = array(
            'user'      => $a->getUsername(),
			'newuser'   => $new,
		);

		$this->_request($action, $var_hash);
        return true;
    }
    
    public function changeAccountDomain(Server_Account $a, $new)
    {
        $this->getLog()->info('Changing account '.$a->getUsername().' domain');

        $action = 'modifyacct';
		$var_hash = array(
            'user'     => $a->getUsername(),
			'domain'   => $new,
		);

		$this->_request($action, $var_hash);
        return true;
    }

    public function changeAccountIp(Server_Account $a, $new)
    {
        $this->getLog()->info('Changing account '.$a->getUsername().' ip');

        $action = 'setsiteip';
		$var_hash = array(
			'domain'    => $a->getDomain(),
			'ip'        => $new,
		);

		$this->_request($action, $var_hash);
        return true;
    }

    /**
     * Check if Package exists
     * @param Server_Package $package
     * @return bool
     */
    private function _checkPackageExists(Server_Package $package, $create = false)
    {
        $name = $this->_getPackageName($package);

        $json = $this->_request('listpkgs');
        $packages = $json->package;

        $exists = false;
        foreach ($packages as $p) {
            if($p->name == $name) {
                $exists = true;
                break;
            }
        }

        if(!$create) {
            return $exists;
        }

        if (!$exists) {
        	$var_hash['name']           = $name;
			$var_hash['quota']			= $package->getQuota();
            $var_hash['bwlimit']		= $package->getBandwidth();
			$var_hash['maxsub']			= $package->getMaxSubdomains();
			$var_hash['maxpark']		= $package->getMaxParkedDomains();
			$var_hash['maxaddon']		= $package->getMaxDomains();
			$var_hash['maxftp']			= $package->getMaxFtp();
			$var_hash['maxsql']			= $package->getMaxSql();
			$var_hash['maxpop']			= $package->getMaxPop();
            
			$var_hash['cgi']			= $package->getCustomValue('cgi');
			$var_hash['frontpage']		= $package->getCustomValue('frontpage');
            $var_hash['cpmod']			= $package->getCustomValue('cpmod');
			$var_hash['maxlst']			= $package->getCustomValue('maxlst');
            $var_hash['hasshell']		= $package->getCustomValue('hasshell');

            $this->_request('addpkg', $var_hash);
        }

        return $exists;
    }

    private function _getPackageName(Server_Package $package)
    {
        $name = $package->getName();
        $name = $this->_config['username'].'_'.$name;
        
        return $name;
    }

	private function modifyAccountPackage(Server_Account $a, Server_Package $p)
    {
        $this->getLog()->info('Midifying account '.$a->getUsername());

        $package = $p;
        $action = 'modifyacct';

		$var_hash = array(
			'user'		=> $a->getUsername(),
			'domain'	=> $a->getDomain(),
			'HASCGI'	=> $package->getHasCgi(),
			'CPTHEME'	=> $package->getTheme(),
			'LANG'		=> $package->getLanguage(),
			'MAXPOP'	=> $package->getMaxPop(),
			'MAXFTP'	=> $package->getMaxFtp(),
			'MAXLST'	=> $package->getMaxEmailLists(),
			'MAXSUB'	=> $package->getMaxSubdomains(),
			'MAXPARK'	=> $package->getMaxParkedDomains(),
			'MAXADDON'	=> $package->getMaxAddons(),
			'MAXSQL'	=> $package->getMaxSql(),
			'shell'		=> $package->getHasShell(),
		);

		$this->_request($action, $var_hash);
        return true;
	}

    public function getPkgs()
    {
        $pkgs = $this->_request('listpkgs');

        $return = array();
        $i = 0;
        foreach ($pkgs->package as $pkg)
        {
            $return[$i]['title'] = $pkg->name;
            $return[$i]['name'] = $pkg->name;
            $return[$i]['feature_list'] = $pkg->FEATURELIST;
            $return[$i]['theme'] = $pkg->CPMOD;
            $return[$i]['quota'] = $pkg->QUOTA;
            $return[$i]['bandwidth'] = $pkg->BWLIMIT;
            $return[$i]['max_ftp'] = $pkg->MAXFTP;
            $return[$i]['max_sql'] = $pkg->MAXSQL;
            $return[$i]['max_emails'] = $pkg->MAXLST;
            $return[$i]['max_sub'] = $pkg->MAXSUB;
            $return[$i]['max_pop'] = $pkg->MAXPOP;
            $return[$i]['max_park'] = $pkg->MAXPARK;
            $return[$i]['max_addon'] = $pkg->MAXADDON;

            $return[$i]['has_shell'] = ($pkg->HASSHELL == 'n' ? 0 : 1);
            $return[$i]['has_ip'] = ($pkg->IP == 'n' ? 0 : 1);
            $return[$i]['has_cgi'] = ($pkg->CGI == 'n' ? 0 : 1);
            $return[$i]['has_frontpage'] = ($pkg->FRONTPAGE == 'n' ? 0 : 1);

            $return[$i]['free_registration'] = 0;
            $return[$i]['free_transfer'] = 0;
            $return[$i++]['free_renewal'] = 0;
        }

        return $return;
    }

    /**
     * @param string $action
     */
    private function _request($action, $params = array())
    {
        $client = $this->getHttpClient()->withOptions([
            'verify_peer'   => false,
            'verify_host'   => false,
            'timeout'       => 30
        ]);

        $url =  ($this->_config['secure'] ? 'https' : 'http') . '://' . $this->_config['host'] . ':' . $this->_config['port'] . '/json-api/' . $action;
        $username = $this->_config['username'];
        $accesshash = $this->_config['accesshash'];
        $password = $this->_config['password'];
        $authstr = (!empty($accesshash)) ? 'WHM ' . $username . ':' . $accesshash  
                                         : 'Basic ' . $username .':'. $password;

        $this->getLog()->debug(sprintf('Requesting WHM server action "%s" with params "%s" ', $action, print_r($params, true)));
        
        try  {
            $response = $client->request('POST', $url, [
                'headers'   => [ 'Authorization' => $authstr ],
                'body'  => $params,
            ]);
        } catch (HttpExceptionInterface $error) {
            $e = throw new Server_Exception('HttpClientException: :error', [':error' => $error->getMessage()]);
            $this->getLog()->err($e);
        }
        $body = $response->getContent();
        $json = json_decode($body);

        if(!is_object($json)) {
            $msg = sprintf('Function call "%s" response is not valid, body: %s', $action, $body);
            $this->getLog()->crit($msg);
            throw new Server_Exception($msg);
        }
        if(isset($json->cpanelresult) && isset($json->cpanelresult->error)) {
            $msg = sprintf('WHM server response error calling action %s: "%s"', $action, $json->cpanelresult->error);
            $this->getLog()->crit($msg);
            throw new Server_Exception($msg);
        }
        if(isset($json->data) && isset($json->data->result) && $json->data->result == '0') {
            $msg = sprintf('WHM server response error calling action %s: "%s"', $action, $json->data->reason);
            $this->getLog()->crit($msg);
            throw new Server_Exception($msg);
        }
        if(isset($json->result) && is_array($json->result) && $json->result[0]->status == 0) {
            $msg = sprintf('WHM server response error calling action %s: "%s"',$action, $json->result[0]->statusmsg);
            $this->getLog()->crit($msg);
            throw new Server_Exception($msg);
        }
        if(isset($json->status) && $json->status != '1') {
            $msg = sprintf('WHM server response error calling action %s: "%s"',$action, $json->statusmsg);
            $this->getLog()->crit($msg);
            throw new Server_Exception($msg);
        }

        return $json;
    }
}