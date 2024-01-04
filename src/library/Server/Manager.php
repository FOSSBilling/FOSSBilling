<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

abstract class Server_Manager
{
    private ?\Box_Log $_log = null;

    protected $_config = array(
        'ip'         =>  NULL,
        'host'       =>  NULL,
        'secure'     =>  FALSE,
        'username'   =>  NULL,
        'password'   =>  NULL,
        'accesshash' =>  NULL,
        'config'     =>  NULL,
        'port'       =>  NULL,
        'passwordlength' => NULL,
    );

    /**
     * Constructor for the class.
     *
     * @param array $options Associative array of options for the class.
     *                       For example, some possible options include:
     *                       - 'ip': IP address of the server.
     *                       - 'host': Hostname of the server.
     *                       - 'secure': Boolean value indicating whether to use a secure connection.
     *                       - 'username': Username for authenticating the connection.
     *                       - 'password': Password for authenticating the connection.
     *                       - 'accesshash': Access hash for authenticating the connection. (API Key)
     *                       - 'config': Optional configuration for the server manager.
     *                       - 'port': Custom port number for the connection.
     *                       - 'passwordlength': Password length for accounts.
     */
    public function __construct($options)
    {
        if (isset($options['ip'])) {
            $this->_config['ip'] = $options['ip'];
        }

        if (isset($options['host'])) {
            $this->_config['host'] = $options['host'];
        }

        if (isset($options['secure'])) {
            $this->_config['secure'] = (bool)$options['secure'];
        }

        if (isset($options['username'])) {
            $this->_config['username'] = $options['username'];
        }

        if (isset($options['password'])) {
            $this->_config['password'] = $options['password'];
        }

        if (isset($options['accesshash'])) {
            $this->_config['accesshash'] = $options['accesshash'];
        }

        if (isset($options['passwordlength'])) {
            $this->_config['passwordlength'] = $options['passwordlength'];
        }

        if (isset($options['ssl'])) {
            $this->_config['ssl'] = $options['ssl'];
        }

        /**
         * Custom configuration.
         */
        if (isset($options['config'])) {
            $this->_config['config'] = $options['config'];
        }

        /**
         * Custom connection port to API.
         * If not provided, using default server manager port
         */
        if (isset($options['port'])) {
            $this->_config['port'] = $options['port'];
        }

        $this->init();
    }

    /**
     * Generates a username for an account based on the provided domain name.
     * Server managers may define this function to provide their own method for username generation depending on the specifics of the server they are integrated with.
     *
     * @param mixed $domain_name The domain name used to generate the username.
     * @return string The generated username.
     */
    public function generateUsername(mixed $domain_name)
    {
        $username = preg_replace('/[^A-Za-z0-9]/', '', $domain_name);
        $username = substr($username, 0, 7);
        $randnum = random_int(0, 9);
        $prefix = $this->_config['config']['userprefix'] ?? '';
        return  $prefix . $username . $randnum;
    }

    /**
     * This method is used to get the password length from the configuration.
     * If the password length is not set in the configuration, it defaults to 10.
     *
     * @return int The password length.
     */
    public function getPasswordLength(): int
    {
        return $this->_config['passwordlength'] ?? 10;
    }

    /**
     * Sets the logger object.
     *
     * @param Box_Log $value The logger object.
     * @return $this
     */
    public function setLog(Box_Log $value)
    {
        $this->_log = $value;
        return $this;
    }

    /**
     * Returns the logger object.
     *
     * @return Box_Log The logger object.
     */
    public function getLog()
    {
        if (!$this->_log instanceof Box_Log) {
            $log = new Box_Log();
            $log->addWriter(new Box_LogDb('Model_ActivitySystem'));
            return $log;
        }
        return $this->_log;
    }

    /**
     * Creates and returns an interface for the Symfony HTTP client.
     */
    public function getHttpClient(): Symfony\Contracts\HttpClient\HttpClientInterface
    {
        return \Symfony\Component\HttpClient\HttpClient::create();
    }

    /**
     * Initializes the object after construction.
     *
     * This function can be used to perform any necessary setup tasks that are required after the object has been constructed.
     *
     * @return void
     */
    protected function init()
    {
    }

    /**
     * Returns the login URL for the server. (ex: panel.example.com)
     *
     * @param null|Server_Account $account Either the related `Server_Account` which can be used to generate an SSO link or `null`.
     *
     * @return string
     */
    abstract public function getLoginUrl(?Server_Account $account);

    /**
     * Returns the login URL for the server for reseller accounts.
     *
     * @param null|Server_Account $account Either the related `Server_Account` which can be used to generate an SSO link or `null`.
     *
     * @return string
     */
    abstract public function getResellerLoginUrl(?Server_Account $account);

    /**
     * Used to test the connection to the server and verify the server configuration is correct.
     *
     * @return bool
     *
     * @throws Server_Exception
     */
    abstract public function testConnection();

    /**
     * Creates a new account on the server.
     *
     * @param Server_Account $account Account object containing the details of the account to create.
     *
     * @return bool True if the account was created successfully, if not the server manager should throw an exception
     *
     * @throws Server_Exception If there was an error while creating the account.
     */
    abstract public function createAccount(Server_Account $account);


    /**
     * Synchronizes the account status from the server.
     *
     * @param Server_Account $a Account object containing the details of the account to synchronize.
     *
     * @return Server_Account A new account object with the updated status.
     *
     * @throws Server_Exception If there was an error while synchronizing the account.
     */
    abstract public function synchronizeAccount(Server_Account $a);


    /**
     * Suspends an account on the server.
     *
     * @param Server_Account $a Account object containing the details of the account to suspend.
     *
     * @return bool True if the account was suspended successfully, if not the sever manager should throw an exception
     *
     * @throws Server_Exception If there was an error while suspending the account.
     */
    abstract public function suspendAccount(Server_Account $a);


    /**
     * Unsuspends an account on the server.
     *
     * @param Server_Account $a Account object containing the details of the account to unsuspend.
     *
     * @return bool True if the account was unsuspended successfully, if not the sever manager should throw an exception
     *
     * @throws Server_Exception If there was an error while unsuspending the account.
     */
    abstract public function unsuspendAccount(Server_Account $a);


    /**
     * Cancels an account on the server.
     *
     * @param Server_Account $a Account object containing the details of the account to cancel.
     *
     * @return bool True if the account was canceled successfully, if not the sever manager should throw an exception
     *
     * @throws Server_Exception If there was an error while canceling the account.
     */
    abstract public function cancelAccount(Server_Account $a);


    /**
     * Changes the password for an account on the server.
     *
     * @param Server_Account $a Account object containing the details of the account to update.
     *
     * @param string $new_password The new password for the account.
     *
     * @return bool True if the password was changed successfully, if not the sever manager should throw an exception
     *
     * @throws Server_Exception If there was an error while changing the password.
     */
    abstract public function changeAccountPassword(Server_Account $a, $new_password);


    /**
     * Changes the username for an account on the server.
     *
     * @param Server_Account $a Account object containing the details of the account to update.
     *
     * @param string $new_username The new username for the account.
     *
     * @return bool True if the username was changed successfully, if not the sever manager should throw an exception
     *
     * @throws Server_Exception If there was an error while changing the username.
     */
    abstract public function changeAccountUsername(Server_Account $a, $new_username);


    /**
     * Changes the domain for an account on the server.
     *
     * @param Server_Account $a Account object containing the details of the account to update.
     *
     * @param string $new_domain The new domain for the account.
     *
     * @return bool True if the domain was changed successfully, if not the sever manager should throw an exception
     *
     * @throws Server_Exception If there was an error while changing the domain.
     */
    abstract public function changeAccountDomain(Server_Account $a, $new_domain);


    /**
     * Changes the IP address for an account on the server.
     *
     * @param Server_Account $a Account object containing the details of the account to update.
     *
     * @param string $new_ip The new IP address for the account.
     *
     * @return bool True if the IP address was changed successfully, if not the sever manager should throw an exception
     *
     * @throws Server_Exception If there was an error while changing the IP address.
     */
    abstract public function changeAccountIp(Server_Account $a, $new_ip);


    /**
     * Changes the package for an account on the server.
     *
     * @param Server_Account $a Account object containing the details of the account to update.
     *
     * @param Server_Package $p New package for the account.
     *
     * @return bool True if the package was changed successfully, if not the sever manager should throw an exception
     *
     * @throws Server_Exception If there was an error while changing the package.
     */
    abstract public function changeAccountPackage(Server_Account $a, Server_Package $p);
}
