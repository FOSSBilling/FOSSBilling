<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

use Symfony\Component\HttpClient\HttpClient;

abstract class Server_Manager
{
    private $_log = null;

    protected $_config = array(
        'ip'         =>  NULL,
        'host'       =>  NULL,
        'secure'     =>  FALSE,
        'username'   =>  NULL,
        'password'   =>  NULL,
        'accesshash' =>  NULL,
        'port'       =>  NULL,
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
     *                       - 'port': Custom port number for the connection.
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

        if (isset($options['ssl'])) {
            $this->_config['ssl'] = $options['ssl'];
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
     * Gets a new HttpClient object.
     *
     * @return Symfony\Component\HttpClient\HttpClient The HttpClient object.
     */
    public function getHttpClient()
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
     * @return string
     */
    abstract public function getLoginUrl();

    /**
     * Returns the login URL for the server for reseller accounts.
     * 
     * @return string
     */
    abstract public function getResellerLoginUrl();

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
     * @param Server_Account $a Account object containing the details of the account to create.
     * 
     * @return bool True if the account was created successfully, if not the server manager should throw an exception
     * 
     * @throws Server_Exception If there was an error while creating the account.
     */
    abstract public function createAccount(Server_Account $a);


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
