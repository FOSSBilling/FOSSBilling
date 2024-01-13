<?php

/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Random\RandomException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

/**
 * cPanel API.
 *
 * @see https://api.docs.cpanel.net/whm/introduction
 */
class Server_Manager_Whm extends Server_Manager
{
    /**
     * @return array
     */
    public static function getForm(): array
    {
        return [
            'label' => 'WHM (cPanel)',
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
            ],
        ];
    }

    /**
     * @return void
     * @throws Server_Exception
     */
    public function init(): void
    {
        if (empty($this->_config['host'])) {
            throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'cPanel WHM', ':missing' => 'hostname'], 2001);
        }

        if (empty($this->_config['username'])) {
            throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'cPanel WHM', ':missing' => 'username'], 2001);
        }

        if (empty($this->_config['password']) && empty($this->_config['accesshash'])) {
            throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'cPanel WHM', ':missing' => 'authentication credentials'], 2001);
        }

        // If port not set, use WHM default.
        $this->_config['port'] = empty($this->_config['port']) ? '2087' : $this->_config['port'];
    }

    /**
     * @param Server_Account|null $account
     * @return string
     */
    public function getLoginUrl(Server_Account $account = null): string
    {
        $host = $this->_config['host'];

        return 'http://' . $host . '/cpanel';
    }

    /**
     * @param Server_Account|null $account
     * @return string
     */
    public function getResellerLoginUrl(Server_Account $account = null): string
    {
        $host = $this->_config['host'];

        return 'http://' . $host . '/whm';
    }

    /**
     * @return true
     * @throws Server_Exception
     */
    public function testConnection(): bool
    {
        $this->_request('version');
        return true;
    }

    // https://docs.cpanel.net/knowledge-base/accounts/reserved-invalid-and-misconfigured-username/

    /**
     * Sends a request to the WHM server and returns the response.
     *
     * This method sends a request to the WHM server using the provided action and parameters.
     * It handles the creation of the HTTP client, the construction of the request URL and headers,
     * and the sending of the request. It also handles any errors that may occur during the request,
     * logging them and throwing a Server_Exception if necessary.
     *
     * @param string $action The action to be performed on the WHM server.
     * @param array $params The parameters to be sent with the request.
     * @return mixed The response from the WHM server, decoded from JSON into a PHP variable.
     * @throws Server_Exception If an error occurs during the request, or if the response from the WHM server indicates an error.
     */
    private function _request(string $action, array $params = []): mixed
    {
        // Create the HTTP client with the necessary options
        $client = $this->getHttpClient()->withOptions([
            'verify_peer' => false,
            'verify_host' => false,
            'timeout' => 90, // Account creation can timeout if set too low - see #1086.
        ]);

        // Construct the request URL
        $url = ($this->_config['secure'] ? 'https' : 'http') . '://' . $this->_config['host'] . ':' . $this->_config['port'] . '/json-api/' . $action;

        // Construct the authorization header
        $username = $this->_config['username'];
        $accessHash = $this->_config['accessHash'];
        $password = $this->_config['password'];
        $authHeader = (!empty($accessHash)) ? 'WHM ' . $username . ':' . $accessHash
            : 'Basic ' . $username . ':' . $password;

        // Log the request
        $this->getLog()->debug(sprintf('Requesting WHM server action "%s" with params "%s" ', $action, print_r($params, true)));

        // Send the request and handle any errors
        try {
            $response = $client->request('POST', $url, [
                'headers' => ['Authorization' => $authHeader],
                'body' => $params,
            ]);
        } catch (HttpExceptionInterface $error) {
            $e = new Server_Exception('HttpClientException: :error', [':error' => $error->getMessage()]);
            $this->getLog()->err($e);
            throw $e;
        }

        // Decode the response from JSON into a PHP variable
        $body = $response->getContent();
        $json = json_decode($body);

        // Check the response for errors and throw a Server_Exception if any are found
        if (!is_object($json)) {
            $msg = sprintf('Function call "%s" response is invalid, body: %s', $action, $body);
            $this->getLog()->crit($msg);

            $placeholders = [':action:' => $action, ':type:' => 'cPanel'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        if (isset($json->cpanelresult->error)) {
            $this->getLog()->crit(sprintf('WHM server response error calling action %s: "%s"', $action, $json->cpanelresult->error));
            $placeholders = ['action' => $action, 'type' => 'cPanel'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        if (isset($json->data->result) && $json->data->result == '0') {
            $this->getLog()->crit(sprintf('WHM server response error calling action %s: "%s"', $action, $json->data->reason));
            $placeholders = [':action:' => $action, ':type:' => 'cPanel'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        if (isset($json->result) && is_array($json->result) && $json->result[0]->status == 0) {
            $this->getLog()->crit(sprintf('WHM server response error calling action %s: "%s"', $action, $json->result[0]->statusmsg));
            $placeholders = [':action:' => $action, ':type:' => 'cPanel'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        if (isset($json->status) && $json->status != '1') {
            $this->getLog()->crit(sprintf('WHM server response error calling action %s: "%s"', $action, $json->statusmsg));
            $placeholders = [':action:' => $action, ':type:' => 'cPanel'];

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        // Return the response
        return $json;
    }

    /**
     * @param string $domain
     * @return string
     * @throws RandomException
     */
    public function generateUsername(string $domain): string
    {
        $processedDomain = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $domain));
        $username = substr($processedDomain, 0, 7) . random_int(0, 9);

        // WHM doesn't allow usernames to start with "test", so replace it with a random string if it does (test3456 would then become something like a62f93456).
        if (str_starts_with($username, 'test')) {
            $username = substr_replace($username, 'a' . bin2hex(random_bytes(2)), 0, 5);
        }

        // WHM doesn't allow usernames to start with a number, so automatically append the letter 'a' to the start of a username that does.
        if (is_numeric(substr($username, 0, 1))) {
            $username = substr_replace($username, 'a', 0, 1);
        }

        return $username;
    }

    /**
     * @param Server_Account $account
     * @return Server_Account
     * @throws Server_Exception
     */
    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        $this->getLog()->info(sprintf('Synchronizing account %s %s with server', $account->getDomain(), $account->getUsername()));

        $action = 'accountsummary';
        $varHash = [
            'user' => $account->getUsername(),
        ];

        $result = $this->_request($action, $varHash);
        if (!isset($result->acct[0])) {
            error_log('Could not synchronize account with cPanel server. Account does not exist.');

            return $account;
        }

        $acc = $result->acct[0];

        $new = clone $account;
        $new->setSuspended($acc->suspended);
        $new->setDomain($acc->domain);
        $new->setUsername($acc->user);
        $new->setIp($acc->ip);

        return $new;
    }

    /**
     * Creates a new account on the WHM server.
     *
     * Sends a request to the WHM server to create a new account with the details provided in the Server_Account object.
     * If the account is a reseller account, it also sets up the reseller and assigns the appropriate ACL list.
     *
     * @param Server_Account $account The account to be created. This object should contain all the necessary details for the new account.
     * @return bool Returns true if the account was successfully created, false otherwise.
     * @throws Server_Exception If an error occurs during the request, or if the response from the WHM server indicates an error.
     */
    public function createAccount(Server_Account $account): bool
    {
        // Log the account creation
        $this->getLog()->info('Creating account ' . $account->getUsername());

        // Get the client and package associated with the account
        $client = $account->getClient();
        $package = $account->getPackage();

        // Check if the package exists on the WHM server, create it if not
        $this->_checkPackageExists($package, true);

        // Prepare the parameters for the API request
        $action = 'createacct';
        $varHash = array(
            'username' => $account->getUsername(),
            'domain' => $account->getDomain(),
            'password' => $account->getPassword(),
            'contactemail' => $client->getEmail(),
            'plan' => $this->_getPackageName($package),
            'useregns' => 0,
        );

        // If the account is a reseller account, add the 'reseller' parameter
        if ($account->getReseller()) {
            $varHash['reseller'] = 1;
        }

        // Send the request to the WHM API
        $json = $this->_request($action, $varHash);
        $result = ($json->result[0]->status == 1);

        // If the account is a reseller account and was successfully created, set up the reseller and assign the ACL list
        if ($result && $account->getReseller()) {

            $params = array(
                'user' => $account->getUsername(),
                'makeowner' => 0,
            );
            $this->_request('setupreseller', $params);

            $params = array(
                'reseller' => $account->getUsername(),
                'acllist' => $package->getAcllist(),
            );
            $this->_request('setacls', $params);
        }

        // Return the result of the account creation
        return $result;
    }

    /**
     * Check if Package exists.
     *
     * @param Server_Package $package
     * @param bool $create
     * @return void
     * @throws Server_Exception
     */
    private function _checkPackageExists(Server_Package $package, bool $create = false): void
    {
        $name = $this->_getPackageName($package);

        $json = $this->_request('listpkgs');
        $packages = $json->package;

        $exists = false;
        foreach ($packages as $p) {
            if ($p->name == $name) {
                $exists = true;

                break;
            }
        }

        if (!$create) {
            return;
        }

        if (!$exists) {
            $varHash = [
                'name' => $name,
                'quota' => $package->getQuota(),
                'bwlimit' => $package->getBandwidth(),
                'maxsub' => $package->getMaxSubdomains(),
                'maxpark' => $package->getMaxParkedDomains(),
                'maxaddon' => $package->getMaxDomains(),
                'maxftp' => $package->getMaxFtp(),
                'maxsql' => $package->getMaxSql(),
                'maxpop' => $package->getMaxPop(),

                'cgi' => $package->getCustomValue('cgi'),
                'cpmod' => $package->getCustomValue('cpmod'),
                'maxlst' => $package->getCustomValue('maxlst'),
                'hasshell' => $package->getCustomValue('hasshell'),
            ];

            $this->_request('addpkg', $varHash);
        }

    }

    /**
     * @param Server_Package $package
     * @return string
     */
    private function _getPackageName(Server_Package $package): string
    {
        return $this->_config['username'] . '_' . $package->getName();
    }

    /**
     * @param Server_Account $account
     * @return true
     * @throws Server_Exception
     */
    public function suspendAccount(Server_Account $account): bool
    {
        $this->getLog()->info('Suspending account ' . $account->getUsername());

        $action = 'suspendacct';
        $varHash = [
            'user' => $account->getUsername(),
            'reason' => $account->getNote(),
        ];

        $this->_request($action, $varHash);

        return true;
    }

    /**
     * @param Server_Account $account
     * @return true
     * @throws Server_Exception
     */
    public function unsuspendAccount(Server_Account $account): bool
    {
        $this->getLog()->info('Activating account ' . $account->getUsername());

        $action = 'unsuspendacct';
        $varHash = [
            'user' => $account->getUsername(),
        ];

        $this->_request($action, $varHash);

        return true;
    }

    /**
     * @param Server_Account $account
     * @return true
     * @throws Server_Exception
     */
    public function cancelAccount(Server_Account $account): bool
    {
        $this->getLog()->info('Canceling account ' . $account->getUsername());

        $action = 'removeacct';
        $varHash = [
            'user' => $account->getUsername(),
            'keepdns' => 0,
        ];

        $this->_request($action, $varHash);

        return true;
    }

    /**
     * @param Server_Account $account
     * @param Server_Package $package
     * @return true
     * @throws Server_Exception
     */
    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        $this->getLog()->info('Changing account ' . $account->getUsername() . ' package');
        $this->_checkPackageExists($package, true);

        $varHash = [
            'user' => $account->getUsername(),
            'pkg' => $this->_getPackageName($package),
        ];

        $this->_request('changepackage', $varHash);

        return true;
    }

    /**
     * @param Server_Account $account
     * @param string $newPassword
     * @return true
     * @throws Server_Exception
     */
    public function changeAccountPassword(Server_Account $account, string $newPassword): bool
    {
        $this->getLog()->info('Changing account ' . $account->getUsername() . ' password');

        $action = 'passwd';

        $varHash = [
            'user' => $account->getUsername(),
            'pass' => $newPassword,
            'db_pass_update' => true,
        ];

        $result = $this->_request($action, $varHash);
        if (isset($result->passwd[0]) && $result->passwd[0]->status == 0) {
            throw new Server_Exception($result->passwd[0]->statusmsg);
        }

        return true;
    }

    /**
     * @param Server_Account $account
     * @param $newUsername
     * @return true
     * @throws Server_Exception
     */
    public function changeAccountUsername(Server_Account $account, $newUsername): bool
    {
        $this->getLog()->info('Changing account ' . $account->getUsername() . ' username');

        $action = 'modifyacct';
        $varHash = [
            'user' => $account->getUsername(),
            'newuser' => $newUsername,
        ];

        $this->_request($action, $varHash);

        return true;
    }

    /**
     * @param Server_Account $account
     * @param $newDomain
     * @return true
     * @throws Server_Exception
     */
    public function changeAccountDomain(Server_Account $account, $newDomain): bool
    {
        $this->getLog()->info('Changing account ' . $account->getUsername() . ' domain');

        $action = 'modifyacct';
        $varHash = [
            'user' => $account->getUsername(),
            'domain' => $newDomain,
        ];

        $this->_request($action, $varHash);

        return true;
    }

    /**
     * @param Server_Account $account
     * @param $newIp
     * @return true
     * @throws Server_Exception
     */
    public function changeAccountIp(Server_Account $account, $newIp): bool
    {
        $this->getLog()->info('Changing account ' . $account->getUsername() . ' ip');

        $action = 'setsiteip';
        $varHash = [
            'domain' => $account->getDomain(),
            'ip' => $newIp,
        ];

        $this->_request($action, $varHash);

        return true;
    }

    /**
     * @return array
     * @throws Server_Exception
     */
    public function getPkgs(): array
    {
        $pkgs = $this->_request('listpkgs');
        $return = [];

        foreach ($pkgs->package as $pkg) {
            $return[] = [
                'title' => $pkg->name,
                'name' => $pkg->name,
                'feature_list' => $pkg->FEATURELIST,
                'theme' => $pkg->CPMOD,
                'quota' => $pkg->QUOTA,
                'bandwidth' => $pkg->BWLIMIT,
                'max_ftp' => $pkg->MAXFTP,
                'max_sql' => $pkg->MAXSQL,
                'max_emails' => $pkg->MAXLST,
                'max_sub' => $pkg->MAXSUB,
                'max_pop' => $pkg->MAXPOP,
                'max_park' => $pkg->MAXPARK,
                'max_addon' => $pkg->MAXADDON,
                'has_shell' => $pkg->HASSHELL == 'n' ? 0 : 1,
                'has_ip' => $pkg->IP == 'n' ? 0 : 1,
                'has_cgi' => $pkg->CGI == 'n' ? 0 : 1,
                'has_frontpage' => $pkg->FRONTPAGE == 'n' ? 0 : 1,
                'free_registration' => 0,
                'free_transfer' => 0,
                'free_renewal' => 0,
            ];
        }

        return $return;
    }

    /**
     * @param Server_Account $a
     * @param Server_Package $p
     * @return true
     * @throws Server_Exception
     */
    private function modifyAccountPackage(Server_Account $a, Server_Package $p)
    {
        $this->getLog()->info('Midifying account ' . $a->getUsername());

        $package = $p;
        $action = 'modifyacct';

        $varHash = [
            'user' => $a->getUsername(),
            'domain' => $a->getDomain(),
            'HASCGI' => $package->getHasCgi(),
            'CPTHEME' => $package->getTheme(),
            'LANG' => $package->getLanguage(),
            'MAXPOP' => $package->getMaxPop(),
            'MAXFTP' => $package->getMaxFtp(),
            'MAXLST' => $package->getMaxEmailLists(),
            'MAXSUB' => $package->getMaxSubdomains(),
            'MAXPARK' => $package->getMaxParkedDomains(),
            'MAXADDON' => $package->getMaxAddons(),
            'MAXSQL' => $package->getMaxSql(),
            'shell' => $package->getHasShell(),
        ];

        $this->_request($action, $varHash);

        return true;
    }
}
