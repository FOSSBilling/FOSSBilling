<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Manager_ISPManager extends Server_Manager
{
    /**
     * Return server manager parameters.
     */
    public static function getForm(): array
    {
        return [
            'label' => 'ISPManager',
            'form' => [
                'credentials' => [
                    'fields' => [
                        [
                            'name' => 'username',
                            'type' => 'text',
                            'label' => 'username',
                            'placeholder' => 'root',
                            'required' => true,
                        ],
                        [
                            'name' => 'password',
                            'type' => 'passwod',
                            'label' => 'Password',
                            'placeholder' => 'Password',
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
        return 'https://' . $this->_config['host'] . ':' . $this->getPort() . '/ispmgr';
    }

    public function getPort(): int|string
    {
        $port = $this->_config['port'];

        if (filter_var($port, FILTER_VALIDATE_INT) !== false && $port >= 0 && $port <= 65535) {
            return $this->_config['port'];
        } else {
            return 1500;
        }
    }

    public function generateUsername(string $domain): string
    {
        $processedDomain = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $domain));
        $prefix = $this->_config['config']['userprefix'] ?? '';
        $username = $prefix . substr($processedDomain, 0, 7) . random_int(0, 9);

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
        // Make request and check sys info
        $result = $this->request();
        if (intval($result) == 0) {
            throw new Server_Exception('Failed to connect to the :type: server. Please verify your credentials and configuration', [':type:' => 'ISPManager']);
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

        // Create user
        $fields = array(
            'out' => 'json',
            'func' => 'user.add',
            'status' => 'on',
            'name' => $account->getUsername(),
            'fullname' => trim($client->getFullName()),
            'passwd' => $account->getPassword(),
            'confirm' => $account->getPassword(),
            'preset' => $account->getPackage()->getName(),
            'addinfo' => 'off',
            'owner' => $this->_config['username'],
        );
        $result1 = $this->request($fields);
        if($result1 === false){
            $placeholders = [':action:' => __trans('create user'), ':type:' => 'ISPManager'];
            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        // Create DNS domain
        $fields = array(
            'out' => 'json',
            'func' => 'domain.edit',
            'displayname' => $account->getDomain(),
            'owner' => $account->getUsername(),
        );
        $result2 = $this->request($fields);
        if($result2 === false){
            $placeholders = [':action:' => __trans('create user'), ':type:' => 'ISPManager'];
            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        // Create web domain
        $fields = array(
            'out' => 'json',
            'func' => 'webdomain.edit',
            'name' => $account->getDomain(),
            'owner' => $account->getUsername(),
            'email' => 'webmaster@' . $account->getDomain(),
        );
        $result3 = $this->request($fields);
        if($result3 === false){
            $placeholders = [':action:' => __trans('create user'), ':type:' => 'ISPManager'];
            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        // Create mail domain
        $fields = array(
            'out' => 'json',
            'func' => 'emaildomain.edit',
            'name' => $account->getDomain(),
            'owner' => $account->getUsername(),
            'ipaddr' => $this->_config['ip'],
        );
        $result4 = $this->request($fields);
        if($result4 === false){
            $placeholders = [':action:' => __trans('create user'), ':type:' => 'ISPManager'];
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
        // Suspend user
        $fields = array(
            'out' => 'json',
            'func' => 'user.suspend',
            'elid' => $account->getUsername(),
        );
        $result1 = $this->request($fields);
        if($result1 === false){
            $placeholders = [':action:' => __trans('suspend account'), ':type:' => 'ISPManager'];
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
        // Suspend user
        $fields = array(
            'out' => 'json',
            'func' => 'user.resume',
            'elid' => $account->getUsername(),
        );
        $result1 = $this->request($fields);
        if($result1 === false){
            $placeholders = [':action:' => __trans('unsuspend account'), ':type:' => 'ISPManager'];
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
        // Cancel user
        $fields = array(
            'out' => 'json',
            'func' => 'user.delete',
            'elid' => $account->getUsername(),
        );
        $result1 = $this->request($fields);
        if($result1 === false){
            $placeholders = [':action:' => __trans('cancel account'), ':type:' => 'ISPManager'];
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
        // Change account password
        $fields = array(
            'out' => 'json',
            'func' => 'user.edit',
            'elid' => $account->getUsername(),
            'preset' => $package->getName(),
        );
        $result1 = $this->request($fields);
        if($result1 === false){
            $placeholders = [':action:' => __trans('change account package'), ':type:' => 'ISPManager'];
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
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'ISPManager', ':action:' => __trans('username changes')]);
    }

    /**
     * Change account domain on server.
     *
     * @throws Server_Exception
     */
    public function changeAccountDomain(Server_Account $account, $newDomain): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'ISPManager', ':action:' => __trans('changing the account domain')]);
    }

    /**
     * Change account password on server.
     *
     * @throws Server_Exception
     */
    public function changeAccountPassword(Server_Account $account, string $newPassword): bool
    {
        // Change account password
        $fields = array(
            'out' => 'json',
            'func' => 'user.edit',
            'elid' => $account->getUsername(),
            'passwd' => $newPassword,
            'confirm' => $newPassword,
        );
        $result1 = $this->request($fields);
        if($result1 === false){
            $placeholders = [':action:' => __trans('change account password'), ':type:' => 'ISPManager'];
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
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'ISPManager', ':action:' => __trans('changing the account IP')]);
    }

    /**
     * @throws Server_Exception
     */
    private function request($fields = array()): mixed
    {
        $url = 'https://' . $this->_config['host'] . ':' . $this->getPort() . '/ispmgr?';

        // Send POST query
        $client = $this->getHttpClient()->withOptions([
            'verify_peer' => false,
            'verify_host' => false,
            'timeout' => 30,
        ]);

        // Authentication
        $field_auth = http_build_query(array(
            'out' => 'json',
            'func' => 'auth',
            'username' => $this->_config['username'],
            'password' => $this->_config['password'],
        ));
        $response = $client->request('GET', $url . $field_auth);
        $result = $response->toArray();
        $auth_id = $result['doc']['auth']['$id'];

        if($fields != array()){
            $fields['auth'] = $auth_id;
            $fields['sok'] = 'ok';
            $field_query = http_build_query($fields);
            $response = $client->request('GET', $url . $field_query);
            $result = $response->toArray();
        }

        if ($response->getStatusCode() != 200) {
            throw new Server_Exception('Failed to connect to the :type: server. Please verify your credentials and configuration', [':type:' => 'ISPManager']);
        }

        return $response->getStatusCode() == 200;

    }
}
