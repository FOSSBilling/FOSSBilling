<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Server_Client
{
    private $id         = NULL;
    private $email      = NULL;
    private $full_name  = 'BoxBilling Client';
    private $company    = 'BoxBilling';
    private $www        = 'www.boxbilling.com';
    private $address_1  = NULL;
    private $address_2  = NULL;
    private $street     = NULL;
    private $state      = 'n/a';
    private $country    = 'US';
    private $city       = NULL;
    private $zip        = NULL;
    private $telephone  = NULL;
    private $fax  = NULL;

    public function __call($name, $arguments)
    {
        if (version_compare(PHP_VERSION, '5.4.0') < 0) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }
        else {
            // Get only the stack frames we need (PHP 5.4 only).
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        }
        error_log(sprintf("Calling %s inaccessible method %s from %s::%d", get_class($this), $name, $backtrace[1]['file'], $backtrace[1]['line']));
        return '';
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setFullName($param)
    {
        $this->full_name = $param;
        return $this;
    }

    public function getFullName()
    {
        return $this->full_name;
    }

    public function setCompany($company)
    {
        $this->company = $company;
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

    public function setAddress1($param)
    {
        $this->address_1 = $param;
        return $this;
    }

    public function getAddress1()
    {
        return $this->address_1;
    }
    
    public function setAddress2($param)
    {
        $this->address_2 = $param;
        return $this;
    }

    public function getAddress2()
    {
        return $this->address_2;
    }
    
    public function setStreet($param)
    {
        $this->street = $param;
        return $this;
    }

    public function getStreet()
    {
        return $this->street;
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

    public function setTelephone($param)
    {
        $this->telephone = $param;
        return $this;
    }

    public function getTelephone()
    {
        return $this->telephone;
    }
    
    public function setFax($param)
    {
        $this->fax = $param;
        return $this;
    }

    public function getFax()
    {
        return $this->fax;
    }
    
    public function setWww($param)
    {
        $this->www = $param;
        return $this;
    }

    public function getWww()
    {
        return $this->www;
    }
}