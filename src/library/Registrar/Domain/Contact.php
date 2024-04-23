<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Registrar_Domain_Contact implements Stringable
{
    private $id;
    private $name;
    private $firstname;
    private $lastname;
    private $email;
    private $city;
    private $zip;
    private $country;
    private $state;
    private $tel;
    private $tel_cc;
    private $fax;
    private $fax_cc;
    private $company;
    private string $company_number = '';
    private $address_1;
    private $address_2;
    private $address_3;
    private $username;
    private $password;
    private $document_type;
    private $document_nr;
    private $job_title;
    private string $birthday = '';
    private string $idn_language_code = '';

    /**
     * @return string
     */
    public function getCompanyNumber()
    {
        return $this->company_number;
    }

    /**
     * @param string $company_number
     */
    public function setCompanyNumber($company_number)
    {
        $this->company_number = $company_number;

        return $this;
    }

    /**
     * @return string
     */
    public function getIdnLanguageCode()
    {
        return $this->idn_language_code;
    }

    /**
     * @param string $idn_language_code
     */
    public function setIdnLanguageCode($idn_language_code)
    {
        $this->idn_language_code = $idn_language_code;

        return $this;
    }

    /**
     * @return string
     */
    public function getBirthday()
    {
        return $this->birthday;
    }

    /**
     * @param string $birthday
     */
    public function setBirthday($birthday)
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function setId($param)
    {
        $this->id = $param;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($param)
    {
        $this->name = $param;

        return $this;
    }

    public function getName()
    {
        if ($this->name) {
            return $this->name;
        }

        return $this->firstname . ' ' . $this->lastname;
    }

    public function setFirstName($param)
    {
        $this->firstname = $param;

        return $this;
    }

    public function getFirstName()
    {
        if ($this->firstname) {
            return $this->firstname;
        }

        $bits = explode(' ', $this->name);

        return $bits[0] ?? '';
    }

    public function setLastName($param)
    {
        $this->lastname = $param;

        return $this;
    }

    public function getLastName()
    {
        if ($this->lastname) {
            return $this->lastname;
        }

        $bits = explode(' ', $this->name);

        return isset($bits[1]) ? str_replace($bits[0] . ' ', '', $this->name) : '';
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

    public function setCity($param)
    {
        $this->city = $param;

        return $this;
    }

    public function getCity()
    {
        return $this->city;
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

    public function setState($param)
    {
        $this->state = $param;

        return $this;
    }

    public function getState()
    {
        return $this->state;
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

    public function setTel($param)
    {
        $this->tel = $param;

        return $this;
    }

    public function getTel()
    {
        return $this->tel;
    }

    public function setTelCc($param)
    {
        $this->tel_cc = $param;

        return $this;
    }

    public function getTelCc()
    {
        return $this->tel_cc;
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

    public function setFaxCc($param)
    {
        $this->fax_cc = $param;

        return $this;
    }

    public function getFaxCc()
    {
        return $this->fax_cc;
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

    public function setAddress3($param)
    {
        $this->address_3 = $param;

        return $this;
    }

    public function getAddress3()
    {
        return $this->address_3;
    }

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

    public function setDocumentType($param)
    {
        $this->document_type = $param;

        return $this;
    }

    public function getDocumentType()
    {
        return $this->document_type;
    }

    public function setDocumentNr($param)
    {
        $this->document_nr = $param;

        return $this;
    }

    public function getDocumentNr()
    {
        return $this->document_nr;
    }

    public function setJobTitle($param)
    {
        $this->job_title = $param;

        return $this;
    }

    public function getJobTitle()
    {
        return $this->job_title;
    }

    public function getAddress()
    {
        $data = [
            $this->getAddress1(),
            $this->getAddress2(),
            $this->getAddress3(),
        ];

        return implode(' ', $data);
    }

    public function __toString(): string
    {
        $c = '';
        $c .= sprintf('Id: %s', $this->getId()) . PHP_EOL;
        $c .= sprintf('Name: %s', $this->getName()) . PHP_EOL;
        $c .= PHP_EOL;
        $c .= sprintf('Email: %s', $this->getEmail()) . PHP_EOL;
        $c .= sprintf('Username: %s', $this->getUsername()) . PHP_EOL;
        $c .= sprintf('Password: %s', $this->getPassword()) . PHP_EOL;
        $c .= PHP_EOL;
        $c .= sprintf('Company: %s', $this->getCompany()) . PHP_EOL;
        $c .= sprintf('Address: %s', $this->getAddress()) . PHP_EOL;
        $c .= sprintf('City: %s', $this->getCity()) . PHP_EOL;
        $c .= sprintf('State: %s', $this->getState()) . PHP_EOL;
        $c .= sprintf('Zip: %s', $this->getZip()) . PHP_EOL;
        $c .= sprintf('Country: %s', $this->getCountry()) . PHP_EOL;
        $c .= PHP_EOL;
        $c .= sprintf('Tel: %s', $this->getTelCc() . ' ' . $this->getTel()) . PHP_EOL;
        $c .= sprintf('Fax: %s', $this->getFaxCc() . ' ' . $this->getFax()) . PHP_EOL;
        $c .= PHP_EOL;
        $c .= sprintf('Document type: %s', $this->getDocumentType()) . PHP_EOL;

        return $c . (sprintf('Document nr: %s', $this->getDocumentNr()) . PHP_EOL);
    }

    public function toArray()
    {
        return get_object_vars($this);
    }
}
