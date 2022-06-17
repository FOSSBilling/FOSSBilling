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

class Server_Manager_Vesta extends Server_Manager
{
    /**
     * Method is called just after obejct contruct is complete.
     * Add required parameters checks here.
     */
    public function init()
    {
        if (!extension_loaded('curl')) {
            throw new Server_Exception('PHP cURL extension is not enabled');
        }
    }

    public function _getPort()
    {
        if (is_numeric($this->_config['port'])) {
            return $this->_config['port'];
        } else {
            return '8083';
        }
    }

    /**
     * Return server manager parameters.
     *
     * @return type
     */
    public static function getForm()
    {
        return [
            'label' => 'VestaCP',
        ];
    }

    /**
     * Returns link to account management page.
     *
     * @return string
     */
    public function getLoginUrl()
    {
        $host = 'https://'.$this->_config['host'].':'.$this->_getPort().'/';

        return $host;
    }

    /**
     * Returns link to reseller account management.
     *
     * @return string
     */
    public function getResellerLoginUrl()
    {
        return 'https://google.com';
    }

    private function _makeRequest($params)
    {
        $host = 'https://'.$this->_config['host'].':'.$this->_getPort().'/api/';

        // Server credentials
        if ('' != $this->_config['accesshash']) {
            $params['hash'] = $this->_config['accesshash'];
        } else {
            $params['user'] = $this->_config['username'];
            $params['password'] = $this->_config['password'];
        }

        // Send POST query via cURL
        $postdata = http_build_query($params);
        $curl = curl_init();
        $timeout = 5;
        curl_setopt($curl, CURLOPT_URL, $host);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $result = curl_exec($curl);
        curl_close($curl);
        if (false !== strpos($result, 'Error')) {
            throw new Server_Exception('Connection to server failed  '.$result);
        }

        return $result;
    }

    private function _getPackageName(Server_Package $package)
    {
        $name = $package->getName();

        return $name;
    }

    /**
     * This method is called to check if configuration is correct
     * and class can connect to server.
     *
     * @return bool
     */
    public function testConnection()
    {
        // Server credentials
        $vst_command = 'v-list-users';
        $vst_returncode = 'yes';
        // Prepare POST query
        $postvars = [
            'returncode' => $vst_returncode,
            'cmd' => $vst_command,
            'arg1' => $this->_config['username'],
            'arg2' => $this->_config['password'],
        ];

        // Make request and check sys info
        $result = $this->_makeRequest($postvars);
        if (false !== strpos($result, 'Error')) {
            throw new Server_Exception('Connection to server failed  '.$result);
        } else {
            if (0 == $result) {
                return true;
            } else {
                throw new Server_Exception('Connection to server failed '.$result);
            }
        }

        return true;
    }

    /**
     * MEthods retrieves information from server, assignes new values to
     * cloned Server_Account object and returns it.
     *
     * @return Server_Account
     */
    public function synchronizeAccount(Server_Account $a)
    {
        $this->getLog()->info('Synchronizing account with server '.$a->getUsername());
        $new = clone $a;
        //@example - retrieve username from server and set it to cloned object
        //$new->setUsername('newusername');
        return $new;
    }

    /**
     * Create new account on server.
     *
     * @param Server_Account $a
     */
    public function create_tmp_file($password)
    {
        $vst_command = 'v-make-tmp-file';
        $vst_returncode = 'yes';
        $postvars = [
            'returncode' => $vst_returncode,
            'cmd' => $vst_command,
            'arg1' => $password,
            'arg2' => 'hestiapass',
        ];
        $result = $this->_makeRequest($postvars);
        if (0 == $result) {
            return '/tmp/hestiapass';
        } else {
            return false;
        }
    }

    public function createAccount(Server_Account $a)
    {
        $p = $a->getPackage();
        $packname = $this->_getPackageName($p);
        $client = $a->getClient();
        // Server credentials
        $vst_command = 'v-add-user';
        $vst_returncode = 'yes';
        $parts = explode(' ', $client->getFullName());
        $lastname = array_pop($parts);
        $firstname = implode(' ', $parts);
        // Prepare POST query
        $postvars = [
            'returncode' => $vst_returncode,
            'cmd' => $vst_command,
            'arg1' => $a->getUsername(),
            'arg2' => $this->create_tmp_file($a->getPassword()),
            'arg3' => $client->getEmail(),
            'arg4' => $packname,
            'arg5' => trim($firstname),
            'arg6' => trim($lastname),
        ];
        // Make request and create user
        $result = $this->_makeRequest($postvars);
        if (0 == $result) {
// Create Domain Prepare POST query
            $postvars2 = [
                'returncode' => 'yes',
                'cmd' => 'v-add-domain',
                'arg1' => $a->getUsername(),
                'arg2' => $a->getDomain(),
            ];
            $result2 = $this->_makeRequest($postvars2);
            if ('0' != $result2) {
                throw new Server_Exception('Server Manager Vesta CP Error: Create Domain failure '.$result2);
            }

            return true;
        } else {
            throw new Server_Exception('Server Manager Vesta CP Error: Unable to create User '.$result1);
        }
    }

    /**
     * Suspend account on server.
     */
    public function suspendAccount(Server_Account $a)
    {
        $user = $a->getUsername();
        // Prepare POST query
        $postvars = [
            'returncode' => 'yes',
            'cmd' => 'v-suspend-user',
            'arg1' => $a->getUsername(),
            'arg2' => 'no',
                  ];
        // Make request and suspend user
        $result = $this->_makeRequest($postvars);
        // Check if error 6 the account is suspended on server
        if ('6' == $result) {
            return true;
        }
        if ('0' != $result) {
            throw new Server_Exception('Server Manager Vesta CP Error: Suspend Account Error '.$result.$suspended);
        }

        return true;
    }

    /**
     * Unsuspend account on server.
     */
    public function unsuspendAccount(Server_Account $a)
    {
        // Server credentials
        $vst_command = 'v-unsuspend-user';
        $vst_returncode = 'yes';
        // Prepare POST query
        $postvars = [
            'returncode' => $vst_returncode,
            'cmd' => $vst_command,
            'arg1' => $a->getUsername(),
            'arg2' => 'no',
            ];

        $result = $this->_makeRequest($postvars);
        if ('0' != $result) {
            throw new Server_Exception('Server Manager Vesta CP Error: Unsuspend Account Error '.$result);
        }

        return true;
    }

    /**
     * Cancel account on server.
     */
    public function cancelAccount(Server_Account $a)
    {
        // Server credentials
        $vst_username = $this->_config['username'];
        $vst_password = $this->_config['password'];
        $vst_command = 'v-delete-user';
        $vst_returncode = 'yes';
        // Prepare POST query
        $postvars = [
            'returncode' => $vst_returncode,
            'cmd' => $vst_command,
            'arg1' => $a->getUsername(),
            'arg2' => 'no',
            ];
        // Make request and delete user
        $result = $this->_makeRequest($postvars);
        if ('3' == $result) {
            return true;
        } else {
            if ('0' != $result) {
                throw new Server_Exception('Server Manager Vesta CP Error: Cancel Account Error '.$result);
            }
        }

        return true;
    }

    /**
     * Change account package on server.
     */
    public function changeAccountPackage(Server_Account $a, Server_Package $p)
    {
        $package = $a->getPackage()->getName();

        // Server credentials
        $vst_username = $this->_config['username'];
        $vst_password = $this->_config['password'];
        $vst_command = 'v-change-user-package';
        $vst_returncode = 'yes';
        // Prepare POST query
        $postvars = [
            'returncode' => $vst_returncode,
            'cmd' => $vst_command,
            'arg1' => $a->getUsername(),
            'arg2' => $this->_getPackageName($p),
            'arg3' => 'no',
        ];
        // Make request and change package
        $result = $this->_makeRequest($postvars);
        if ('0' != $result) {
            throw new Server_Exception('Server Manager Vesta CP Error: Change User package Account Error '.$result);
        }

        return true;
    }

    /**
     * Change account username on server.
     *
     * @param type $new - new account username
     */
    public function changeAccountUsername(Server_Account $a, $new)
    {
        throw new Server_Exception('Server Manager Vesta CP Error: Not Supported');
    }

    /**
     * Change account domain on server.
     *
     * @param type $new - new domain name
     */
    public function changeAccountDomain(Server_Account $a, $new)
    {
        throw new Server_Exception('Server Manager Vesta CP Error: Not Supported');
    }

    /**
     * Change account password on server.
     *
     * @param type $new - new password
     */
    public function changeAccountPassword(Server_Account $a, $new)
    {
        // Server credentials
        $vst_username = $this->_config['username'];
        $vst_password = $this->_config['password'];
        $vst_command = 'v-change-user-password';
        $vst_returncode = 'yes';
        // Prepare POST query
        $postvars = [
            'returncode' => $vst_returncode,
            'cmd' => $vst_command,
            'arg1' => $a->getUsername(),
            'arg2' => $this->create_tmp_file($new),
        ];
        // Make request and change password
        $result = $this->_makeRequest($postvars);
        if ('0' != $result) {
            throw new Server_Exception('Server Manager Vesta CP Error: Change Password Account Error '.$result);
        }

        return true;
    }

    /**
     * Change account IP on server.
     *
     * @param type $new - account IP
     */
    public function changeAccountIp(Server_Account $a, $new)
    {
        throw new Server_Exception('Server Manager Vesta CP Error: Not Supported');
    }
}
