<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
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
     * Returns the form configuration for the WHM (cPanel) server manager.
     *
     * @return array the form configuration as an associative array
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
     * Initializes the WHM server manager.
     * Checks if the necessary configuration options are set and throws an exception if any are missing.
     *
     * @throws Server_Exception if any necessary configuration options are missing
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
     * Returns the login URL for a cPanel account.
     *
     * @param Server_Account|null $account The account for which to get the login URL. This parameter is currently not used.
     *
     * @return string the login URL
     */
    public function getLoginUrl(Server_Account $account = null): string
    {
        $host = $this->_config['host'];

        return 'http://' . $host . '/cpanel';
    }

    /**
     * Returns the login URL for a WHM reseller account.
     *
     * @param Server_Account|null $account The account for which to get the login URL. This parameter is currently not used.
     *
     * @return string the login URL
     */
    public function getResellerLoginUrl(Server_Account $account = null): string
    {
        $host = $this->_config['host'];

        return 'http://' . $host . '/whm';
    }

    /**
     * Tests the connection to the WHM server.
     * Sends a request to the WHM server to get its version.
     *
     * @return true if the connection was successful
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function testConnection(): bool
    {
        $this->request('version');

        return true;
    }

    /**
     * Generates a username for a new account on the WHM server.
     * The username is generated based on the domain name, with some modifications to comply with WHM's username restrictions.
     *
     * @param string $domain the domain name for which to generate a username
     *
     * @return string the generated username
     *
     * @throws RandomException if an error occurs during the generation of a random number
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
     * Synchronizes an account with the WHM server.
     * Sends a request to the WHM server to get the account's details and updates the Server_Account object accordingly.
     *
     * @param Server_Account $account the account to be synchronized
     *
     * @return Server_Account the updated account
     *
     * @throws Server_Exception if an error occurs during the request, or if the account does not exist on the WHM server
     */
    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        $this->getLog()->info(sprintf('Synchronizing account %s %s with server', $account->getDomain(), $account->getUsername()));

        $action = 'accountsummary';
        $varHash = [
            'user' => $account->getUsername(),
        ];

        $result = $this->request($action, $varHash);
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
     * Sends a request to the WHM server to create a new account with the details provided in the Server_Account object.
     * If the account is a reseller account, it also sets up the reseller and assigns the appropriate ACL list.
     *
     * @param Server_Account $account The account to be created. This object should contain all the necessary details for the new account.
     *
     * @return bool returns true if the account was successfully created, false otherwise
     *
     * @throws Server_Exception if an error occurs during the request, or if the response from the WHM server indicates an error
     */
    public function createAccount(Server_Account $account): bool
    {
        // Log the account creation
        $this->getLog()->info('Creating account ' . $account->getUsername());

        // Get the client and package associated with the account
        $client = $account->getClient();
        $package = $account->getPackage();

        // Check if the package exists on the WHM server, create it if not
        $this->checkPackageExists($package, true);

        // Prepare the parameters for the API request
        $action = 'createacct';
        $varHash = [
            'username' => $account->getUsername(),
            'domain' => $account->getDomain(),
            'password' => $account->getPassword(),
            'contactemail' => $client->getEmail(),
            'plan' => $this->getPackageName($package),
            'useregns' => 0,
        ];

        // If the account is a reseller account, add the 'reseller' parameter
        if ($account->getReseller()) {
            $varHash['reseller'] = 1;
        }

        // Send the request to the WHM API
        $json = $this->request($action, $varHash);
        $result = ($json->result[0]->status == 1);

        // If the account is a reseller account and was successfully created, set up the reseller and assign the ACL list
        if ($result && $account->getReseller()) {
            $params = [
                'user' => $account->getUsername(),
                'makeowner' => 0,
            ];
            $this->request('setupreseller', $params);

            $params = [
                'reseller' => $account->getUsername(),
                'acllist' => $package->getAcllist(),
            ];
            $this->request('setacls', $params);
        }

        // Return the result of the account creation
        return $result;
    }

    /**
     * Suspends an account on the WHM server.
     *
     * @param Server_Account $account the account to be suspended
     *
     * @return bool returns true if the account was successfully suspended
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function suspendAccount(Server_Account $account): bool
    {
        // Log the suspension
        $this->getLog()->info('Suspending account ' . $account->getUsername());

        // Define the action and parameters for the API request
        $action = 'suspendacct';
        $varHash = [
            'user' => $account->getUsername(),
            'reason' => $account->getNote(),
        ];

        // Send the request to the WHM API
        $this->request($action, $varHash);

        return true;
    }

    /**
     * Unsuspends an account on the WHM server.
     *
     * @param Server_Account $account the account to be unsuspended
     *
     * @return bool returns true if the account was successfully unsuspended
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function unsuspendAccount(Server_Account $account): bool
    {
        // Log the unsuspension
        $this->getLog()->info('Activating account ' . $account->getUsername());

        // Define the action and parameters for the API request
        $action = 'unsuspendacct';
        $varHash = [
            'user' => $account->getUsername(),
        ];

        // Send the request to the WHM API
        $this->request($action, $varHash);

        return true;
    }

    /**
     * Cancels an account on the WHM server.
     *
     * @param Server_Account $account the account to be cancelled
     *
     * @return bool returns true if the account was successfully cancelled
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function cancelAccount(Server_Account $account): bool
    {
        // Log the cancellation
        $this->getLog()->info('Canceling account ' . $account->getUsername());

        // Define the action and parameters for the API request
        $action = 'removeacct';
        $varHash = [
            'user' => $account->getUsername(),
            'keepdns' => 0,
        ];

        // Send the request to the WHM API
        $this->request($action, $varHash);

        return true;
    }

    /**
     * Changes the package of an account on the WHM server.
     *
     * @param Server_Account $account the account for which to change the package
     * @param Server_Package $package the new package
     *
     * @return bool returns true if the package was successfully changed
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        // Log the package change
        $this->getLog()->info('Changing account ' . $account->getUsername() . ' package');

        // Check if the package exists on the WHM server, create it if not
        $this->checkPackageExists($package, true);

        // Define the action and parameters for the API request
        $varHash = [
            'user' => $account->getUsername(),
            'pkg' => $this->getPackageName($package),
        ];

        // Send the request to the WHM API
        $this->request('changepackage', $varHash);

        return true;
    }

    /**
     * Changes the password of an account on the WHM server.
     *
     * @param Server_Account $account     the account for which to change the password
     * @param string         $newPassword the new password
     *
     * @return bool returns true if the password was successfully changed
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function changeAccountPassword(Server_Account $account, string $newPassword): bool
    {
        // Log the password change
        $this->getLog()->info('Changing account ' . $account->getUsername() . ' password');

        // Define the action and parameters for the API request
        $action = 'passwd';
        $varHash = [
            'user' => $account->getUsername(),
            'pass' => $newPassword,
            'db_pass_update' => true,
        ];

        // Send the request to the WHM API
        $result = $this->request($action, $varHash);

        // If the password change failed, throw an exception
        if (isset($result->passwd[0]) && $result->passwd[0]->status == 0) {
            throw new Server_Exception($result->passwd[0]->statusmsg);
        }

        return true;
    }

    /**
     * Changes the username of an account on the WHM server.
     *
     * @param Server_Account $account     the account for which to change the username
     * @param string         $newUsername the new username
     *
     * @return bool returns true if the username was successfully changed
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function changeAccountUsername(Server_Account $account, string $newUsername): bool
    {
        // Log the username change
        $this->getLog()->info('Changing account ' . $account->getUsername() . ' username');

        // Define the action and parameters for the API request
        $action = 'modifyacct';
        $varHash = [
            'user' => $account->getUsername(),
            'newuser' => $newUsername,
        ];

        // Send the request to the WHM API
        $this->request($action, $varHash);

        return true;
    }

    /**
     * Changes the domain of an account on the WHM server.
     *
     * @param Server_Account $account   the account for which to change the domain
     * @param string         $newDomain the new domain
     *
     * @return bool returns true if the domain was successfully changed
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function changeAccountDomain(Server_Account $account, string $newDomain): bool
    {
        // Log the domain change
        $this->getLog()->info('Changing account ' . $account->getUsername() . ' domain');

        // Define the action and parameters for the API request
        $action = 'modifyacct';
        $varHash = [
            'user' => $account->getUsername(),
            'domain' => $newDomain,
        ];

        // Send the request to the WHM API
        $this->request($action, $varHash);

        return true;
    }

    /**
     * Changes the IP of an account on the WHM server.
     *
     * @param Server_Account $account the account for which to change the IP
     * @param string         $newIp   the new IP
     *
     * @return bool returns true if the IP was successfully changed
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function changeAccountIp(Server_Account $account, string $newIp): bool
    {
        // Log the IP change
        $this->getLog()->info('Changing account ' . $account->getUsername() . ' ip');

        // Define the action and parameters for the API request
        $action = 'setsiteip';
        $varHash = [
            'domain' => $account->getDomain(),
            'ip' => $newIp,
        ];

        // Send the request to the WHM API
        $this->request($action, $varHash);

        return true;
    }

    /**
     * Retrieves the packages from the WHM server.
     *
     * @return array an array of packages, each represented as an associative array of package details
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function getPackages(): array
    {
        // Send a request to the WHM server to list the packages
        $pkgs = $this->request('listpkgs');
        $return = [];

        // Iterate over the packages and add their details to the return array
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
     * Sends a request to the WHM server and returns the response.
     * This method sends a request to the WHM server using the provided action and parameters.
     * It handles the creation of the HTTP client, the construction of the request URL and headers,
     * and the sending of the request. It also handles any errors that may occur during the request,
     * logging them and throwing a Server_Exception if necessary.
     *
     * @param string $action the action to be performed on the WHM server
     * @param array  $params the parameters to be sent with the request
     *
     * @return mixed the response from the WHM server, decoded from JSON into a PHP variable
     *
     * @throws Server_Exception if an error occurs during the request, or if the response from the WHM server indicates an error
     */
    private function request(string $action, array $params = []): mixed
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
        $accessHash = $this->_config['accesshash'];
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
     * Checks if a package exists on the WHM server.
     *
     * @param Server_Package $package the package to check
     * @param bool           $create  whether to create the package if it does not exist
     *
     * @throws Server_Exception if an error occurs during the request
     */
    private function checkPackageExists(Server_Package $package, bool $create = false): void
    {
        // Get the name of the package
        $name = $this->getPackageName($package);

        // Send a request to the WHM server to list the packages
        $json = $this->request('listpkgs');
        $packages = $json->package;

        // Check if the package exists
        $exists = false;
        foreach ($packages as $p) {
            if ($p->name == $name) {
                $exists = true;

                break;
            }
        }

        // If the package does not exist and the $create parameter is true, create the package
        if (!$exists && $create) {
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

            // Send a request to the WHM server to add the package
            $this->request('addpkg', $varHash);
        }
    }

    /**
     * Generates a package name based on the WHM server's username and the package's name.
     *
     * @param Server_Package $package the package for which to generate a name
     *
     * @return string the generated package name
     */
    private function getPackageName(Server_Package $package): string
    {
        return $this->_config['username'] . '_' . $package->getName();
    }

    /**
     * Modifies the package of an account on the WHM server.
     *
     * @param Server_Account $a the account for which to modify the package
     * @param Server_Package $p the new package
     *
     * @return true if the package was successfully modified
     *
     * @throws Server_Exception if an error occurs during the request
     */
    private function modifyAccountPackage(Server_Account $a, Server_Package $p): bool
    {
        // Log the modification
        $this->getLog()->info('Modifying account ' . $a->getUsername());

        // Prepare the parameters for the API request
        $varHash = [
            'user' => $a->getUsername(),
            'domain' => $a->getDomain(),
            'HASCGI' => $p->getHasCgi(),
            'CPTHEME' => $p->getTheme(),
            'LANG' => $p->getLanguage(),
            'MAXPOP' => $p->getMaxPop(),
            'MAXFTP' => $p->getMaxFtp(),
            'MAXLST' => $p->getMaxEmailLists(),
            'MAXSUB' => $p->getMaxSubdomains(),
            'MAXPARK' => $p->getMaxParkedDomains(),
            'MAXADDON' => $p->getMaxAddons(),
            'MAXSQL' => $p->getMaxSql(),
            'shell' => $p->getHasShell(),
        ];

        // Send a request to the WHM server to modify the account's package
        $this->request('modifyacct', $varHash);

        return true;
    }
}
