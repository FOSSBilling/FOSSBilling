<?php

/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Server_Client
{
    private $id;
    private $email;
    private ?string $full_name = 'FOSSBilling Client';
    private ?string $company = 'FOSSBilling';
    private ?string $www = 'www.fossbilling.org';
    private $address_1;
    private $address_2;
    private $street;
    private ?string $state = 'n/a';
    private ?string $country = 'US';
    private $city;
    private $zip;
    private $telephone;
    private $fax;

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
     * @return mixed
     */
    public function getId(): mixed
    {
        return $this->id;
    }

    /**
     * @param $id
     * @return $this
     */
    public function setId($id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return ?string
     */
    public function getFullName(): ?string
    {
        return $this->full_name;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setFullName($param): static
    {
        $this->full_name = $param;

        return $this;
    }

    /**
     * @return ?string
     */
    public function getCompany(): ?string
    {
        return $this->company;
    }

    /**
     * @param $company
     * @return $this
     */
    public function setCompany($company): static
    {
        $this->company = $company;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getEmail(): mixed
    {
        return $this->email;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setEmail($param): static
    {
        $this->email = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAddress1(): mixed
    {
        return $this->address_1;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setAddress1($param): static
    {
        $this->address_1 = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAddress2(): mixed
    {
        return $this->address_2;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setAddress2($param): static
    {
        $this->address_2 = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getStreet(): mixed
    {
        return $this->street;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setStreet($param): static
    {
        $this->street = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCity(): mixed
    {
        return $this->city;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setCity($param): static
    {
        $this->city = $param;

        return $this;
    }

    /**
     * @return ?string
     */
    public function getState(): ?string
    {
        return $this->state;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setState($param): static
    {
        $this->state = $param;

        return $this;
    }

    /**
     * @return ?string
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setCountry($param): static
    {
        $this->country = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getZip(): mixed
    {
        return $this->zip;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setZip($param): static
    {
        $this->zip = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTelephone(): mixed
    {
        return $this->telephone;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setTelephone($param): static
    {
        $this->telephone = $param;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFax(): mixed
    {
        return $this->fax;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setFax($param): static
    {
        $this->fax = $param;

        return $this;
    }

    /**
     * @return ?string
     */
    public function getWww(): ?string
    {
        return $this->www;
    }

    /**
     * @param $param
     * @return $this
     */
    public function setWww($param): static
    {
        $this->www = $param;

        return $this;
    }
}
