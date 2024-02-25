<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Client
{
    private ?int $id = null;
    private ?string $email = null;
    private ?string $full_name = 'FOSSBilling Client';
    private ?string $company = 'FOSSBilling';
    private ?string $www = 'www.fossbilling.org';
    private ?string $address_1 = null;
    private ?string $address_2 = null;
    private ?string $street = null;
    private ?string $state = 'n/a';
    private ?string $country = 'US';
    private ?string $city = null;
    private ?string $zip = null;
    private ?string $telephone = null;
    private ?string $fax = null;

    public function __call($name, $arguments)
    {
        if (version_compare(PHP_VERSION, '5.4.0') < 0) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        } else {
            // Get only the stack frames we need (PHP 5.4 only).
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        }
        error_log(sprintf('Calling %s inaccessible method %s from %s::%d', static::class, $name, $backtrace[1]['file'], $backtrace[1]['line']));

        return '';
    }

    /**
     * Get the ID of the Server_Client instance.
     *
     * @return int|null returns the ID of the Server_Client instance
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set the ID of the Server_Client instance.
     *
     * @param int $id the ID to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the full name of the Server_Client instance.
     *
     * @return string|null returns the full name as a string if it exists, otherwise returns null
     */
    public function getFullName(): ?string
    {
        return $this->full_name;
    }

    /**
     * Set the full name of the Server_Client instance.
     *
     * @param string|null $fullName the full name to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setFullName(?string $fullName): static
    {
        $this->full_name = $fullName;

        return $this;
    }

    /**
     * Get the company of the Server_Client instance.
     *
     * @return string|null returns the company as a string if it exists, otherwise returns null
     */
    public function getCompany(): ?string
    {
        return $this->company;
    }

    /**
     * Set the company of the Server_Client instance.
     *
     * @param string|null $company the company to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setCompany(?string $company): static
    {
        $this->company = $company;

        return $this;
    }

    /**
     * Get the email of the Server_Client instance.
     *
     * @return string|null returns the email of the Server_Client instance
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Set the email of the Server_Client instance.
     *
     * @param string|null $email the email to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get the first address of the Server_Client instance.
     *
     * @return string|null returns the first address of the Server_Client instance
     */
    public function getAddress1(): ?string
    {
        return $this->address_1;
    }

    /**
     * Set the first address of the Server_Client instance.
     *
     * @param string|null $address1 the first address to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setAddress1(?string $address1): static
    {
        $this->address_1 = $address1;

        return $this;
    }

    /**
     * Get the second address of the Server_Client instance.
     *
     * @return string|null returns the second address of the Server_Client instance
     */
    public function getAddress2(): ?string
    {
        return $this->address_2;
    }

    /**
     * Set the second address of the Server_Client instance.
     *
     * @param string|null $address2 the second address to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setAddress2(?string $address2): static
    {
        $this->address_2 = $address2;

        return $this;
    }

    /**
     * Get the street of the Server_Client instance.
     *
     * @return string|null returns the street of the Server_Client instance
     */
    public function getStreet(): ?string
    {
        return $this->street;
    }

    /**
     * Set the street of the Server_Client instance.
     *
     * @param string|null $street the street to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setStreet(?string $street): static
    {
        $this->street = $street;

        return $this;
    }

    /**
     * Get the city of the Server_Client instance.
     *
     * @return string|null returns the city of the Server_Client instance
     */
    public function getCity(): ?string
    {
        return $this->city;
    }

    /**
     * Set the city of the Server_Client instance.
     *
     * @param string|null $city the city to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Get the state of the Server_Client instance.
     *
     * @return string|null returns the state as a string if it exists, otherwise returns null
     */
    public function getState(): ?string
    {
        return $this->state;
    }

    /**
     * Set the state of the Server_Client instance.
     *
     * @param string|null $state the state to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setState(?string $state): static
    {
        $this->state = $state;

        return $this;
    }

    /**
     * Get the country of the Server_Client instance.
     *
     * @return string|null returns the country as a string if it exists, otherwise returns null
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * Set the country of the Server_Client instance.
     *
     * @param string|null $country the country to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Get the zip code of the Server_Client instance.
     *
     * @return string|null returns the zip code of the Server_Client instance
     */
    public function getZip(): ?string
    {
        return $this->zip;
    }

    /**
     * Set the zip code of the Server_Client instance.
     *
     * @param string|null $zip the zip code to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setZip(?string $zip): static
    {
        $this->zip = $zip;

        return $this;
    }

    /**
     * Get the telephone number of the Server_Client instance.
     *
     * @return string|null returns the telephone number of the Server_Client instance
     */
    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    /**
     * Set the telephone number of the Server_Client instance.
     *
     * @param string|null $telephone the telephone number to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    /**
     * Get the fax number of the Server_Client instance.
     *
     * @return string|null returns the fax number of the Server_Client instance
     */
    public function getFax(): ?string
    {
        return $this->fax;
    }

    /**
     * Set the fax number of the Server_Client instance.
     *
     * @param string|null $fax the fax number to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setFax(?string $fax): static
    {
        $this->fax = $fax;

        return $this;
    }

    /**
     * Get the website URL of the Server_Client instance.
     *
     * @return string|null returns the website URL as a string if it exists, otherwise returns null
     */
    public function getWww(): ?string
    {
        return $this->www;
    }

    /**
     * Set the website URL of the Server_Client instance.
     *
     * @param string|null $www the website URL to be set
     *
     * @return $this returns the current instance to allow for method chaining
     */
    public function setWww(?string $www): static
    {
        $this->www = $www;

        return $this;
    }
}
