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
    private $username = null;
    private $password = null;
    private $domain = null;
    private $ip = null;

    private ?Server_Client $client = null;
    private ?Server_Package $package = null;
    private ?bool $reseller = null;
    private ?bool $suspended = null;
    private $ns_1 = null;
    private $ns_2 = null;
    private $ns_3 = null;
    private $ns_4 = null;
    private $note = null;

    /**
     * @return null
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setUsername($param): static
    {
        $this->username = $param;

        return $this;
    }

    /**
     * @return null
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setPassword($param): static
    {
        $this->password = $param;

        return $this;
    }

    /**
     * @return null
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setDomain($param): static
    {
        $this->domain = $param;

        return $this;
    }

    /**
     * @return null
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setIp($param): static
    {
        $this->ip = $param;

        return $this;
    }

    /**
     * @return Server_Client|null
     */
    public function getClient(): ?Server_Client
    {
        return $this->client;
    }

    /**
     * @return $this
     */
    public function setClient(Server_Client $param): static
    {
        $this->client = $param;

        return $this;
    }

    /**
     * @return Server_Package
     */
    public function getPackage(): Server_Package
    {
        return $this->package;
    }

    /**
     * @return $this
     */
    public function setPackage(Server_Package $param): static
    {
        $this->package = $param;

        return $this;
    }

    /**
     * @return null
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setNote($param): static
    {
        $this->note = $param;

        return $this;
    }

    /**
     * @return bool|null
     */
    public function getReseller(): ?bool
    {
        return $this->reseller;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setReseller($param): static
    {
        $this->reseller = (bool)$param;

        return $this;
    }

    /**
     * @return bool|null
     */
    public function getSuspended(): ?bool
    {
        return $this->suspended;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setSuspended($param): static
    {
        $this->suspended = (bool)$param;

        return $this;
    }

    /**
     * @return null
     */
    public function getNs1()
    {
        return $this->ns_1;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setNs1($param): static
    {
        $this->ns_1 = $param;

        return $this;
    }

    /**
     * @return null
     */
    public function getNs2()
    {
        return $this->ns_2;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setNs2($param): static
    {
        $this->ns_2 = $param;

        return $this;
    }

    /**
     * @return null
     */
    public function getNs3()
    {
        return $this->ns_3;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setNs3($param): static
    {
        $this->ns_3 = $param;

        return $this;
    }

    /**
     * @return null
     */
    public function getNs4()
    {
        return $this->ns_4;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setNs4($param): static
    {
        $this->ns_4 = $param;

        return $this;
    }
}
