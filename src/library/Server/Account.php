<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Account
{
    private ?string $username = null;
    private ?string $password = null;
    private ?string $domain = null;
    private ?string $ip = null;
    private ?Server_Client $client = null;
    private ?Server_Package $package = null;
    private ?bool $reseller = null;
    private ?bool $suspended = null;
    private ?string $ns_1 = null;
    private ?string $ns_2 = null;
    private ?string $ns_3 = null;
    private ?string $ns_4 = null;
    private ?string $note = null;

    /**
     * Get the username associated with the Server_Account instance.
     *
     * @return string returns the username as a string
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Set the username associated with the Server_Account instance.
     *
     * @param string|null $username the username to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get the password associated with the Server_Account instance.
     *
     * @return string|null returns the password as a string if it exists, otherwise returns null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Set the password associated with the Server_Account instance.
     *
     * @param string|null $password the password to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get the domain associated with the Server_Account instance.
     *
     * @return string|null returns the domain as a string if it exists, otherwise returns null
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * Set the domain associated with the Server_Account instance.
     *
     * @param string|null $domain the domain to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setDomain(?string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * Get the IP address associated with the Server_Account instance.
     *
     * @return string|null returns the IP address as a string if it exists, otherwise returns null
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }

    /**
     * Set the IP address associated with the Server_Account instance.
     *
     * @param string|null $ip the IP address to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setIp(?string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }

    /**
     * Get the client associated with the Server_Account instance.
     *
     * @return Server_Client|null returns the client if it exists, otherwise returns null
     */
    public function getClient(): ?Server_Client
    {
        return $this->client;
    }

    /**
     * Set the client associated with the Server_Account instance.
     *
     * @param Server_Client|null $client the client to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setClient(?Server_Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Get the package associated with the Server_Account instance.
     *
     * @return Server_Package returns the package
     */
    public function getPackage(): Server_Package
    {
        return $this->package;
    }

    /**
     * Set the package associated with the Server_Account instance.
     *
     * @param Server_Package|null $package the package to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setPackage(?Server_Package $package): static
    {
        $this->package = $package;

        return $this;
    }

    /**
     * Get the note associated with the Server_Account instance.
     *
     * @return string|null returns the note as a string if it exists, otherwise returns null
     */
    public function getNote(): ?string
    {
        return $this->note;
    }

    /**
     * Set the note associated with the Server_Account instance.
     *
     * @param string|null $note the note to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    /**
     * Get the reseller status associated with the Server_Account instance.
     *
     * @return bool|null returns the reseller status as a boolean if it exists, otherwise returns null
     */
    public function getReseller(): ?bool
    {
        return $this->reseller;
    }

    /**
     * Set the reseller status associated with the Server_Account instance.
     *
     * @param bool $reseller the reseller status to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setReseller(bool $reseller): static
    {
        $this->reseller = $reseller;

        return $this;
    }

    /**
     * Get the suspension status associated with the Server_Account instance.
     *
     * @return bool|null returns the suspension status as a boolean if it exists, otherwise returns null
     */
    public function getSuspended(): ?bool
    {
        return $this->suspended;
    }

    /**
     * Set the suspension status associated with the Server_Account instance.
     *
     * @param bool $suspended the suspension status to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setSuspended(bool $suspended): static
    {
        $this->suspended = $suspended;

        return $this;
    }

    /**
     * Get the first nameserver associated with the Server_Account instance.
     *
     * @return string|null returns the first nameserver as a string if it exists, otherwise returns null
     */
    public function getNs1(): ?string
    {
        return $this->ns_1;
    }

    /**
     * Set the first nameserver associated with the Server_Account instance.
     *
     * @param string|null $ns1 the first nameserver to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setNs1(?string $ns1): static
    {
        $this->ns_1 = $ns1;

        return $this;
    }

    /**
     * Get the second nameserver associated with the Server_Account instance.
     *
     * @return string|null returns the second nameserver as a string if it exists, otherwise returns null
     */
    public function getNs2(): ?string
    {
        return $this->ns_2;
    }

    /**
     * Set the second nameserver associated with the Server_Account instance.
     *
     * @param string|null $ns2 the second nameserver to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setNs2(?string $ns2): static
    {
        $this->ns_2 = $ns2;

        return $this;
    }

    /**
     * Get the third nameserver associated with the Server_Account instance.
     *
     * @return string|null returns the third nameserver as a string if it exists, otherwise returns null
     */
    public function getNs3(): ?string
    {
        return $this->ns_3;
    }

    /**
     * Set the third nameserver associated with the Server_Account instance.
     *
     * @param string|null $ns3 the third nameserver to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setNs3(?string $ns3): static
    {
        $this->ns_3 = $ns3;

        return $this;
    }

    /**
     * Get the fourth nameserver associated with the Server_Account instance.
     *
     * @return string|null returns the fourth nameserver as a string if it exists, otherwise returns null
     */
    public function getNs4(): ?string
    {
        return $this->ns_4;
    }

    /**
     * Set the fourth nameserver associated with the Server_Account instance.
     *
     * @param string|null $ns4 the fourth nameserver to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setNs4(?string $ns4): static
    {
        $this->ns_4 = $ns4;

        return $this;
    }
}
