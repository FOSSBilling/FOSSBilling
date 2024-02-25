<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Payment_Invoice_Buyer
{
    private $first_name;
    private $last_name;
    private $company;
    private $email;
    private $address;
    private $city;
    private $state;
    private $country;
    private $zip;
    private $phone;
    private $phone_cc;

    /**
     * Set the first name of the buyer.
     *
     * @param string $param the first name of the buyer
     *
     * @return $this the current object, for method chaining
     */
    public function setFirstName($param)
    {
        $this->first_name = $param;

        return $this;
    }

    /**
     * Get the first name of the buyer.
     *
     * @return string the first name of the buyer
     */
    public function getFirstName()
    {
        return $this->first_name;
    }

    /**
     * Set the last name of the buyer.
     *
     * @param string $param the last name of the buyer
     *
     * @return $this the current object, for method chaining
     */
    public function setLastName($param)
    {
        $this->last_name = $param;

        return $this;
    }

    /**
     * Get the last name of the buyer.
     *
     * @return string the last name of the buyer
     */
    public function getLastName()
    {
        return $this->last_name;
    }

    /**
     * Set the company name of the buyer.
     *
     * @param string $param the company name of the buyer
     *
     * @return $this the current object, for method chaining
     */
    public function setCompany($param)
    {
        $this->company = $param;

        return $this;
    }

    /**
     * Get the company name of the buyer.
     *
     * @return string the company name of the buyer
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * Set the email address of the buyer.
     *
     * @param string $param the email address of the buyer
     *
     * @return $this the current object, for method chaining
     */
    public function setEmail($param)
    {
        $this->email = $param;

        return $this;
    }

    /**
     * Get the email address of the buyer.
     *
     * @return string the email address of the buyer
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set the mailing address of the buyer.
     *
     * @param string $param the mailing address of the buyer
     *
     * @return $this the current object, for method chaining
     */
    public function setAddress($param)
    {
        $this->address = $param;

        return $this;
    }

    /**
     * Get the mailing address of the buyer.
     *
     * @return string the mailing address of the buyer
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set the city of the buyer's mailing address.
     *
     * @param string $param the city of the buyer's mailing address
     *
     * @return $this the current object, for method chaining
     */
    public function setCity($param)
    {
        $this->city = $param;

        return $this;
    }

    /**
     * Get the city of the buyer's mailing address.
     *
     * @return string The city of the buyer's mailing address
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Set the state/province of the buyer's mailing address.
     *
     * @param string $param the state/province of the buyer's mailing address
     *
     * @return $this the current object, for method chaining
     */
    public function setState($param)
    {
        $this->state = $param;

        return $this;
    }

    /**
     * Get the state/province of the buyer's mailing address.
     *
     * @return string the state/province of the buyer's mailing address
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Set the country of the buyer's mailing address.
     *
     * @param string $param the country of the buyer's mailing address
     *
     * @return $this the current object, for method chaining
     */
    public function setCountry($param)
    {
        $this->country = $param;

        return $this;
    }

    /**
     * Get the country of the buyer's mailing address.
     *
     * @return string the country of the buyer's mailing address
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * Set the ZIP/postal code of the buyer's mailing address.
     *
     * @param string $param the ZIP/postal code of the buyer's mailing address
     *
     * @return $this the current object, for method chaining
     */
    public function setZip($param)
    {
        $this->zip = $param;

        return $this;
    }

    /**
     * Get the ZIP/postal code of the buyer's mailing address.
     *
     * @return string the ZIP/postal code of the buyer's mailing address
     */
    public function getZip()
    {
        return $this->zip;
    }

    /**
     * Set the phone number of the buyer.
     *
     * @param string $param the phone number of the buyer
     *
     * @return $this the current object, for method chaining
     */
    public function setPhone($param)
    {
        $this->phone = $param;

        return $this;
    }

    /**
     * Get the phone number of the buyer.
     *
     * @return string the phone number of the buyer
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set the country code of the buyer's phone number.
     *
     * @param string $param the country code of the buyer's phone number
     *
     * @return $this the current object, for method chaining
     */
    public function setPhoneCountryCode($param)
    {
        $this->phone_cc = $param;

        return $this;
    }

    /**
     * Get the country code of the buyer's phone number.
     *
     * @return string the country code of the buyer's phone number
     */
    public function getPhoneCountryCode()
    {
        return $this->phone_cc;
    }
}
