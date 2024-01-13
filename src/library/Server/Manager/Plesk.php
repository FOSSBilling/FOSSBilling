<?php

use PleskX\Api\Client;

/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Manager_Plesk extends Server_Manager
{
    private ?Client $_client = null;

    /**
     * @return string[]
     */
    public static function getForm(): array
    {
        return [
            'label' => 'Plesk',
        ];
    }

    /**
     * @return void
     */
    public function init(): void
    {
        $this->_config['port'] = empty($this->_config['port']) ? 8443 : $this->_config['port'];
        $this->_client = new PleskX\Api\Client($this->_config['host'], $this->_config['port']);
        $this->_client->setCredentials($this->_config['username'], $this->_config['password']);
    }

    /**
     * @param Server_Account|null $account
     * @return string
     */
    public function getResellerLoginUrl(Server_Account $account = null): string
    {
        return $this->getLoginUrl();
    }

    /**
     * @param Server_Account|null $account
     * @return string
     */
    public function getLoginUrl(Server_Account $account = null): string
    {
        $protocol = $this->_config['secure'] ? 'https' : 'http';
        $url = $protocol . '://' . $this->_config['host'] . ':' . $this->_config['port'];
        if ($account) {
            $sessionId = $this->_client->session()->create($account->getUsername(), $_SERVER['REMOTE_ADDR']);
            $url .= '/enterprise/rsession_init.php?PHPSESSID=' . $sessionId;
        }

        return $url;
    }

    /**
     * @return true
     * @throws Server_Exception
     */
    public function testConnection(): bool
    {
        $stats = $this->_client->server()->getStatistics();

        if ($stats->other->uptime < 0) {
            throw new Server_Exception('Failed to connect to the :type: server. Please verify your credentials and configuration', [':type:' => 'Plesk']);
        }

        return true;
    }

    /**
     * @param Server_Account $account
     * @return never
     * @throws Server_Exception
     */
    public function synchronizeAccount(Server_Account $account): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'Plesk', ':action:' => __trans('account synchronization')]);
    }

    /**
     * @param Server_Account $account
     * @return true
     * @throws Server_Exception
     */
    public function createAccount(Server_Account $account): bool
    {
        $this->getLog()->info('Creating account ' . $account->getUsername());

        if ($account->getReseller()) {
            $ips = $this->_getIps();
            foreach ($ips['exclusive'] as $key => $ip) {
                if (!$ip['empty']) {
                    unset ($ips['exclusive'][$key]);
                }
            }

            /*
    		if (count($ips['exclusive']) == 0) {
    			// Disabled. Resellers can also use shared IP addresses.
                // throw new Server_Exception('Out of free IP addresses');
            }
            */
            if ((is_countable($ips['exclusive']) ? count($ips['exclusive']) : 0) > 0) {
                $ips['exclusive'] = array_values($ips['exclusive']);
                $rand = array_rand($ips['exclusive']);
                $account->setIp($ips['exclusive'][$rand]['ip']);
            }
        }

        $id = $this->_createClient($account);
        $client = $account->getClient();

        if (!$id) {
            $placeholders = [':action:' => __trans('create account'), ':type"' => 'Plesk'];
            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        $client->setId((string)$id);

        $this->setSubscription($account);

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

    /**
     * @return array|array[]
     */
    private function _getIps(): array
    {
        $response = $this->_client->ip()->get();

        $ips = array('shared' => array(), 'exclusive' => array());

        foreach ($response as $ip) {
            $ips[(string)$ip->type][] = array(
                'ip' => (string)$ip->ipAddress,
                'empty' => empty($ip->default),
            );
        }

        return $ips;
    }

    // ??

    /**
     * Creates a new client account.
     *
     * @param Server_Account $a
     * @return bool|int client's Plesk id
     */
    private function _createClient(Server_Account $a): bool|int
    {
        $client = $a->getClient();

        $props = [
            'cname' => $client->getCompany(),
            'pname' => $client->getFullname(),
            'login' => $a->getUsername(),
            'passwd' => $a->getPassword(),
            'phone' => $client->getTelephone(),
            'fax' => $client->getFax(),
            'email' => $client->getEmail(),
            'address' => $client->getAddress1(),
            'city' => $client->getCity(),
            'state' => $client->getState(),
            'description' => 'Created using FOSSBilling.',
        ];

        if ($a->getReseller()) {
            $this->_client->reseller()->create($props);
        } else {
            $this->_client->customer()->create($props);
        }

        return true;
    }

    /**
     * @param Server_Account $account
     * @return void
     */
    public function setSubscription(Server_Account $account): void
    {
        $this->getLog()->info('Setting subscription for account ' . $account->getUsername());

        $this->_client->webspace()->request($this->_createSubscriptionProps($account, 'add'));
    }

    /**
     * @param Server_Account $account
     * @param $action
     * @return array[]
     */
    private function _createSubscriptionProps(Server_Account $account, $action): array
    {
        $package = $account->getPackage();

        return array(
            $action => array(
                'gen_setup' => array(
                    'name' => $account->getDomain(),
                    'owner-login' => $account->getUsername(),
                    'htype' => 'vrt_hst',
                    'ip_address' => $account->getIp()
                ),
                'hosting' => array(
                    'vrt_hst' => array(
                        'property' => array(
                            array(
                                'name' => 'ftp_login',
                                'value' => $account->getUsername(),
                            ),
                            array(
                                'name' => 'ftp_password',
                                'value' => $account->getPassword(),
                            ),
                            array(
                                'name' => 'php',
                                'value' => 'true',
                            ),
                            array(
                                'name' => 'ssl',
                                'value' => 'true',
                            ),
                            array(
                                'name' => 'cgi',
                                'value' => 'true',
                            ),
                        ),
                        'ip_address' => $account->getIp(),
                    ),
                ),
                'limits' => array(
                    'limit' => array(
                        array(
                            'name' => 'max_db',
                            'value' => $package->getMaxSql() ?: 0,
                        ),
                        array(
                            'name' => 'max_maillists',
                            'value' => $package->getMaxEmailLists() ?: 0,
                        ),
                        array(
                            'name' => 'max_maillists',
                            'value' => $package->getMaxEmailLists() ?: 0,
                        ),
                        array(
                            'name' => 'max_box',
                            'value' => $package->getMaxPop() ?: 0,
                        ),
                        array(
                            'name' => 'max_traffic',
                            'value' => $package->getBandwidth() ? $package->getBandwidth() * 1024 * 1024 : 0,
                        ),
                        array(
                            'name' => 'disk_space',
                            'value' => $package->getQuota() ? $package->getQuota() * 1024 * 1024 : 0,
                        ),
                        array(
                            'name' => 'max_subdom',
                            'value' => $package->getMaxSubdomains() ?: 0,
                        ),
                        array(
                            'name' => 'max_subftp_users',
                            'value' => $package->getMaxFtp() ?: 0,
                        ),
                        array(
                            'name' => 'max_site',
                            'value' => $package->getMaxDomains() ?: 0,
                        ),
                    ),
                ),
                'permissions' => array(
                    'permission' => array(
                        array(
                            'name' => 'manage_subdomains',
                            'value' => $package->getMaxSubdomains() ? 'true' : 'false',
                        ),
                        array(
                            'name' => 'manage_dns',
                            'value' => 'true'
                        ),
                        array(
                            'name' => 'manage_crontab',
                            'value' => $package->getHasCron() ? 'true' : 'false',
                        ),
                        array(
                            'name' => 'manage_anonftp',
                            'value' => $package->getHasAnonymousFtp() ? 'true' : 'false',
                        ),
                        array(
                            'name' => 'manage_sh_access',
                            'value' => $package->getHasShell() ? 'true' : 'false',
                        ),
                        array(
                            'name' => 'manage_maillists',
                            'value' => $package->getMaxEmailLists() ? 'true' : 'false',
                        ),
                        array(
                            'name' => 'create_domains',
                            'value' => 'true',
                        ),
                        array(
                            'name' => 'manage_phosting',
                            'value' => 'true',
                        ),
                        array(
                            'name' => 'manage_quota',
                            'value' => $account->getReseller() ? 'true' : 'false',
                        ),
                        array(
                            'name' => 'manage_not_chroot_shell',
                            'value' => $package->getHasShell() ? 'true' : 'false',
                        ),
                        array(
                            'name' => 'manage_domain_aliases',
                            'value' => 'true',
                        ),
                        array(
                            'name' => 'manage_subftp',
                            'value' => $package->getMaxFtp() ? 'true' : 'false',
                        ),
                        array(
                            'name' => 'manage_spamfilter',
                            'value' => $package->getHasSpamFilter() ? 'true' : 'false',
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * @param Server_Account $account
     * @return void
     */
    public function createServicePlan(Server_Account $account)
    {

    }

    /**
     * @param Server_Account $account
     * @return mixed
     */
    public function deleteSubscription(Server_Account $account): mixed
    {
        return $this->_client->webspace()->delete('name', $account->getDomain());
    }

    /**
     * @param Server_Account $account
     * @param bool $suspend
     * @return bool
     */
    public function suspendAccount(Server_Account $account, bool $suspend = true): bool
    {
        if ($account->getReseller()) {
            $result = $this->_client->reseller()->setProperties('login', $account->getUsername(), ['status' => 16]);
        } else {
            $result = $this->_client->customer()->setProperties('login', $account->getUsername(), ['status' => 16]);
        }

        return $result;
    }

    /**
     * @param Server_Account $account
     * @return bool
     */
    public function unsuspendAccount(Server_Account $account): bool
    {
        if ($account->getReseller()) {
            $result = $this->_client->reseller()->setProperties('login', $account->getUsername(), ['status' => 0]);
        } else {
            $result = $this->_client->customer()->setProperties('login', $account->getUsername(), ['status' => 0]);
        }

        return $result;
    }

    /**
     * @param Server_Account $account
     * @return bool
     */
    public function cancelAccount(Server_Account $account): bool
    {
        if ($account->getReseller()) {
            $result = $this->_client->reseller()->delete('login', $account->getUsername());
        } else {
            $result = $this->_client->customer()->delete('login', $account->getUsername());
        }

        return $result;
    }

    /**
     * @param Server_Account $account
     * @param Server_Package $package
     * @return true
     * @throws Server_Exception
     */
    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        $domainId = null;
        $id = $this->_modifyClient($account);
        $client = $account->getClient();
        if (!$id) {
            throw new Server_Exception('Can\'t modify client');
        } else {
            $client->setId($id);
        }

        $account->setPackage($package);
        $this->updateSubscription($account);

        if ($account->getReseller()) {
            $this->_addNs($account, $domainId);
        }

        return true;
    }

    /**
     * @param Server_Account $account
     * @return mixed
     */
    private function _modifyClient(Server_Account $account): mixed
    {
        if ($account->getReseller()) {
            $result = $this->_client->reseller()->setProperties('login', $account->getUsername(), $this->_createClientProps($account));
        } else {
            $result = $this->_client->customer()->setProperties('login', $account->getUsername(), $this->_createClientProps($account));
        }

        return $result;
    }

    /**
     * @param Server_Account $account
     * @return array
     */
    private function _createClientProps(Server_Account $account): array
    {
        $client = $account->getClient();

        $props = [
            'cname' => $client->getCompany(),
            'pname' => $client->getFullname(),
            'login' => $account->getUsername(),
            'passwd' => $account->getPassword(),
            'phone' => $client->getTelephone(),
            'fax' => $client->getFax(),
            'email' => $client->getEmail(),
            'address' => $client->getAddress1(),
            'city' => $client->getCity(),
            'state' => $client->getState(),
        ];

        return $props;
    }

    /**
     * @param Server_Account $account
     * @return void
     */
    public function updateSubscription(Server_Account $account): void
    {
        $this->getLog()->info('Updating subscription for account ' . $account->getUsername());

        $this->_client->webspace()->request($this->_createSubscriptionProps($account, 'set'));
    }

    /**
     * @param Server_Account $account
     * @param $domainId
     * @return true
     */
    private function _addNs(Server_Account $account, $domainId): bool
    {
        // Will be done in the future

        return true;
    }

    /**
     * @param Server_Account $account
     * @param string $newPassword
     * @return bool
     */
    public function changeAccountPassword(Server_Account $account, string $newPassword): bool
    {
        $this->getLog()->info('Changing password for account ' . $account->getUsername());

        if ($account->getReseller()) {
            $result = $this->_client->reseller()->setProperties('login', $account->getUsername(), ['passwd' => $newPassword]);
        } else {
            $result = $this->_client->customer()->setProperties('login', $account->getUsername(), ['passwd' => $newPassword]);
        }

        return $result;
    }

    /**
     * @param Server_Account $account
     * @param $newUsername
     * @return never
     * @throws Server_Exception
     */
    public function changeAccountUsername(Server_Account $account, string $newUsername): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'Plesk', ':action:' => __trans('username changes')]);
    }

    /**
     * @param Server_Account $account
     * @param $newDomain
     * @return void
     */
    public function changeAccountDomain(Server_Account $account, string $newDomain): bool
    {
        $this->getLog()->info('Updating domain for account ' . $account->getUsername());

        $account->setDomain($newDomain);

        $params = [
            'set' => [
                'filter' => [
                    'owner-login' => $account->getUsername(),
                ],
                'values' => [
                    'gen_setup' => [
                        'name' => $newDomain,
                    ],
                ],
            ],
        ];

        $this->_client->webspace()->request($params);
    }

    /**
     * @param Server_Account $account
     * @param $newIp
     * @return never
     * @throws Server_Exception
     */
    public function changeAccountIp(Server_Account $account, string $newIp): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'Plesk', ':action:' => __trans('changing the account IP')]);
    }

    /**
     * @param Server_Account $account
     * @param string $newIp
     * @return void
     */
    private function _setIp(Server_Account $account, string $newIp)
    {
        $params = [
            'reseller' => [
                'ippool-add-ip' => [
                    'reseller-id' => $account->getUsername(),
                    'ip' => [
                        'ip-address' => $newIp,
                    ],
                ],
            ],
        ];

        $response = $this->_client->request($params);
    }

    /**
     * @param Server_Account $account
     * @param $domainId
     * @return array
     */
    private function _getNs(Server_Account $account, $domainId)
    {
        $response = $this->_client->dns()->get('domain_id', $domainId);

        $ns = [];

        foreach ($response->dns->get_rec->result as $dns) {
            if ($dns->data->type == 'NS') {
                $ns[] = (string)$dns->id;
            }
        }

        return $ns;
    }

    /**
     * Removes DNS records from the Plesk server.
     *
     * Sends a request to the Plesk API to remove DNS records.
     * The DNS records to be removed are identified by their IDs.
     *
     * @param array $ns An array of DNS record IDs to be removed.
     * @return bool Returns true after the DNS records have been removed.
     */
    private function _removeDns($ns)
    {
        // Iterate over each DNS record ID
        foreach ($ns as $key => $id) {
            // Prepare the parameters for the API request
            $params['dns']['del_rec']['filter']['id' . $key] = $id;
        }

        // If there are no DNS records to remove, return true
        if (empty($params)) {
            return true;
        }

        // Send the request to the Plesk API to remove the DNS records
        $response = $this->_client->request($params);

        // Return true after the DNS records have been removed
        return true;
    }

    /**
     * Changes the IP type of a given account to 'shared'.
     *
     * Sends a request to the Plesk API to change the IP type of the given account to 'shared'.
     * The account is identified by its reseller ID and IP address.
     *
     * @param Server_Account $account The account for which the IP type should be changed.
     * @return bool Returns true if the IP type was successfully changed to 'shared', false otherwise.
     */
    private function _changeIpType(Server_Account $account)
    {
        // Get the client associated with the account
        $client = $account->getClient();

        // Prepare the parameters for the API request
        $params = [
            'reseller' => [
                'ippool-set-ip' => [
                    'reseller-id' => $client->getId(), // The reseller ID of the client
                    'filter' => [
                        'ip-address' => $account->getIp(), // The IP address of the account
                    ],
                    'values' => [
                        'ip-type' => 'shared', // The new IP type
                    ],
                ],
            ],
        ];

        // Send the request to the Plesk API
        $response = $this->_client->reseller()->request($params);

        // Check if the IP type was successfully changed to 'shared'
        return isset($response->reseller->{'ippool-set-ip'}->result->status)
            && $response->reseller->{'ippool-set-ip'}->result->status == 'ok';
    }
}
