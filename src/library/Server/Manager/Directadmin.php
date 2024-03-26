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
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Class Server_Manager_Directadmin
 * This class is responsible for managing the DirectAdmin server.
 * It extends the Server_Manager class.
 */
class Server_Manager_Directadmin extends Server_Manager
{
    /**
     * Returns the form configuration for the DirectAdmin server manager.
     * The form includes fields for username and password.
     *
     * @return array the form configuration
     */
    public static function getForm(): array
    {
        return [
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
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Initialize the server manager.
     * Checks if the host, username, and password are set in the configuration.
     * Throws an exception if any of these are not set.
     *
     * @throws Server_Exception
     */
    public function init(): void
    {
        // Check if host is set in the configuration
        if (empty($this->_config['host'])) {
            throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'DirectAdmin', ':missing' => 'hostname'], 2001);
        }

        // Check if username is set in the configuration
        if (empty($this->_config['username'])) {
            throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'DirectAdmin', ':missing' => 'username'], 2001);
        }

        // Check if password is set in the configuration
        if (empty($this->_config['password'])) {
            throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'DirectAdmin', ':missing' => 'password'], 2001);
        }
    }

    /**
     * Cancels an account on the DirectAdmin server.
     * This method sends a request to the server to delete the account.
     *
     * @param Server_Account $account the account to be cancelled
     *
     * @return bool returns true if the account is successfully cancelled
     *
     * @throws Server_Exception if there is an error while sending the request to the server
     */
    public function cancelAccount(Server_Account $account): bool
    {
        $fields = [
            'confirmed' => 'Confirm',
            'delete' => 'yes',
            'select0' => $account->getUsername(),
        ];

        $this->request('API_SELECT_USERS', $fields);

        return true;
    }

    /**
     * Returns the port number for the DirectAdmin server.
     * If the port is set in the configuration, verify that it's a valid port number (0 - 65535).
     * If a valid port is not set in the configuration, it defaults to '2222'.
     *
     * @return int|string the port number
     */
    public function getPort(): int|string
    {
        $port = $this->_config['port'];

        if (filter_var($port, FILTER_VALIDATE_INT) !== false && $port >= 0 && $port <= 65535) {
            return $this->_config['port'];
        } else {
            return 2222;
        }
    }

    /**
     * Changes the domain of an account on the DirectAdmin server.
     * This method is not supported and will always throw an exception.
     *
     * @param Server_Account $account   the account for which the domain is to be changed
     * @param string         $newDomain the new domain
     *
     * @throws Server_Exception always throws an exception indicating that the method is not supported
     */
    public function changeAccountDomain(Server_Account $account, string $newDomain): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'DirectAdmin', ':action:' => __trans('changing the account domain')]);
    }

    /**
     * Changes the IP of an account on the DirectAdmin server.
     * This method is not supported and will always throw an exception.
     *
     * @param Server_Account $account the account for which the IP is to be changed
     * @param string         $newIp   the new IP
     *
     * @throws Server_Exception always throws an exception indicating that the method is not supported
     */
    public function changeAccountIp(Server_Account $account, string $newIp): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'DirectAdmin', ':action:' => __trans('changing the account IP')]);
    }

    /**
     * Changes the package of an account on the DirectAdmin server.
     * This method sends a request to the server to change the package of the account.
     *
     * @param Server_Account $account the account for which the package is to be changed
     * @param Server_Package $package the new package
     *
     * @return bool returns true if the package is successfully changed
     *
     * @throws Server_Exception if there is an error while sending the request to the server
     */
    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        $fields = [
            'action' => 'package',
            'user' => $account->getUsername(),
            'package' => $account->getPackage()->getName(),
        ];

        $this->request('API_MODIFY_USER', $fields);

        return true;
    }

    /**
     * Changes the password of an account on the DirectAdmin server.
     * This method sends a request to the server to change the password of the account.
     *
     * @param Server_Account $account     the account for which the password is to be changed
     * @param string         $newPassword the new password
     *
     * @return bool returns true if the password is successfully changed
     *
     * @throws Server_Exception if there is an error while sending the request to the server
     */
    public function changeAccountPassword(Server_Account $account, string $newPassword): bool
    {
        $fields = [
            'username' => $account->getUsername(),
            'passwd' => $newPassword,
            'passwd2' => $newPassword,
        ];

        $this->request('API_USER_PASSWD', $fields);

        return true;
    }

    /**
     * Changes the username of an account on the DirectAdmin server.
     * This method is not supported and will always throw an exception.
     *
     * @param Server_Account $account     the account for which the username is to be changed
     * @param string         $newUsername the new username
     *
     * @throws Server_Exception always throws an exception indicating that the method is not supported
     */
    public function changeAccountUsername(Server_Account $account, string $newUsername): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'DirectAdmin', ':action:' => __trans('username changes')]);
    }

    /**
     * Creates a new account on the DirectAdmin server.
     * This method sends a request to the server with the account details.
     * If the account is a reseller account, additional fields are included in the request.
     *
     * @param Server_Account $account the server account
     *
     * @return bool true if the account is created successfully, false otherwise
     *
     * @throws Server_Exception
     */
    public function createAccount(Server_Account $account): bool
    {
        $ips = $this->getIps();
        if (empty($ips)) {
            throw new Server_Exception('There are no available IPs on this server.');
        }

        $ip = $ips[array_rand($ips)];
        $package = $account->getPackage();
        $client = $account->getClient();

        $fields = [
            'action' => 'create',
            'add' => 'Submit',
            'username' => $account->getUsername(),
            'email' => $client->getEmail(),
            'passwd' => $account->getPassword(),
            'passwd2' => $account->getPassword(),
            'domain' => $account->getDomain(),
            'ip' => $ip,
            'notify' => 'no',
        ];

        // If the `package` custom value is set, use that package from the DirectAdmin server instead of implicitly creating a new one
        if (!empty($package->getCustomValue('package'))) {
            $this->getLog()->info('Using DirectAdmin package name: ' . $package->getCustomValue('package') . ', ignoring package settings');
            $fields['package'] = $package->getCustomValue('package');
        } else {
            // Specify the package quotas
            $fields = array_merge($fields, [
                'aftp' => $package->getCustomValue('aftp') ? 'ON' : 'OFF', // ON or OFF. If ON, the User will be able to have anonymous ftp accounts.
                'bandwidth' => $package->getBandwidth(), // Bandwidth quota in MB
                'catchall' => $package->getCustomValue('catchall') ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to enable and customize a catch-all email (*@domain.com).
                'cgi' => $package->getCustomValue('cgi') ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to run cgi scripts in their cgi-bin.
                'cron' => $package->getCustomValue('cron') ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to creat cronjobs.
                'dnscontrol' => $package->getCustomValue('dnscontrol') ? 'ON' : 'OFF', // ON or OFF. If ON, the User will be able to modify his/her dns records.
                'domainptr' => $package->getMaxParkedDomains(), // Domain pointer quota
                'ftp' => $package->getMaxFtp(), // FTP account quota
                'login_keys' => $package->getCustomValue('login_keys') ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have access to the Login Key system for extra account passwords.
                'mysql' => $package->getMaxSql(), // Database quota
                'nemailf' => $package->getMaxEmailForwarders(), // Email forwarder quota
                'nemailml' => $package->getMaxEmailLists(), // Mailing list quota
                'nemailr' => $package->getMaxEmailAutoresponders(), // Autoresponder quota
                'nemails' => $package->getMaxPop(), // Email account quota
                'nsubdomains' => $package->getMaxSubdomains(), // Subdomain quota
                'php' => $package->getCustomValue('php') ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to run php scripts.
                'quota' => $package->getQuota(), // Disk space quota in MB
                'spam' => $package->getCustomValue('spam') ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to run scan email with SpamAssassin.
                'ssh' => $package->getCustomValue('ssh') ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have an ssh account.
                'ssl' => $package->getCustomValue('ssl') ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to access their websites through secure https://.
                'suspend_at_limit' => $package->getCustomValue('suspend_at_limit') ? 'ON' : 'OFF', // ON or OFF. If ON, the User will be suspended if their User bandwidth limit is exceeded.
                'sysinfo' => $package->getCustomValue('sysinfo') ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have access to a page that shows the system information.
                'vdomains' => $package->getMaxDomains(), // Domain quota
            ]);

            if ($package->getBandwidth() == 'unlimited') {
                $fields['ubandwidth'] = 'ON'; // ON or OFF. If ON, bandwidth is ignored and no limit is set
            }

            if ($package->getQuota() == 'unlimited') {
                $fields['uquota'] = 'ON'; // ON or OFF. If ON, quota is ignored and no limit is set
            }

            if ($package->getMaxDomains() == 'unlimited') {
                $fields['uvdomains'] = 'ON'; // ON or OFF. If ON, vdomains is ignored and no limit is set
            }

            if ($package->getMaxSubdomains() == 'unlimited') {
                $fields['unsubdomains'] = 'ON'; // ON or OFF. If ON, nsubdomains is ignored and no limit is set
            }

            if ($package->getMaxParkedDomains() == 'unlimited') {
                $fields['udomainptr'] = 'ON'; // ON or OFF Unlimited option for domainptr
            }

            if ($package->getMaxPop() == 'unlimited') {
                $fields['unemails'] = 'ON'; // ON or OFF Unlimited option for nemails
            }

            if ($package->getMaxSql() == 'unlimited') {
                $fields['umysql'] = 'ON'; // ON or OFF Unlimited option for mysql
            }

            if ($package->getMaxFtp() == 'unlimited') {
                $fields['uftp'] = 'ON'; // ON or OFF Unlimited option for ftp
            }

            if ($fields['nemailf'] == 'unlimited') {
                $fields['unemailf'] = 'ON'; // ON or OFF Unlimited option for nemailf
            }

            if ($fields['nemailml'] == 'unlimited') {
                $fields['unemailml'] = 'ON'; // ON or OFF Unlimited option for nemailml
            }

            if ($fields['nemailr'] == 'unlimited') {
                $fields['unemailr'] = 'ON'; // ON or OFF Unlimited option for nemailr
            }
        }

        $command = 'API_ACCOUNT_USER';

        if ($account->getReseller()) {
            $command = 'ACCOUNT_RESELLER';

            $fields['ips'] = 1; // Number of ips that will be allocated to the Reseller upon account during account
            $fields['ip'] = 'assign';
        }

        try {
            $results = $this->request($command, $fields);
        } catch (Exception $e) {
            if (strtolower($e->getMessage()) == strtolower(sprintf('Server Manager DirectAdmin Error: "%s" ', 'That domain already exists'))) {
                return true;
            } else {
                throw new Server_Exception($e->getMessage());
            }
        }

        if (str_contains(implode('', $results), 'Unable to assign the Reseller ANY ips')) {
            throw new Server_Exception('Unable to assign the Reseller ANY IPs. Make sure to have free, unassigned IPs.');
        }

        if (str_contains(implode('', $results), 'Error Creating User')) {
            throw new Server_Exception('Error creating user');
        }

        return true;
    }

    /**
     * Returns the login URL for a reseller account on the DirectAdmin server.
     * This method simply calls the getLoginUrl method, as the URL is the same for reseller accounts.
     *
     * @param Server_Account|null $account The server account. This parameter is not used in this method.
     *
     * @return string the login URL
     *
     * @throws Server_Exception
     */
    public function getResellerLoginUrl(Server_Account $account = null): string
    {
        return $this->getLoginUrl();
    }

    /**
     * Returns the login URL for the DirectAdmin server.
     * The URL includes the host and port from the configuration.
     * If the 'secure' configuration option is set, the URL uses the 'https' protocol.
     * Otherwise, it uses the 'http' protocol.
     *
     * @param Server_Account|null $account The server account. This parameter is not used in this method.
     *
     * @return string the login URL
     *
     * @throws Server_Exception
     */
    public function getLoginUrl(Server_Account $account = null): string
    {
        $protocol = $this->_config['secure'] ? 'https://' : 'http://';

        if (!$account) {
            return $protocol . $this->_config['host'] . ':' . $this->getPort();
        }

        $fields = [
            'action' => 'create',
            'expiry' => '30m', // when the URL expires, so does the user session
            'login_keys_notify_on_creation' => 0,
            'redirect-url' => $protocol . $this->_config['host'] . ':' . $this->getPort(),
            'type' => 'one_time_url',
        ];

        $result = $this->request('API_LOGIN_KEYS', $fields, true, $account->getUsername());

        return $result['details'];
    }

    /**
     * Generates a username for a new account on the DirectAdmin server.
     * The username is based on the domain name, but is sanitized to be alphanumeric and start with a letter.
     * The username is also limited to 10 characters to avoid collisions.
     *
     * @param string $domain the domain name
     *
     * @return string the generated username
     *
     * @throws RandomException
     */
    public function generateUsername(string $domain): string
    {
        $prefix = $this->_config['config']['userprefix'] ?? '';

        // Username must be alphanumeric.
        $username = preg_replace('/[^A-Za-z0-9]/', '', $prefix . $domain);

        // Username must start with a-z.
        $username = is_numeric(substr($username, 0, 1)) ? substr_replace($username, chr(random_int(97, 122)), 0, 1) : $username;

        // Username must be at most 10 characters long, and sufficiently random to avoid collisions.
        $username = substr($username, 0, 7);

        $random_number = random_int(0, 99);

        return $username . $random_number;
    }

    /**
     * Tests the connection to the DirectAdmin server.
     * This method sends a request to the server and checks if the response is an array.
     *
     * @return bool true if the connection is successful, false otherwise
     *
     * @throws Server_Exception
     */
    public function testConnection(): bool
    {
        // Makes login test connection. As we don't currently force JSON, DirectAdmin will return HTML on a failed attempt, which causes the request to throw an error.
        $this->request('API_LOGIN_TEST');

        return true;
    }

    /**
     * Synchronizes the server account with the DirectAdmin server.
     * This method currently does nothing and simply returns the account as is.
     *
     * @param Server_Account $account the server account
     *
     * @return Server_Account the same server account
     *
     * @throws Server_Exception
     */
    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'DirectAdmin', ':action:' => __trans('synchronizing the account')]);
    }

    /**
     * Suspends an account on the DirectAdmin server.
     * This method sends a request to the server to suspend the account.
     *
     * @param Server_Account $account the account to be suspended
     *
     * @return bool returns true if the account is already suspended or is successfully suspended
     *
     * @throws Server_Exception if there is an error while sending the request to the server
     */
    public function suspendAccount(Server_Account $account): bool
    {
        $info = $this->getAccountInfo($account);
        if ($info['suspended'] == 'yes') {
            return true;
        }

        $fields = [
            'location' => 'USER_SHOW',
            'suspend' => 'Suspend',
            'select0' => $account->getUsername(),
        ];

        $this->request('API_SELECT_USERS', $fields);

        return true;
    }

    /**
     * Unsuspends an account on the DirectAdmin server.
     * This method sends a request to the server to unsuspend the account.
     *
     * @param Server_Account $account the account to be unsuspended
     *
     * @return bool returns true if the account is successfully unsuspended
     *
     * @throws Server_Exception if the account is not suspended or there is an error while sending the request to the server
     */
    public function unsuspendAccount(Server_Account $account): bool
    {
        $info = $this->getAccountInfo($account);
        if ($info['suspended'] == 'no') {
            throw new Server_Exception('Server Manager DirectAdmin Error: "Account is not suspended"');
        }

        $fields = [
            'location' => 'USER_SHOW',
            'suspend' => 'Unsuspend',
            'select0' => $account->getUsername(),
        ];

        $this->request('API_SELECT_USERS', $fields);

        return true;
    }

    /**
     * Modifies an existing account on the DirectAdmin server.
     * This method sends a request to the server with the updated account details.
     * If certain account parameters are set to 'unlimited', the corresponding fields are set to 'ON'.
     *
     * @param Server_Account $account the server account to be modified
     *
     * @return bool returns true if the account is modified successfully
     *
     * @throws Server_Exception if there is an error while sending the request to the server
     */
    public function modifyAccount(Server_Account $account): bool
    {
        // Get the package associated with the account
        $package = $account->getPackage();

        // Prepare the fields for the request
        $fields = [
            'aftp' => $package->getHasAnonymousFtp() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will be able to have anonymous ftp accounts.
            'action' => 'customize',
            'bandwidth' => $package->getBandwidth(), // Bandwidth quota in MB
            'catchall' => $package->getHasCatchAll() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to enable and customize a catch-all email (*@domain.com).
            'cgi' => $package->getHasCgi() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to run cgi scripts in their cgi-bin.
            'cron' => $package->getHasCron() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to creat cronjobs.
            'dnscontrol' => 'ON', // ON or OFF. If ON, the User will be able to modify his/her dns records.
            'domainptr' => $package->getMaxParkedDomains(), // Domain pointer quota
            'ftp' => $package->getMaxFtp(), // FTP account quota
            'mysql' => $package->getMaxSql(), // MySQL database quota
            'nemailf' => $package->getMaxEmailForwarders(), // Email forwarder quota
            'nemailml' => $package->getMaxEmailLists(), // Mailing list quota
            'nemailr' => $package->getMaxEmailAutoresponders(), // Autoresponder quota
            'nemails' => $package->getMaxPop(), // Email account quota
            'nsubdomains' => $package->getMaxSubdomains(), // Subdomain quota
            'ns1' => $account->getNs1(),
            'ns2' => $account->getNs2(),
            'php' => $package->getHasPhp() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to run php scripts.
            'quota' => $package->getQuota(), // Disk space quota in MB
            'spam' => $package->getHasSpamFilter() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to run scan email with SpamAssassin.
            'ssh' => $package->getHasShell() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have an ssh account.
            'ssl' => $package->getHasSll() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to access their websites through secure https://.
            'sysinfo' => 'ON', // ON or OFF. If ON, the User will have access to a page that shows the system information.
            'user' => $account->getUsername(),
            'vdomains' => $package->getMaxDomains(), // Domain quota
        ];

        // Check if certain parameters are set to 'unlimited' and set the corresponding fields to 'ON'
        if ($package->getBandwidth() == 'unlimited') {
            $fields['ubandwidth'] = 'ON'; // ON or OFF. If ON, bandwidth is ignored and no limit is set
        }

        if ($package->getQuota() == 'unlimited') {
            $fields['uquota'] = 'ON'; // ON or OFF. If ON, quota is ignored and no limit is set
        }

        if ($package->getMaxDomains() == 'unlimited') {
            $fields['uvdomains'] = 'ON'; // ON or OFF. If ON, vdomains is ignored and no limit is set
        }

        if ($package->getMaxSubdomains() == 'unlimited') {
            $fields['unsubdomains'] = 'ON'; // ON or OFF. If ON, nsubdomains is ignored and no limit is set
        }

        if ($package->getMaxPop() == 'unlimited') {
            $fields['unemails'] = 'ON'; // ON or OFF Unlimited option for nemails
        }

        if ($package->getMaxEmailForwarders() == 'unlimited') {
            $fields['unemailf'] = 'ON'; // ON or OFF Unlimited option for nemailf
        }

        if ($package->getMaxEmailLists() == 'unlimited') {
            $fields['unemailml'] = 'ON'; // ON or OFF Unlimited option for nemailml
        }

        if ($package->getMaxEmailAutoresponders() == 'unlimited') {
            $fields['unemailr'] = 'ON'; // ON or OFF Unlimited option for nemailr
        }

        if ($package->getMaxSql() == 'unlimited') {
            $fields['umysql'] = 'ON'; // ON or OFF Unlimited option for mysql
        }

        if ($package->getMaxParkedDomains() == 'unlimited') {
            $fields['udomainptr'] = 'ON'; // ON or OFF Unlimited option for domainptr
        }

        if ($package->getMaxFtp() == 'unlimited') {
            $fields['uftp'] = 'ON'; // ON or OFF Unlimited option for ftp
        }

        // Send the request to the server
        $this->request('API_MODIFY_USER', $fields);

        return true;
    }

    /**
     * Sends a request to the DirectAdmin server.
     *
     * @param string $command the command to be executed on the server
     * @param array  $fields  the fields to be included in the request
     * @param bool   $post    Whether the request should be a POST request. If false, a GET request is sent.
     *
     * @return array the response from the server, parsed into an array
     *
     * @throws Server_Exception if there is an error while sending the request or if the server returns an error
     */
    private function request(string $command, array $fields = [], bool $post = true, string $asUser = ''): array
    {
        // Get the host from the configuration
        $host = $this->_config['host'];

        // Determine the protocol based on the 'secure' configuration option
        $protocol = $this->_config['secure'] ? 'https://' : 'http://';

        // Build the field string for the request
        $field_string = http_build_query($fields);

        // Support login-as for non-admin functions
        $username = $this->_config['username'];
        if ($asUser) {
            $username .= '|' . $asUser;
        }

        // Get the HTTP client with the basic authentication and timeout options set
        $httpClient = $this->getHttpClient()->withOptions([
            'auth_basic' => [$username, $this->_config['password']],
            'timeout' => 60,
            'verify_host' => false,
            'verify_peer' => false,
        ]);

        // Construct the URL for the request
        $url = $protocol . $host . ':' . $this->getPort() . '/CMD_' . $command . '?' . $field_string;

        // Log the URL for debugging purposes
        $this->getLog()->debug($url);

        try {
            // If it's a POST request, include the fields in the request body
            if ($post) {
                $request = $httpClient->request('POST', $url, [
                    'body' => $fields,
                ]);
            } else {
                // Otherwise, send a GET request
                $request = $httpClient->request('GET', $url);
            }

            // Get the content of the response
            $data = $request->getContent();
        } catch (TransportExceptionInterface|HttpExceptionInterface $error) {
            // If there is an error while sending the request, throw an exception
            $exception = new Server_Exception('HttpClientException: :error', [':error' => $error->getMessage()]);
            $this->getLog()->err($exception);

            throw $exception;
        }

        // Check if the response data contains HTML, as some endpoints return HTML if the request fails (such as auth requests)
        if (strlen(strstr($data, '<!doctype html>')) > 0 || strlen(strstr($data, 'DirectAdmin Login')) > 0) {
            throw new Server_Exception('Failed to connect to the :type: server. Please verify your credentials and configuration', [':type:' => 'DirectAdmin']);
        }

        // Check if the response data contains an error message indicating that the request cannot be executed
        if (strlen(strstr($data, "The request you've made cannot be executed because it does not exist in your authority level")) > 0) {
            throw new Server_Exception('Server Manager DirectAdmin Error: "The request you have made cannot be executed because it does not exist in your authority level"');
        }

        // Parse the response data into an array
        $response = $this->parseResponse($data);

        // If the response contains an error, log the error and throw an exception
        if (isset($response['error']) && $response['error'] == 1) {
            $placeholders = [':action:' => $command, ':type:' => 'DirectAdmin'];
            $this->getLog()->err('Failed to ' . $command . ' on the DirectAdmin server: ' . $response['text'] . ': ' . $response['details']);

            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        // Return the parsed response data, or an empty array if the response is empty
        return empty($response) ? [] : $response;
    }

    /**
     * Parses the response data from the DirectAdmin server.
     * It first logs the raw response data for debugging purposes.
     * Then it replaces certain HTML entities in the data with their corresponding characters.
     * After that, it parses the data into an array using PHP's parse_str function.
     * Finally, it logs the parsed response data and returns it.
     *
     * @param string $data the raw response data from the DirectAdmin server
     *
     * @return array the parsed response data
     */
    private function parseResponse(string $data): array
    {
        // Log the raw response data for debugging purposes
        $this->getLog()->debug('Raw Response: ' . $data);

        // Replace certain HTML entities in the data with their corresponding characters
        $data = str_replace('&#39', '"', $data);
        $data = preg_replace('|(\&\#\d+)|', '$1;', $data);
        $data = html_entity_decode($data);

        // Parse the data into an array
        parse_str($data, $response);

        // Log the parsed response data for debugging purposes
        $this->getLog()->debug('Parsed Response: ' . print_r($response, 1));

        // Return the parsed response data
        return $response;
    }

    /**
     * Retrieves the list of IPs from the DirectAdmin server.
     *
     * @return array the list of IPs
     *
     * @throws Server_Exception if there is an error while sending the request to the server
     */
    private function getIps(): array
    {
        $results = $this->request('API_SHOW_RESELLER_IPS');

        return $results['list'] ?? [];
    }

    /**
     * Retrieves the information of an account from the DirectAdmin server.
     *
     * @param Server_Account $account the account for which the information is to be retrieved
     *
     * @return array the account information
     *
     * @throws Server_Exception if there is an error while sending the request to the server
     */
    private function getAccountInfo(Server_Account $account): array
    {
        $fields = [
            'action' => 'create',
            'add' => 'Submit',
            'user' => $account->getUsername(),
        ];

        return $this->request('API_SHOW_USER_CONFIG', $fields);
    }
}
