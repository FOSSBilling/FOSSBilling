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
     * @return array The form configuration.
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
     * Returns the login URL for a reseller account on the DirectAdmin server.
     * This method simply calls the getLoginUrl method, as the URL is the same for reseller accounts.
     *
     * @param Server_Account|null $account The server account. This parameter is not used in this method.
     * @return string The login URL.
     */
    public function getResellerLoginUrl(?Server_Account $account = null): string
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
     * @return string The login URL.
     */
    public function getLoginUrl(?Server_Account $account = null): string
    {
        $protocol = $this->_config['secure'] ? 'https://' : 'http://';

        return $protocol . $this->_config['host'] . ':' . $this->_getPort();
    }

    /**
     * Returns the port number for the DirectAdmin server.
     * If the port is set in the configuration, verify that it's a valid port number (0 - 65535).
     * If a valid port is not set in the configuration, it defaults to '2222'.
     *
     * @return int|string The port number.
     */
    public function _getPort(): int|string
    {
        $port = $this->_config['port'];

        if (filter_var($port, FILTER_VALIDATE_INT) !== false && $port >= 0 && $port <= 65535) {
            return $this->_config['port'];
        } else {
            return 2222;
        }
    }

    /**
     * Generates a username for a new account on the DirectAdmin server.
     * The username is based on the domain name, but is sanitized to be alphanumeric and start with a letter.
     * The username is also limited to 10 characters to avoid collisions.
     *
     * @param string $domain The domain name.
     * @return string The generated username.
     * @throws RandomException
     */
    public function generateUsername($domain): string
    {
        // Username must be alphanumeric.
        $username = preg_replace('/[^A-Za-z0-9]/', '', $domain);

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
     * @return bool True if the connection is successful, false otherwise.
     * @throws Server_Exception
     */
    public function testConnection(): bool
    {
        // Makes login test connection. As we don't currently force JSON, DirectAdmin will return HTML on a failed attempt, which causes the request to throw an error.
        $this->_request('API_LOGIN_TEST');
        return true;
    }

    /**
     * @param string $command
     * @param array $fields
     * @param bool $post
     * @return array
     * @throws Server_Exception
     */
    private function _request(string $command, array $fields = array(), bool $post = true): array
    {
        $host = $this->_config['host'];
        $protocol = $this->_config['secure'] ? 'https://' : 'http://';

        $field_string = http_build_query($fields);

        $httpClient = $this->getHttpClient()->withOptions([
            'auth_basic' => [$this->_config['username'], $this->_config['password']],
            'timeout' => 60,
            'verify_host' => false,
            'verify_peer' => false,
        ]);

        $url = $protocol . $host . ':' . $this->_getPort() . '/CMD_' . $command . '?' . $field_string;
        $this->getLog()->debug($url);

        try {
            // If it's a POST request, include the fields in the request body
            if ($post) {
                $request = $httpClient->request('POST', $url, [
                    'body' => $fields,
                ]);
            } else {
                $request = $httpClient->request('GET', $url);
            }

            $data = $request->getContent();
        } catch (TransportExceptionInterface|
        HttpExceptionInterface $error) {
            $exception = new Server_Exception('HttpClientException: :error', [':error' => $error->getMessage()]);
            $this->getLog()->err($exception);
            throw $exception;
        }

        // Check if the response data contains HTML, as some endpoints return HTML if the request fails (such as auth requests)
        if (strlen(strstr($data, '<!doctype html>')) > 0 || strlen(strstr($data, 'DirectAdmin Login')) > 0) {
            throw new Server_Exception('Failed to connect to the :type: server. Please verify your credentials and configuration', [':type:' => 'DirectAdmin']);
        }

        if (strlen(strstr($data, "The request you've made cannot be executed because it does not exist in your authority level")) > 0) {
            throw new Server_Exception('Server Manager DirectAdmin Error: "The request you have made cannot be executed because it does not exist in your authority level"');
        }

        $response = $this->_parseResponse($data);

        if (isset($response['error']) && $response['error'] == 1) {
            $placeholders = [':action:' => $command, ':type:' => 'DirectAdmin'];
            $this->getLog()->err('Failed to ' . $command . ' on the DirectAdmin server: ' . $response['text'] . ': ' . $response['details']);
            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        return empty($response) ? array() : $response;
    }

    /**
     * This method is used to parse the response data from the DirectAdmin server.
     * It first logs the raw response data for debugging purposes.
     * Then it replaces certain HTML entities in the data with their corresponding characters.
     * After that, it parses the data into an array using PHP's parse_str function.
     * Finally, it logs the parsed response data and returns it.
     *
     * @param string $data The raw response data from the DirectAdmin server.
     * @return array The parsed response data.
     */
    private function _parseResponse(string $data): array
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
     * Synchronizes the server account with the DirectAdmin server.
     * This method currently does nothing and simply returns the account as is.
     *
     * @param Server_Account $account The server account.
     * @return Server_Account The same server account.
     * @throws Server_Exception
     */
    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'DirectAdmin', ':action:' => __trans('synchronizing the account')]);
    }

    /**
     * Creates a new account on the DirectAdmin server.
     * This method sends a request to the server with the account details.
     * If the account is a reseller account, additional fields are included in the request.
     *
     * @param Server_Account $account The server account.
     * @return bool True if the account is created successfully, false otherwise.
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
                'aftp' => $package->getCustomValue('aftp') ? 'ON' : 'OFF', //ON or OFF. If ON, the User will be able to have anonymous ftp accounts.
                'bandwidth' => $package->getBandwidth(), // Bandwidth quota in MB
                'catchall' => $package->getCustomValue('catchall') ? 'ON' : 'OFF', //ON or OFF. If ON, the User will have the ability to enable and customize a catch-all email (*@domain.com).
                'cgi' => $package->getCustomValue('cgi') ? 'ON' : 'OFF', //ON or OFF. If ON, the User will have the ability to run cgi scripts in their cgi-bin.
                'cron' => $package->getCustomValue('cron') ? 'ON' : 'OFF', //ON or OFF. If ON, the User will have the ability to creat cronjobs.
                'dnscontrol' => $package->getCustomValue('dnscontrol') ? 'ON' : 'OFF', //ON or OFF. If ON, the User will be able to modify his/her dns records.
                'domainptr' => $package->getMaxParkedDomains(), // Domain pointer quota
                'ftp' => $package->getMaxFtp(), // FTP account quota
                'login_keys' => $package->getCustomValue('login_keys') ? 'ON' : 'OFF', //ON or OFF. If ON, the User will have access to the Login Key system for extra account passwords.
                'mysql' => $package->getMaxSql(), // Database quota
                'nemailf' => $package->getMaxEmailForwarders(), // Email forwarder quota
                'nemailml' => $package->getMaxEmailLists(), // Mailing list quota
                'nemailr' => $package->getMaxEmailAutoresponders(), // Autoresponder quota
                'nemails' => $package->getMaxPop(), // Email account quota
                'nsubdomains' => $package->getMaxSubdomains(), // Subdomain quota
                'php' => $package->getCustomValue('php') ? 'ON' : 'OFF', //ON or OFF. If ON, the User will have the ability to run php scripts.
                'quota' => $package->getQuota(), // Disk space quota in MB
                'spam' => $package->getCustomValue('spam') ? 'ON' : 'OFF', //ON or OFF. If ON, the User will have the ability to run scan email with SpamAssassin.
                'ssh' => $package->getCustomValue('ssh') ? 'ON' : 'OFF', //ON or OFF. If ON, the User will have an ssh account.
                'ssl' => $package->getCustomValue('ssl') ? 'ON' : 'OFF', //ON or OFF. If ON, the User will have the ability to access their websites through secure https://.
                'suspend_at_limit' => $package->getCustomValue('suspend_at_limit') ? 'ON' : 'OFF', //ON or OFF. If ON, the User will be suspended if their User bandwidth limit is exceeded.
                'sysinfo' => $package->getCustomValue('sysinfo') ? 'ON' : 'OFF', //ON or OFF. If ON, the User will have access to a page that shows the system information.
                'vdomains' => $package->getMaxDomains(), // Domain quota
            ]);

            if ($package->getBandwidth() == 'unlimited') {
                $fields['ubandwidth'] = 'ON'; //ON or OFF. If ON, bandwidth is ignored and no limit is set
            }

            if ($package->getQuota() == 'unlimited') {
                $fields['uquota'] = 'ON'; //ON or OFF. If ON, quota is ignored and no limit is set
            }

            if ($package->getMaxDomains() == 'unlimited') {
                $fields['uvdomains'] = 'ON'; //ON or OFF. If ON, vdomains is ignored and no limit is set
            }

            if ($package->getMaxSubdomains() == 'unlimited') {
                $fields['unsubdomains'] = 'ON'; //ON or OFF. If ON, nsubdomains is ignored and no limit is set
            }

            if ($package->getMaxParkedDomains() == 'unlimited') {
                $fields['udomainptr'] = 'ON'; //ON or OFF Unlimited option for domainptr
            }

            if ($package->getMaxPop() == 'unlimited') {
                $fields['unemails'] = 'ON'; //ON or OFF Unlimited option for nemails
            }

            if ($package->getMaxSql() == 'unlimited') {
                $fields['umysql'] = 'ON'; //ON or OFF Unlimited option for mysql
            }

            if ($package->getMaxFtp() == 'unlimited') {
                $fields['uftp'] = 'ON'; //ON or OFF Unlimited option for ftp
            }

            if ($fields['nemailf'] == 'unlimited') {
                $fields['unemailf'] = 'ON'; //ON or OFF Unlimited option for nemailf
            }

            if ($fields['nemailml'] == 'unlimited') {
                $fields['unemailml'] = 'ON'; //ON or OFF Unlimited option for nemailml
            }

            if ($fields['nemailr'] == 'unlimited') {
                $fields['unemailr'] = 'ON'; //ON or OFF Unlimited option for nemailr
            }
        }

        $command = 'API_ACCOUNT_USER';

        if ($account->getReseller()) {
            $command = 'ACCOUNT_RESELLER';

            $fields['ips'] = 1; // Number of ips that will be allocated to the Reseller upon account during account
            $fields['ip'] = 'assign';
        }

        try {
            $results = $this->_request($command, $fields);
        } catch (Exception $e) {
            if (strtolower($e->getMessage()) == strtolower(sprintf('Server Manager DirectAdmin Error: "%s" ', 'That domain already exists'))) {
                return true;
            } else {
                throw new Server_Exception($e->getMessage());
            }
        }

        if (str_contains(implode('', $results), 'Unable to assign the Reseller ANY ips')) {
            throw new Server_Exception('Unable to assign the Reseller ANY ips. Make sure to have free, un-assigned ips.');
        }

        if (str_contains(implode('', $results), 'Error Creating User')) {
            throw new Server_Exception('Error creating user');
        }

        return true;
    }

    /**
     * @throws Server_Exception
     */
    private function getIps()
    {
        $results = $this->_request('API_SHOW_RESELLER_IPS');
        return $results['list'] ?? array();
    }

    /**
     * @param Server_Account $account
     * @return true
     * @throws Server_Exception
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

        $result = $this->_request('API_SELECT_USERS', $fields);

        return true;
    }

    /**
     * @throws Server_Exception
     */
    private function getAccountInfo(Server_Account $account): array
    {
        $fields = [
            'action' => 'create',
            'add' => 'Submit',
            'user' => $account->getUsername(),
        ];

        return $this->_request('API_SHOW_USER_CONFIG', $fields);
    }

    /**
     * @param Server_Account $account
     * @return true
     * @throws Server_Exception
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

        $this->_request('API_SELECT_USERS', $fields);

        return true;
    }

    /**
     * @param Server_Account $account
     * @return true
     * @throws Server_Exception
     */
    public function cancelAccount(Server_Account $account): bool
    {
        $fields = [
            'confirmed' => 'Confirm',
            'delete' => 'yes',
            'select0' => $account->getUsername(),
        ];

        $this->_request('API_SELECT_USERS', $fields);

        return true;
    }

    /**
     * @param Server_Account $account
     * @return true
     * @throws Server_Exception
     */
    public function modifyAccount(Server_Account $account): bool
    {
        $package = $account->getPackage();

        $fields = [
            'aftp' => $package->getHasAnonymousFtp() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will be able to have anonymous ftp accounts.
            'action' => 'customize',
            'bandwidth' => $package->getBandwidth(), // Amount of bandwidth User will be allowed to use. Number, in Megabytes
            'catchall' => $package->getHasCatchAll() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to enable and customize a catch-all email (*@domain.com).
            'cgi' => $package->getHasCgi() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to run cgi scripts in their cgi-bin.
            'cron' => $package->getHasCron() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to creat cronjobs.
            'dnscontrol' => 'ON', // ON or OFF. If ON, the User will be able to modify his/her dns records.
            'domainptr' => $package->getMaxParkedDomains(), // Number of domain pointers the User will be allowed to create
            'ftp' => $package->getMaxFtp(), // Number of ftp accounts the User will be allowed to create
            'mysql' => $package->getMaxSql(), // Number of MySQL databases the User will be allowed to create
            'nemailf' => $package->getMaxEmailForwarders(), // Number of forwarders the User will be allowed to create
            'nemailml' => $package->getMaxEmailLists(), // Number of mailing lists the User will be allowed to create
            'nemailr' => $package->getMaxEmailAutoresponders(), // Number of autoresponders the User will be allowed to create
            'nemails' => $package->getMaxPop(), // Number of pop accounts the User will be allowed to create
            'nsubdomains' => $package->getMaxSubdomains(), // Number of subdomains the User will be allowed to create
            'ns1' => $account->getNs1(),
            'ns2' => $account->getNs2(),
            'php' => $package->getHasPhp() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to run php scripts.
            'quota' => $package->getQuota(), // Amount of disk space User will be allowed to use. Number, in Megabytes
            'spam' => $package->getHasSpamFilter() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to run scan email with SpamAssassin.
            'ssh' => $package->getHasShell() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have an ssh account.
            'ssl' => $package->getHasSll() ? 'ON' : 'OFF', // ON or OFF. If ON, the User will have the ability to access their websites through secure https://.
            'sysinfo' => 'ON', // ON or OFF. If ON, the User will have access to a page that shows the system information.
            'user' => $account->getUsername(),
            'vdomains' => $package->getMaxDomains(), // Number of domains the User will be allowed to create
        ];

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

        $this->_request('API_MODIFY_USER', $fields);

        return true;
    }

    /**
     * @param Server_Account $account
     * @param string $newDomain
     * @return never
     * @throws Server_Exception
     */
    public function changeAccountDomain(Server_Account $account, string $newDomain): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'DirectAdmin', ':action:' => __trans('changing the account domain')]);
    }

    /**
     * @param Server_Account $account
     * @param string $newIp
     * @return never
     * @throws Server_Exception
     */
    public function changeAccountIp(Server_Account $account, string $newIp): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'DirectAdmin', ':action:' => __trans('changing the account IP')]);
    }

    /**
     * @param Server_Account $account
     * @param Server_Package $package
     * @return bool
     * @throws Server_Exception
     */
    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        $fields = [
            'action' => 'package',
            'user' => $account->getUsername(),
            'package' => $account->getPackage()->getName(),
        ];

        $this->_request('API_MODIFY_USER', $fields);

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
        $fields = [
            'username' => $account->getUsername(),
            'passwd' => $newPassword,
            'passwd2' => $newPassword,
        ];

        $this->_request('API_USER_PASSWD', $fields);

        return true;
    }

    /**
     * @param Server_Account $account
     * @param string $newUsername
     * @return never
     * @throws Server_Exception
     */
    public function changeAccountUsername(Server_Account $account, string $newUsername): never
    {
        throw new Server_Exception(':type: does not support :action:', [':type:' => 'DirectAdmin', ':action:' => __trans('username changes')]);
    }

    /**
     * @throws Server_Exception
     */
    private function getUserInfo(Server_Account $account)
    {
        $fields = array();
        $fields['user'] = $account->getUsername();
        $result = $this->_request('API_SHOW_USER_CONFIG', $fields);
    }

    /**
     * This method is used to verify the authentication credentials for the DirectAdmin server.
     * It sends a request to the server with the 'API_VERIFY_PASSWORD' command and the username and password from the configuration.
     * The response from the server is stored in the $response variable, but is not used in this method.
     * The method always returns true, regardless of the server's response.
     *
     * @return bool Always returns true.
     * @throws Server_Exception If there is an error while sending the request to the server.
     */
    private function checkAuth()
    {
        $response = $this->_request('API_VERIFY_PASSWORD', array(
            'user' => $this->_config['username'],
            'passwd' => $this->_config['password'],
        ));

        return true;
    }
}
