<?php
/**
 * BoxBilling
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file LICENSE.txt
 * It is also available through the world-wide-web at this URL:
 * http://www.boxbilling.com/LICENSE.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@boxbilling.com so we can send you a copy immediately.
 *
 * @copyright Copyright (c) 2010-2012 BoxBilling (http://www.boxbilling.com)
 * @license   http://www.boxbilling.com/LICENSE.txt
 * @version   $Id$
 */
class Server_Account
{
    private $username   = NULL;
    private $password   = NULL;
    private $domain     = NULL;
    private $ip         = NULL;

    /**
     * @var Server_Package
     */
    private $package    = NULL;

    /**
     * @var Server_Client
     */
    private $client     = NULL;
    private $reseller   = NULL;
    private $suspended  = NULL;
    private $ns_1       = NULL;
    private $ns_2       = NULL;
    private $ns_3       = NULL;
    private $ns_4       = NULL;
    private $note       = 'Client created via BoxBilling';
    
    public function setUsername($param)
    {
        $this->username = $param;
        return $this;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setPassword($param)
    {
        $this->password = $param;
        return $this;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setDomain($param)
    {
        $this->domain = $param;
        return $this;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    public function setIp($param)
    {
        $this->ip = $param;
        return $this;
    }

    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param Server_Client $param
     * @return $this
     */
    public function setClient(Server_Client $param)
    {
        $this->client = $param;
        return $this;
    }

    /**
     * @return Server_Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param Server_Package $param
     * @return $this
     */
    public function setPackage(Server_Package $param)
    {
        $this->package = $param;
        return $this;
    }

    /**
     * @return Server_package
     */
    public function getPackage()
    {
        return $this->package;
    }

    public function setNote($param)
    {
        $this->note = $param;
        return $this;
    }

    public function getNote()
    {
        return $this->note;
    }

    public function setReseller($param)
    {
        $this->reseller = (bool)$param;
        return $this;
    }

    public function getReseller()
    {
        return $this->reseller;
    }

    public function setSuspended($param)
    {
        $this->suspended = (bool)$param;
        return $this;
    }

    public function getSuspended()
    {
        return $this->suspended;
    }
    
    public function setNs1($param)
    {
        $this->ns_1 = $param;
        return $this;
    }

    public function getNs1()
    {
        return $this->ns_1;
    }
    
    public function setNs2($param)
    {
        $this->ns_2 = $param;
        return $this;
    }

    public function getNs2()
    {
        return $this->ns_2;
    }

    public function setNs3($param)
    {
        $this->ns_3 = $param;
        return $this;
    }

    public function getNs3()
    {
        return $this->ns_3;
    }

    public function setNs4($param)
    {
        $this->ns_4 = $param;
        return $this;
    }

    public function getNs4()
    {
        return $this->ns_4;
    }
}