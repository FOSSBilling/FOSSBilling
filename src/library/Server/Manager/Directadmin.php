<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Server_Manager_Directadmin extends Server_Manager
{
    public function init()
    {
        if(empty($this->_config['host'])) {
            throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'DirectAdmin', ':missing' => 'hostname']);
        }
        
        if(empty($this->_config['username'])) {
            throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'DirectAdmin', ':missing' => 'username']);
        }
        
        if(empty($this->_config['password'])) {
            throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'DirectAdmin', ':missing' => 'password']);
        }
    }
    
    public static function getForm()
    {
        return array(
            'label' => 'DirectAdmin',
            'form' => [
                'credentials' => [
                    'fields' => [
                        [
                            'name' => 'username',
                            'type' => 'text',
                            'label' => 'Username',
                            'placeholder' => 'Username used to connect to the server',
                            'required' => true,
                        ],
                        [
                            'name' => 'password',
                            'type' => 'text',
                            'label' => 'Password / Login Key',
                            'placeholder' => 'Password or login key used to connect to the server',
                            'required' => true,
                        ]
                    ]
                ]
            ]
        );
    }
    
    public function getLoginUrl() : string
    {
        $protocol = $this->_config['secure'] ? 'https://' : 'http://';
        return $protocol . $this->_config['host'] . ':2222';
    }
    
    public function getResellerLoginUrl()
    {
        return $this->getLoginUrl();
    }

    public function generateUsername($domain_name)
    {
        // Username must be alphanumeric.
        $username = preg_replace('/[^A-Za-z0-9]/', '', $domain_name);

        // Username must start with a-z.
        $username = is_numeric(substr($username, 0, 1)) ? substr_replace($username, chr(random_int(97,122)), 0, 1) : $username;

        // Username must be at most 10 characters long, and sufficiently random to avoid collisons.
        $username = substr($username, 0, 7);
        $randnum = random_int(0, 99);
        return $username . $randnum;
    }
    
    public function testConnection()
    {
        $results = $this->_request('CMD_API_SHOW_RESELLER_IPS');
        return is_array($results);
    }
    
    public function synchronizeAccount(Server_Account $a)
    {
        return $a;
    }
    
    public function createAccount(Server_Account $a)
    {
        $ips = $this->getIps();
        if(empty($ips)) {
            throw new Server_Exception('There are no available IPs on this server.');
        }
        $ip = $ips[array_rand($ips)];
        
        $package = $a->getPackage();
        $client  = $a->getClient();
        
        $fields             = array();
        $fields['action']   = 'create';
        $fields['add']      = 'Submit';
        $fields['username'] = $a->getUsername();
        $fields['email']    = $client->getEmail();
        $fields['passwd']   = $a->getPassword();
        $fields['passwd2']  = $a->getPassword();
        $fields['domain']   = $a->getDomain();
        
        // do not create new package
        //        $packages = $this->getPackages();
        //        $fields['package'] = $package->getName();
        
        $fields['ip']     = $ip;
        $fields['notify'] = 'no';
        
        $fields['bandwidth'] = $package->getBandwidth(); //Amount of bandwidth User will be allowed to use. Number, in Megabytes
        if($package->getBandwidth() == 'unlimited') {
            $fields['ubandwidth'] = 'ON'; //ON or OFF. If ON, bandwidth is ignored and no limit is set
        }
        $fields['quota'] = $package->getQuota(); //Amount of disk space User will be allowed to use. Number, in Megabytes
        if($package->getQuota() == 'unlimited') {
            $fields['uquota'] = 'ON'; //ON or OFF. If ON, quota is ignored and no limit is set
        }
        $fields['vdomains'] = $package->getMaxDomains(); //Number of domains the User will be allowed to create
        if($package->getMaxDomains() == 'unlimited') {
            $fields['uvdomains'] = 'ON'; //ON or OFF. If ON, vdomains is ignored and no limit is set
        }
        $fields['nsubdomains'] = $package->getMaxSubdomains(); //Number of subdomains the User will be allowed to create
        if($package->getMaxSubdomains() == 'unlimited') {
            $fields['unsubdomains'] = 'ON'; //ON or OFF. If ON, nsubdomains is ignored and no limit is set
        }
        $fields['domainptr'] = $package->getMaxParkedDomains(); //Number of domain pointers the User will be allowed to create
        if($package->getMaxParkedDomains() == 'unlimited') {
            $fields['udomainptr'] = 'ON'; //ON or OFF Unlimited option for domainptr
        }
        $fields['nemails'] = $package->getMaxPop(); //Number of pop accounts the User will be allowed to create
        if($package->getMaxPop() == 'unlimited') {
            $fields['unemails'] = 'ON'; //ON or OFF Unlimited option for nemails
        }
        $fields['mysql'] = $package->getMaxSql(); //Number of MySQL databases the User will be allowed to create
        if($package->getMaxSql() == 'unlimited') {
            $fields['umysql'] = 'ON'; //ON or OFF Unlimited option for mysql
        }
        $fields['ftp'] = $package->getMaxFtp(); //Number of ftp accounts the User will be allowed to create
        if($package->getMaxFtp() == 'unlimited') {
            $fields['uftp'] = 'ON'; //ON or OFF Unlimited option for ftp
        }
        $fields['nemailf'] = $package->getCustomValue('nemailf'); //Number of forwarders the User will be allowed to create
        if($fields['nemailf'] == 'unlimited') {
            $fields['unemailf'] = 'ON'; //ON or OFF Unlimited option for nemailf
        }
        $fields['nemailml'] = $package->getCustomValue('nemailml'); //Number of mailing lists the User will be allowed to create
        if($fields['nemailml'] == 'unlimited') {
            $fields['unemailml'] = 'ON'; //ON or OFF Unlimited option for nemailml
        }
        $fields['nemailr'] = $package->getCustomValue('nemailr'); //Number of autoresponders the User will be allowed to create
        if($fields['nemailr'] == 'unlimited') {
            $fields['unemailr'] = 'ON'; //ON or OFF Unlimited option for nemailr
        }
        
        $fields['aftp']       = $package->getCustomValue('aftp') ? 'ON' : 'OFF'; //ON or OFF If ON, the User will be able to have anonymous ftp accounts.
        $fields['cgi']        = $package->getCustomValue('cgi') ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have the ability to run cgi scripts in their cgi-bin.
        $fields['php']        = $package->getCustomValue('php') ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have the ability to run php scripts.
        $fields['spam']       = $package->getCustomValue('spam') ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have the ability to run scan email with SpamAssassin.
        $fields['cron']       = $package->getCustomValue('cron') ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have the ability to creat cronjobs.
        $fields['catchall']   = $package->getCustomValue('catchall') ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have the ability to enable and customize a catch-all email (*@domain.com).
        $fields['ssl']        = $package->getCustomValue('ssl') ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have the ability to access their websites through secure https://.
        $fields['ssh']        = $package->getCustomValue('ssh') ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have an ssh account.
        $fields['sysinfo']    = $package->getCustomValue('sysinfo') ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have access to a page that shows the system information.
        $fields['login_keys'] = $package->getCustomValue('login_keys') ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have access to the Login Key system for extra account passwords.
        $fields['dnscontrol'] = $package->getCustomValue('dnscontrol') ? 'ON' : 'OFF'; //ON or OFF If ON, the User will be able to modify his/her dns records.
        $fields['suspend_at_limit'] = $package->getCustomValue('suspend_at_limit') ? 'ON' : 'OFF'; //ON or OFF If ON, the User will be suspended if their User bandwidth limit is exceeded.
        $command              = 'CMD_API_ACCOUNT_USER';
        
        if($a->getReseller()) {
            $command = 'CMD_ACCOUNT_RESELLER';
            
            $fields['ips'] = 1; //Number of ips that will be allocated to the Reseller upon account during account
            $fields['ip']  = 'assign';
        }
        
        try {
            $results = $this->_request($command, $fields);
        }
        catch(Exception $e) {
            if(strtolower($e->getMessage()) == strtolower(sprintf('Server Manager DirectAdmin Error: "%s" ', 'That domain already exists'))) {
                return true;
            } else {
                throw new Server_Exception($e->getMessage());
            }
        }
        
        if(str_contains(implode('', $results), 'Unable to assign the Reseller ANY ips')) {
            throw new Server_Exception('Unable to assign the Reseller ANY ips. Make sure to have free, un-assigned ips.');
        }
        
        if(str_contains(implode('', $results), 'Error Creating User')) {
            throw new Server_Exception('Error creating user');
        }
        
        return true;
    }
    
    public function suspendAccount(Server_Account $a)
    {
        $info = $this->getAccountInfo($a);
        if($info['suspended'] == 'yes') {
            return true;
        }
        
        $fields             = array();
        $fields['location'] = 'CMD_USER_SHOW';
        $fields['suspend']  = 'Suspend';
        $fields['select0']  = $a->getUsername();
        $result             = $this->_request('CMD_API_SELECT_USERS', $fields);
        
        return true;
    }
    
    public function unsuspendAccount(Server_Account $a)
    {
        $info = $this->getAccountInfo($a);
        if($info['suspended'] == 'no') {
            throw new Server_Exception('Server Manager DirectAdmin Error: "Account is not suspended"');
        }
        
        $fields             = array();
        $fields['location'] = 'CMD_USER_SHOW';
        $fields['suspend']  = 'Unsuspend';
        $fields['select0']  = $a->getUsername();
        $this->_request('CMD_API_SELECT_USERS', $fields);
        return true;
    }
    
    public function cancelAccount(Server_Account $a)
    {
        $fields              = array();
        $fields['confirmed'] = 'Confirm';
        $fields['delete']    = 'yes';
        $fields['select0']   = $a->getUsername();
        $this->_request('CMD_API_SELECT_USERS', $fields);
        return true;
    }
    
    public function modifyAccount(Server_Account $a)
    {
        $package = $a->getPackage();
        
        $fields           = array();
        $fields['action'] = 'customize';
        $fields['user']   = $a->getUsername();
        
        $fields['bandwidth'] = $package->getBandwidth(); //Amount of bandwidth User will be allowed to use. Number, in Megabytes
        if($package->getBandwidth() == 'unlimited') {
            $fields['ubandwidth'] = 'ON'; //ON or OFF. If ON, bandwidth is ignored and no limit is set
        }
        $fields['quota'] = $package->getQuota(); //Amount of disk space User will be allowed to use. Number, in Megabytes
        if($package->getQuota() == 'unlimited') {
            $fields['uquota'] = 'ON'; //ON or OFF. If ON, quota is ignored and no limit is set
        }
        $fields['vdomains'] = $package->getMaxDomains(); //Number of domains the User will be allowed to create
        if($package->getMaxDomains() == 'unlimited') {
            $fields['uvdomains'] = 'ON'; //ON or OFF. If ON, vdomains is ignored and no limit is set
        }
        $fields['nsubdomains'] = $package->getMaxSubdomains(); //Number of subdomains the User will be allowed to create
        if($package->getMaxSubdomains() == 'unlimited') {
            $fields['unsubdomains'] = 'ON'; //ON or OFF. If ON, nsubdomains is ignored and no limit is set
        }
        $fields['nemails'] = $package->getMaxPop(); //Number of pop accounts the User will be allowed to create
        if($package->getMaxPop() == 'unlimited') {
            $fields['unemails'] = 'ON'; //ON or OFF Unlimited option for nemails
        }
        $fields['nemailf'] = $package->getMaxEmailForwarders(); //Number of forwarders the User will be allowed to create
        if($package->getMaxEmailForwarders() == 'unlimited') {
            $fields['unemailf'] = 'ON'; //ON or OFF Unlimited option for nemailf
        }
        $fields['nemailml'] = $package->getMaxEmailLists(); //Number of mailing lists the User will be allowed to create
        if($package->getMaxEmailLists() == 'unlimited') {
            $fields['unemailml'] = 'ON'; //ON or OFF Unlimited option for nemailml
        }
        $fields['nemailr'] = $package->getMaxEmailAutoresponders(); //Number of autoresponders the User will be allowed to create
        if($package->getMaxEmailAutoresponders() == 'unlimited') {
            $fields['unemailr'] = 'ON'; //ON or OFF Unlimited option for nemailr
        }
        $fields['mysql'] = $package->getMaxSql(); //Number of MySQL databases the User will be allowed to create
        if($package->getMaxSql() == 'unlimited') {
            $fields['umysql'] = 'ON'; //ON or OFF Unlimited option for mysql
        }
        $fields['domainptr'] = $package->getMaxParkedDomains(); //Number of domain pointers the User will be allowed to create
        if($package->getMaxParkedDomains() == 'unlimited') {
            $fields['udomainptr'] = 'ON'; //ON or OFF Unlimited option for domainptr
        }
        $fields['ftp'] = $package->getMaxFtp(); //Number of ftp accounts the User will be allowed to create
        if($package->getMaxFtp() == 'unlimited') {
            $fields['uftp'] = 'ON'; //ON or OFF Unlimited option for ftp
        }
        $fields['aftp']       = $package->getHasAnonymousFtp() ? 'ON' : 'OFF'; //ON or OFF If ON, the User will be able to have anonymous ftp accounts.
        $fields['cgi']        = $package->getHasCgi() ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have the ability to run cgi scripts in their cgi-bin.
        $fields['php']        = $package->getHasPhp() ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have the ability to run php scripts.
        $fields['spam']       = $package->getHasSpamFilter() ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have the ability to run scan email with SpamAssassin.
        $fields['cron']       = $package->getHasCron() ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have the ability to creat cronjobs.
        $fields['catchall']   = $package->getHasCatchAll() ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have the ability to enable and customize a catch-all email (*@domain.com).
        $fields['ssl']        = $package->getHasSll() ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have the ability to access their websites through secure https://.
        $fields['ssh']        = $package->getHasShell() ? 'ON' : 'OFF'; //ON or OFF If ON, the User will have an ssh account.
        $fields['sysinfo']    = 'ON'; //ON or OFF If ON, the User will have access to a page that shows the system information.
        $fields['dnscontrol'] = 'ON'; //ON or OFF If ON, the User will be able to modify his/her dns records.
        
        $fields['ns1'] = $a->getNs1();
        $fields['ns2'] = $a->getNs2();
        
        $this->_request('CMD_API_MODIFY_USER', $fields);
        return true;
    }
    
    public function changeAccountPassword(Server_Account $a, $newPassword)
    {
        $fields             = array();
        $fields['username'] = $a->getUsername();
        $fields['passwd']   = $newPassword;
        $fields['passwd2']  = $newPassword;
        $this->_request('CMD_API_USER_PASSWD', $fields);
        return true;
    }
    
    public function changeAccountUsername(Server_Account $a, $new)
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'DirectAdmin', ':action:' => __trans('username changes')]);
    }
    
    public function changeAccountDomain(Server_Account $a, $new)
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'DirectAdmin', ':action:' => __trans('changing the account domain')]);
    }
    
    public function changeAccountIp(Server_Account $a, $new)
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'DirectAdmin', ':action:' => __trans('changing the account IP')]);
    }
    
    public function changeAccountPackage(Server_Account $a, Server_Package $p)
    {
        $fields            = array();
        $fields['action']  = 'package';
        $fields['user']    = $a->getUsername();
        $fields['package'] = $a->getPackage()->getName();
        $this->_request('CMD_API_MODIFY_USER', $fields);
        return true;
    }
    
    private function getAccountInfo(Server_Account $a)
    {
        $fields           = array();
        $fields['action'] = 'create';
        $fields['add']    = 'Submit';
        $fields['user']   = $a->getUsername();
        return $this->_request('CMD_API_SHOW_USER_CONFIG', $fields);
    }
    
    private function getUserInfo(Server_Account $a)
    {
        $fields         = array();
        $fields['user'] = $a->getUsername();
        $result         = $this->_request('CMD_API_SHOW_USER_CONFIG', $fields);
        return;
    }
    
    private function checkAuth()
    {
        $r = $this->_request('CMD_API_VERIFY_PASSWORD', array(
            'user' => $this->_config['username'],
            'passwd' => $this->_config['password']
        ));
        
        return true;
    }
    
    private function getPackages()
    {
        return $this->_request('CMD_API_PACKAGES_USER');
    }
    
    private function getIps()
    {
        $results = $this->_request('CMD_API_SHOW_RESELLER_IPS');
        return $results['list'] ?? array();
    }
    
    /**
     * @param string $command
     */
    private function _request($command, $fields = array(), $post = true)
    {
        $host = $this->_config['host'];
        $protocol = $this->_config['secure'] ? 'https://' : 'http://';
        
        $fieldstring = http_build_query($fields);
        
        $httpClient = $this->getHttpClient()->withOptions([
            'verify_peer'   => false,
            'verify_host'   => false,
            'timeout'       => 60,
            'auth_basic'    => [ $this->_config['username'], $this->_config['password'] ],           
        ]);

        $url = $protocol . $host . ':2222/' . $command . '?' . $fieldstring;
        $this->getLog()->debug($url);

        try {
            if($post) {
                $request = $httpClient->request('POST', $url, [
                    'body'  => $fields,
                ]);
            } else {
                $request = $httpClient->request('GET', $url);
            }

            $data = $request->getContent();
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface |
                 \Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface $error) {
            $e = new Server_Exception('HttpClientException: :error', [':error' => $error->getMessage()]);
            $this->getLog()->err($e);
            throw $e;
        }
        
        if(strlen(strstr($data, 'DirectAdmin Login')) > 0) {
            throw new Server_Exception('Failed to connect to the :type: server. Please verify your credentials and configuration', [':type:' => 'DirectAdmin']);
        }
        
        if(strlen(strstr($data, "The request you've made cannot be executed because it does not exist in your authority level")) > 0) {
            throw new Server_Exception('Server Manager DirectAdmin Error: "The request you have made cannot be executed because it does not exist in your authority level"');
        }
        
        $r = $this->_parseResponse($data);
        
        if(isset($r['error']) && $r['error'] == 1) {
            $placeholders = ['action' => $command, 'type' => 'DirectAdmin'];
            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }
        
        $response = empty($r) ? array() : $r;
        return $response;
    }
    
    private function _parseResponse($data)
    {
        $this->getLog()->debug('Raw Response: ' . $data);
        
        // add more replacers if needed
        $data = str_replace('&#39', '"', $data);
        
        $data = preg_replace('|(\&\#\d+)|', '$1;', $data);
        $data = html_entity_decode($data);
        
        parse_str($data, $r);
        
        $this->getLog()->debug('Parsed Response: ' . print_r($r, 1));
        return $r;
    }
}
