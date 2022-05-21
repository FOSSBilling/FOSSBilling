<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

abstract class Server_Manager
{
    private $_log = null;
    
    protected $_config = array(
        'ip'        =>  NULL,
        'host'      =>  NULL,
        'secure'    =>  FALSE,
        'username'  =>  NULL,
        'password'  =>  NULL,
        'accesshash'=>  NULL,
        'port'      =>  NULL,
    );

    public function __construct($options)
    {
        if(isset($options['ip'])) {
            $this->_config['ip'] = $options['ip'];
        }

        if(isset($options['host'])) {
            $this->_config['host'] = $options['host'];
        }

        if(isset($options['secure'])) {
            $this->_config['secure'] = (bool)$options['secure'];
        }

        if(isset($options['username'])) {
            $this->_config['username'] = $options['username'];
        }

        if(isset($options['password'])) {
            $this->_config['password'] = $options['password'];
        }

        if(isset($options['accesshash'])) {
            $this->_config['accesshash'] = $options['accesshash'];
        }

        if(isset($options['ssl'])) {
            $this->_config['ssl'] = $options['ssl'];
        }

        /**
         * Custom connection port to API.
         * If not provided, using default server manager port
         */
        if(isset($options['port'])) {
            $this->_config['port'] = $options['port'];
        }
        
        $this->init();
    }

    public function setLog(Box_Log $value)
    {
        $this->_log = $value;
        return $this;
    }

    public function getLog()
    {
        if(!$this->_log instanceof Box_Log) {
            $log = new Box_Log();
            $log->addWriter(new Box_LogDb('Model_ActivitySystem'));
            return $log;
        }
        return $this->_log;
    }

    protected function init(){}

    /**
     * @return string
     */
    abstract public function getLoginUrl();

    /**
     * @return string
     */
    abstract public function getResellerLoginUrl();

    /**
     * @return bool
     * @throws Server_Exception
     */
    abstract public function testConnection();

    /**
     * @param Server_Account
     * @return bool
     * @throws Server_Exception
     */
    abstract public function createAccount(Server_Account $a);

    /**
     * @param Server_Account
     * @return Server_Account
     * @throws Server_Exception
     */
    abstract public function synchronizeAccount(Server_Account $a);
    
    /**
     * @param Server_Account
     * @return bool
     * @throws Server_Exception
     */
    abstract public function suspendAccount(Server_Account $a);

    /**
     * @param Server_Account
     * @return bool
     * @throws Server_Exception
     */
    abstract public function unsuspendAccount(Server_Account $a);

    /**
     * @param Server_Account
     * @return bool
     * @throws Server_Exception
     */
    abstract public function cancelAccount(Server_Account $a);

    /**
     * @param Server_Account
     * @return bool
     * @throws Server_Exception
     */
    abstract public function changeAccountPassword(Server_Account $a, $new_password);

    /**
     * @param Server_Account
     * @return bool
     * @throws Server_Exception
     */
    abstract public function changeAccountUsername(Server_Account $a, $new_username);

    /**
     * @param Server_Account
     * @param string $new_domain
     * @return bool
     * @throws Server_Exception
     */
    abstract public function changeAccountDomain(Server_Account $a, $new_domain);

    /**
     * @param Server_Account
     * @param string - new ip
     * @return bool
     * @throws Server_Exception
     */
    abstract public function changeAccountIp(Server_Account $a, $new_ip);
    
    /**
     * @param Server_Account
     * @param Server_Package - new package
     * @return bool
     * @throws Server_Exception
     */
    abstract public function changeAccountPackage(Server_Account $a, Server_Package $p);
}