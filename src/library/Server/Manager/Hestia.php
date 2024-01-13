<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Manager_Hestia extends Server_Manager
{
    /**
     * Method is called just after obejct contruct is complete.
     * Add required parameters checks here.
     */
    public function init()
    {
    }

    public function _getPort()
    {
        return is_numeric($this->_config['port']) ? $this->_config['port'] : '8083';
    }

    /**
     * Return server manager parameters.
     */
    public static function getForm(): array
    {
        return [
            'label' => 'Hestia Control Panel',
            'form' => [
                'credentials' => [
                    'fields' => [
                        [
                            'name' => 'username',
                            'type' => 'text',
                            'label' => 'Access key ID',
                            'placeholder' => 'ID for the access key you\'ve generated in Hestia.',
                            'required' => true,
                        ],
                        [
                            'name' => 'accesshash',
                            'type' => 'text',
                            'label' => 'Secret key',
                            'placeholder' => 'Secret key for the access key you\'ve generated in Hestia',
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns link to account management page.
     *
     * @return string
     */
    public function getLoginUrl(Server_Account $account = null)
    {
        return 'https://' . $this->_config['host'] . ':' . $this->_getPort() . '/';
    }

    /**
     * Returns link to reseller account management.
     *
     * @return string
     */
    public function getResellerLoginUrl(Server_Account $account = null)
    {
        return $this->getLoginUrl();
    }

    private function _makeRequest($params)
    {
        $host = 'https://' . $this->_config['host'] . ':' . $this->_getPort() . '/api/';

        // Server credentials
        if ($this->_config['accesshash'] != '' && $this->_config['username'] != '') {
            $params['hash'] = $this->_config['username'] . ':' . $this->_config['accesshash'];
        } elseif ($this->_config['accesshash'] != '') {
            $params['hash'] = $this->_config['accesshash'];
        } else {
            $params['user'] = $this->_config['username'];
            $params['password'] = $this->_config['password'];
        }

        // Send POST query
        $client = $this->getHttpClient()->withOptions([
            'verify_peer' => false,
            'verify_host' => false,
            'timeout' => 30,
        ]);
        $response = $client->request('POST', $host, [
            'body' => $params,
        ]);
        $result = $response->getContent();

        if (str_contains($result, 'Error')) {
            throw new Server_Exception('Failed to connect to the :type: server. Please verify your credentials and configuration', [':type:' => 'HestiaCP']);
        } elseif (intval($result) !== 0) {
            error_log("HestiaCP returned error code $result for the " . $params['cmd'] . 'command');
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
        if (intval($result) == 0) {
            return true;
        } else {
            throw new Server_Exception('Failed to connect to the :type: server. Please verify your credentials and configuration', [':type:' => 'HestiaCP']);
        }

        return true;
    }

    /**
     * Methods retrieves information from server, assign's new values to
     * cloned Server_Account object and returns it.
     *
     * @return Server_Account
     */
    public function synchronizeAccount(Server_Account $a)
    {
        $this->getLog()->info('Synchronizing account with server ' . $a->getUsername());
        $new = clone $a;

        // @example - retrieve username from server and set it to cloned object
        // $new->setUsername('newusername');
        return $new;
    }

    /**
     * Create new account on server.
     */
    public function createAccount(Server_Account $account)
    {
        $p = $account->getPackage();
        $packname = $this->_getPackageName($p);
        $client = $account->getClient();
        // Server credentials
        $vst_command = 'v-add-user';
        $vst_returncode = 'yes';
        // Prepare POST query
        $postvars = [
            'returncode' => $vst_returncode,
            'cmd' => $vst_command,
            'arg1' => $account->getUsername(),
            'arg2' => $account->getPassword(),
            'arg3' => $client->getEmail(),
            'arg4' => $packname,
            'arg5' => trim($client->getFullName()),
        ];
        // Make request and create user
        $result1 = $this->_makeRequest($postvars);
        if (intval($result1) == 0) {
            // Create Domain Prepare POST query
            $postvars2 = [
                'returncode' => 'yes',
                'cmd' => 'v-add-domain',
                'arg1' => $account->getUsername(),
                'arg2' => $account->getDomain(),
            ];
            $result2 = $this->_makeRequest($postvars2);
        } else {
            $placeholders = [':action:' => __trans('create user'), ':type:' => 'HestiaCP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }
        if (intval($result2) !== 0) {
            $postvars3 = [
                'returncode' => 'yes',
                'cmd' => 'v-delete-user',
                'arg1' => $account->getUsername(),
            ];
            $result3 = $this->_makeRequest($postvars3);
            if (intval($result3) !== 0) {
                $placeholders = [':action1:' => __trans('delete domain'), ':action2:' => __trans('create domain'), ':type:' => 'HestiaCP'];

                throw new Server_Exception('Failed to :action1: on the :type: server after failed to :action2:, check the error logs for further details', $placeholders);
            }
            $placeholders = [':action:' => __trans('create domain'), ':type:' => 'HestiaCP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
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
        if (intval($result) == 6) {
            return true;
        }
        if (intval($result) !== 0) {
            $placeholders = [':action:' => __trans('suspend account'), ':type:' => 'HestiaCP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
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
        if (intval($result) !== 0) {
            $placeholders = [':action:' => __trans('unsuspend account'), ':type:' => 'HestiaCP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
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
        if (intval($result) == '3') {
            return true;
        } elseif (intval($result) !== 0) {
            $placeholders = [':action:' => __trans('cancel account'), ':type:' => 'HestiaCP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
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
        if (intval($result) !== 0) {
            $placeholders = [':action:' => __trans('change account package'), ':type:' => 'HestiaCP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * Change account username on server.
     */
    public function changeAccountUsername(Server_Account $a, $new): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'HestiaCP', ':action:' => __trans('username changes')]);
    }

    /**
     * Change account domain on server.
     */
    public function changeAccountDomain(Server_Account $a, $new): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'HestiaCP', ':action:' => __trans('changing the account domain')]);
    }

    /**
     * Change account password on server.
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
            'arg2' => $new,
        ];
        // Make request and change password
        $result = $this->_makeRequest($postvars);
        if (intval($result) !== 0) {
            $placeholders = [':action:' => __trans('change account password'), ':type:' => 'HestiaCP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * Change account IP on server.
     */
    public function changeAccountIp(Server_Account $a, $new): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'HestiaCP', ':action:' => __trans('changing the account IP')]);
    }
}
