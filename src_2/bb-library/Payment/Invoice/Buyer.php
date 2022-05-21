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

    public function setFirstName($param)
    {
        $this->first_name = $param;
        return $this;
    }

    public function getFirstName()
    {
        return $this->first_name;
    }

    public function setLastName($param)
    {
        $this->last_name = $param;
        return $this;
    }

    public function getLastName()
    {
        return $this->last_name;
    }

    public function setCompany($param)
    {
        $this->company = $param;
        return $this;
    }

    public function getCompany()
    {
        return $this->company;
    }

    public function setEmail($param)
    {
        $this->email = $param;
        return $this;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setAddress($param)
    {
        $this->address = $param;
        return $this;
    }

    public function getAddress()
    {
        return $this->address;
    }
    
    public function setCity($param)
    {
        $this->city = $param;
        return $this;
    }

    public function getCity()
    {
        return $this->city;
    }

    public function setState($param)
    {
        $this->state = $param;
        return $this;
    }

    public function getState()
    {
        return $this->state;
    }

    public function setCountry($param)
    {
        $this->country = $param;
        return $this;
    }

    public function getCountry()
    {
        return $this->country;
    }

    public function setZip($param)
    {
        $this->zip = $param;
        return $this;
    }

    public function getZip()
    {
        return $this->zip;
    }

    public function setPhone($param)
    {
        $this->phone = $param;
        return $this;
    }

    public function getPhone()
    {
        return $this->phone;
    }
    
    /**
     * @param string $param
     */
    public function setPhoneCountryCode($param)
    {
        $this->phone_cc = $param;
        return $this;
    }

    public function getPhoneCountryCode()
    {
        return $this->phone_cc;
    }

}