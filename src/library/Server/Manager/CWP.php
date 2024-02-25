<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * CWP API.
 *
 * @see https://docs.control-webpanel.com/docs/developer-tools/api-manager
 */
class Server_Manager_CWP extends Server_Manager
{
    /**
     * Returns the form configuration for the CWP server manager.
     *
     * @return array returns an array with the label and form configuration for the CWP server manager
     */
    public static function getForm(): array
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
            ],
        ];
    }

    /**
     * Initializes the server manager.
     * Checks if the required parameters (IP, host, access hash, port) are set.
     *
     * @throws Server_Exception if any of the required parameters are not set
     */
    public function init(): void
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

    /**
     * Returns the login URL for the CWP server manager.
     *
     * @param Server_Account|null $account the account for which the login URL is generated
     *
     * @return string returns the login URL as a string
     */
    public function getLoginUrl(?Server_Account $account): string
    {
        $host = $this->_config['host'];

        return 'https://' . $host . ':2083';
    }

    /**
     * Returns the reseller login URL for the CWP server manager.
     *
     * @param Server_Account|null $account the account for which the reseller login URL is generated
     *
     * @return string returns the reseller login URL as a string
     */
    public function getResellerLoginUrl(?Server_Account $account): string
    {
        $host = $this->_config['host'];

        return 'https://' . $host . ':2031';
    }

    /**
     * Tests the connection to the CWP server.
     * Makes a request to the server and checks if the response is successful.
     *
     * @return bool returns true if the connection test is successful
     *
     * @throws Server_Exception if the connection test fails
     */
    public function testConnection(): bool
    {
        $data = [
            'action' => 'list',
        ];

        if ($this->request('account', $data)) {
            return true;
        } else {
            throw new Server_Exception('Failed to connect to the :type: server. Please verify your credentials and configuration', [':type:' => 'CWP']);
        }
    }

    /**
     * Synchronizes the account with the server.
     *
     * @param Server_Account $account the account to be synchronized
     *
     * @return Server_Account returns the synchronized account
     */
    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        $this->getLog()->info('Synchronizing account with server ' . $account->getUsername());

        $data = [
            'action' => 'list',
            'user' => $account->getUsername(),
        ];

        $new = clone $account;
        $acc = $this->request('accountdetail', $data);

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
     * Creates a new account on the CWP server.
     *
     * @param Server_Account $account the account to be created
     *
     * @return bool returns true if the account is successfully created
     *
     * @throws Server_Exception if the account creation fails
     */
    public function createAccount(Server_Account $account): bool
    {
        $this->getLog()->info('Creating account ' . $account->getUsername());

        $client = $account->getClient();
        $package = $account->getPackage()->getName();

        $ip = $this->_config['ip'];

        $data = [
            'action' => 'add',
            'domain' => $account->getDomain(),
            'user' => $account->getUsername(),
            'pass' => base64_encode($account->getPassword()),
            'email' => $client->getEmail(),
            'package' => $package,
            'server_ips' => $ip,
            'encodepass' => true,
        ];

        if ($account->getReseller()) {
            $data['reseller'] = 1;
        }

        if (!$this->request('account', $data)) {
            $placeholders = [':action:' => __trans('create account'), ':type:' => 'CWP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * Suspends an account on the CWP server.
     *
     * @param Server_Account $account the account to be suspended
     *
     * @return bool returns true if the account is successfully suspended
     *
     * @throws Server_Exception if the account suspension fails
     */
    public function suspendAccount(Server_Account $account): bool
    {
        $this->getLog()->info('Suspending account ' . $account->getUsername());

        $data = [
            'action' => 'susp',
            'user' => $account->getUsername(),
        ];

        if (!$this->request('account', $data)) {
            $placeholders = ['action' => __trans('suspend account'), 'type' => 'CWP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * Unsuspends an account on the CWP server.
     *
     * @param Server_Account $account the account to be unsuspended
     *
     * @return bool returns true if the account is successfully unsuspended
     *
     * @throws Server_Exception if the account unsuspension fails
     */
    public function unsuspendAccount(Server_Account $account): bool
    {
        $this->getLog()->info('Un-suspending account ' . $account->getUsername());

        $data = [
            'action' => 'unsp',
            'user' => $account->getUsername(),
        ];

        if (!$this->request('account', $data)) {
            $placeholders = [':action:' => __trans('unsuspend account'), ':type:' => 'CWP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * Cancels an account on the CWP server.
     *
     * @param Server_Account $account the account to be cancelled
     *
     * @return bool returns true if the account is successfully cancelled
     *
     * @throws Server_Exception if the account cancellation fails
     */
    public function cancelAccount(Server_Account $account): bool
    {
        $this->getLog()->info('Canceling account ' . $account->getUsername());

        $client = $account->getClient();

        $data = [
            'action' => 'del',
            'user' => $account->getUsername(),
            'email' => $client->getEmail(),
        ];

        if (!$this->request('account', $data)) {
            $placeholders = [':action:' => __trans('cancel account'), ':type:' => 'CWP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * Changes the package of an account on the CWP server.
     *
     * @param Server_Account $account the account for which the package is to be changed
     * @param Server_Package $package the new package
     *
     * @return bool returns true if the package is successfully changed
     *
     * @throws Server_Exception if the package change fails
     */
    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        $this->getLog()->info('Changing package on account ' . $account->getUsername());

        $data = [
            'action' => 'upd',
            'user' => $account->getUsername(),
            'package' => $account->getPackage()->getName(),
        ];

        if (!$this->request('changepack', $data)) {
            $placeholders = [':action:' => __trans('change account package'), ':type:' => 'CWP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * Changes the password of an account on the CWP server.
     *
     * @param Server_Account $account     the account for which the password is to be changed
     * @param string         $newPassword the new password
     *
     * @return bool returns true if the password is successfully changed
     *
     * @throws Server_Exception if the password change fails
     */
    public function changeAccountPassword(Server_Account $account, string $newPassword): bool
    {
        $this->getLog()->info('Changing password on account ' . $account->getUsername());

        $data = [
            'action' => 'udp',
            'user' => $account->getUsername(),
            'pass' => $newPassword,
        ];

        if (!$this->request('changepass', $data)) {
            $placeholders = [':action:' => __trans('change account password'), ':type:' => 'CWP'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * Throws an exception because CWP does not support username changes.
     *
     * @param Server_Account $account     the account for which the username is to be changed
     * @param string         $newUsername the new username
     *
     * @throws Server_Exception always throws an exception because CWP does not support username changes
     */
    public function changeAccountUsername(Server_Account $account, string $newUsername): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'CWP', ':action:' => __trans('username changes')]);
    }

    /**
     * Throws an exception because CWP does not support changing the account domain.
     *
     * @param Server_Account $account   the account for which the domain is to be changed
     * @param string         $newDomain the new domain
     *
     * @throws Server_Exception always throws an exception because CWP does not support changing the account domain
     */
    public function changeAccountDomain(Server_Account $account, string $newDomain): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'CWP', ':action:' => __trans('changing the account domain')]);
    }

    /**
     * Throws an exception because CWP does not support changing the account IP.
     *
     * @param Server_Account $account the account for which the IP is to be changed
     * @param string         $newIp   the new IP
     *
     * @throws Server_Exception always throws an exception because CWP does not support changing the account IP
     */
    public function changeAccountIp(Server_Account $account, string $newIp): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'CWP', ':action:' => __trans('changing the account IP')]);
    }

    /**
     * Makes an API request to the CWP server.
     *
     * @param string $func the function to be called on the server
     * @param array  $data the data to be sent with the request
     *
     * @return mixed returns the response from the server
     */
    private function request(string $func, array $data): mixed
    {
        // Add the access hash to the data array
        $data['key'] = $this->_config['accesshash'];

        // Get the host and port from the config
        $host = $this->_config['host'];
        $port = $this->_config['port'];

        // Construct the URL for the request
        $url = 'https://' . $host . ':' . $port . '/v1/' . $func;

        // Get the HTTP client with options set to ignore SSL verification
        $client = $this->getHttpClient()->withOptions([
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        // Make the request and convert the response to an array
        $request = $client->request('POST', $url, [
            'body' => $data,
        ]);
        $response = $request->toArray();

        // Get the status, result, and message from the response, with default values if they are not set
        $status = $response['status'] ?? 'Error';
        $result = $response['result'] ?? null;
        $msg = $response['msg'] ?? 'CWP did not return a message in it\'s response.';

        // If the status is not 'OK', log an error message and return false
        if ($status !== 'OK') {
            error_log('CWP Server manager error. Status: ' . $status . '. Message: ' . $msg);

            return false;
        }

        // If the function called is 'accountdetail', return the result from the response
        if ($func == 'accountdetail') {
            return $result;
        }

        return true;
    }
}
