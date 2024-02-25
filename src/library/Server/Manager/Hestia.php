<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Manager_Hestia extends Server_Manager
{
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
     * Method is called just after object construct is complete.
     * Add required parameters checks here.
     */
    public function init()
    {
    }

    /**
     * Returns link to reseller account management.
     */
    public function getResellerLoginUrl(Server_Account $account = null): string
    {
        return $this->getLoginUrl();
    }

    /**
     * Returns link to account management page.
     */
    public function getLoginUrl(Server_Account $account = null): string
    {
        return 'https://' . $this->_config['host'] . ':' . $this->getPort() . '/';
    }

    public function getPort(): int|string
    {
        $port = $this->_config['port'];

        if (filter_var($port, FILTER_VALIDATE_INT) !== false && $port >= 0 && $port <= 65535) {
            return $this->_config['port'];
        } else {
            return 8083;
        }
    }

    public function generateUsername(string $domain): string
    {
        $processedDomain = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $domain));
        $prefix = $this->_config['config']['userprefix'] ?? '';
        $username = $prefix . substr($processedDomain, 0, 7) . random_int(0, 9);

        // HestiaCP doesn't allow usernames to start with a number, so automatically append the letter 'a' to the start of a username that does.
        // See: https://github.com/hestiacp/hestiacp/pull/4195
        if (is_numeric(substr($username, 0, 1))) {
            $username = substr_replace($username, 'a', 0, 1);
        }

        return $username;
    }

    /**
     * This method is called to check if configuration is correct
     * and class can connect to server.
     *
     * @throws Server_Exception
     */
    public function testConnection(): bool
    {
        // Prepare POST query
        $postVars = [
            'cmd' => 'v-list-users',
            'arg1' => $this->_config['username'],
            'arg2' => $this->_config['password'],
        ];

        // Make request and check sys info
        $result = $this->request($postVars);
        if (intval($result) != 0) {
            throw new Server_Exception('Failed to connect to the :type: server. Please verify your credentials and configuration', [':type:' => 'HestiaCP']);
        }

        return true;
    }

    /**
     * Methods retrieves information from server, assign's new values to
     * cloned Server_Account object and returns it.
     */
    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        $this->getLog()->info('Synchronizing account with server ' . $account->getUsername());

        // @example - retrieve username from server and set it to cloned object
        // $new->setUsername('newusername');
        return clone $account;
    }

    /**
     * Create new account on server.
     *
     * @return true
     *
     * @throws Server_Exception
     */
    public function createAccount(Server_Account $account): bool
    {
        $client = $account->getClient();

        // Prepare POST query
        $postVars = [
            'cmd' => 'v-add-user',
            'arg1' => $account->getUsername(),
            'arg2' => $account->getPassword(),
            'arg3' => $client->getEmail(),
            'arg4' => $account->getPackage()->getName(),
            'arg5' => trim($client->getFullName()),
        ];

        // Make request and create user
        $result1 = $this->request($postVars);
        if (intval($result1) == 0) {
            // Create Domain Prepare POST query
            $postVars = [
                'cmd' => 'v-add-domain',
                'arg1' => $account->getUsername(),
                'arg2' => $account->getDomain(),
            ];
            $result2 = $this->request($postVars);
        } else {
            $placeholders = [':action:' => __trans('create user'), ':type:' => 'HestiaCP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        if (intval($result2) !== 0) {
            $postVars = [
                'cmd' => 'v-delete-user',
                'arg1' => $account->getUsername(),
            ];

            $result3 = $this->request($postVars);
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
     *
     * @throws Server_Exception
     */
    public function suspendAccount(Server_Account $account): bool
    {
        // Prepare POST query
        $postVars = [
            'cmd' => 'v-suspend-user',
            'arg1' => $account->getUsername(),
            'arg2' => 'no',
        ];

        // Make request and suspend user
        $result = $this->request($postVars);

        // Check for known errors
        if (intval($result) !== 0 && intval($result) !== 6) {
            $placeholders = [':action:' => __trans('suspend account'), ':type:' => 'HestiaCP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * Unsuspend account on server.
     *
     * @throws Server_Exception
     */
    public function unsuspendAccount(Server_Account $account): bool
    {
        // Prepare POST query
        $postVars = [
            'cmd' => 'v-unsuspend-user',
            'arg1' => $account->getUsername(),
            'arg2' => 'no',
        ];

        $result = $this->request($postVars);
        if (intval($result) !== 0) {
            $placeholders = [':action:' => __trans('unsuspend account'), ':type:' => 'HestiaCP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * Cancel account on server.
     *
     * @throws Server_Exception
     */
    public function cancelAccount(Server_Account $account): bool
    {
        // Prepare POST query
        $postVars = [
            'cmd' => 'v-delete-user',
            'arg1' => $account->getUsername(),
            'arg2' => 'no',
        ];

        // Make request and delete user
        $result = $this->request($postVars);
        if (intval($result) !== 0 && intval($result) !== 3) {
            $placeholders = [':action:' => __trans('cancel account'), ':type:' => 'HestiaCP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * Change account package on server.
     *
     * @throws Server_Exception
     */
    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        // Prepare POST query
        $postVars = [
            'cmd' => 'v-change-user-package',
            'arg1' => $account->getUsername(),
            'arg2' => $package->getName(),
            'arg3' => 'no',
        ];

        // Make request and change package
        $result = $this->request($postVars);
        if (intval($result) !== 0) {
            $placeholders = [':action:' => __trans('change account package'), ':type:' => 'HestiaCP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * Change account username on server.
     *
     * @throws Server_Exception
     */
    public function changeAccountUsername(Server_Account $account, $newUsername): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'HestiaCP', ':action:' => __trans('username changes')]);
    }

    /**
     * Change account domain on server.
     *
     * @throws Server_Exception
     */
    public function changeAccountDomain(Server_Account $account, $newDomain): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'HestiaCP', ':action:' => __trans('changing the account domain')]);
    }

    /**
     * Change account password on server.
     *
     * @throws Server_Exception
     */
    public function changeAccountPassword(Server_Account $account, string $newPassword): bool
    {
        // Prepare POST query
        $postVars = [
            'cmd' => 'v-change-user-password',
            'arg1' => $account->getUsername(),
            'arg2' => $newPassword,
        ];

        // Make request and change password
        $result = $this->request($postVars);
        if (intval($result) !== 0) {
            $placeholders = [':action:' => __trans('change account password'), ':type:' => 'HestiaCP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * Change account IP on server.
     *
     * @throws Server_Exception
     */
    public function changeAccountIp(Server_Account $account, string $newIp): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'HestiaCP', ':action:' => __trans('changing the account IP')]);
    }

    /**
     * @throws Server_Exception
     */
    private function request($params): mixed
    {
        $host = 'https://' . $this->_config['host'] . ':' . $this->getPort() . '/api/';

        // Set return code to yes
        $params['returncode'] = 'yes';

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
}
