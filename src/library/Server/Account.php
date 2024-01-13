<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Account
{
    private $username;
    private $password;
    private $domain;
    private $ip;

    private ?\Server_Client $client = null;
    private ?\Server_Package $package = null;
    private ?bool $reseller = null;
    private ?bool $suspended = null;
    private $ns_1;
    private $ns_2;
    private $ns_3;
    private $ns_4;
    private $note;

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
     * @return $this
     */
    public function setPackage(Server_Package $param)
    {
        $this->package = $param;

        return $this;
    }

    public function getPackage(): Server_Package
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
        $this->reseller = (bool) $param;

        return $this;
    }

    public function getReseller()
    {
        return $this->reseller;
    }

    public function setSuspended($param)
    {
        $this->suspended = (bool) $param;

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
