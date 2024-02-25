<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Registrar_Domain implements Stringable
{
    private $_tld;
    private $_sld;
    private $_registered_at;
    private $_expires_at;
    private ?int $_period = null;
    private $_epp;
    private ?bool $_privacy = null;
    private $_locked;
    private $_ns1;
    private $_ns2;
    private $_ns3;
    private $_ns4;

    private ?Registrar_Domain_Contact $_contact_registrar = null;

    private ?Registrar_Domain_Contact $_contact_admin = null;

    private ?Registrar_Domain_Contact $_contact_tech = null;

    private ?Registrar_Domain_Contact $_contact_billing = null;

    public function setRegistrationPeriod($years)
    {
        $this->_period = (int) $years;

        return $this;
    }

    public function getRegistrationPeriod()
    {
        return $this->_period;
    }

    public function getName()
    {
        return $this->_sld . $this->_tld;
    }

    public function setExpirationTime($param)
    {
        $this->_expires_at = $param;

        return $this;
    }

    public function getExpirationTime()
    {
        return $this->_expires_at;
    }

    public function setRegistrationTime($param)
    {
        $this->_registered_at = $param;

        return $this;
    }

    public function getRegistrationTime()
    {
        return $this->_registered_at;
    }

    public function setContactRegistrar(Registrar_Domain_Contact $c)
    {
        $this->_contact_registrar = $c;

        return $this;
    }

    public function getContactRegistrar()
    {
        return $this->_contact_registrar;
    }

    public function setContactAdmin(Registrar_Domain_Contact $c)
    {
        $this->_contact_admin = $c;

        return $this;
    }

    public function getContactAdmin()
    {
        return $this->_contact_admin;
    }

    public function setContactTech(Registrar_Domain_Contact $c)
    {
        $this->_contact_tech = $c;

        return $this;
    }

    public function getContactTech()
    {
        return $this->_contact_tech;
    }

    public function setContactBilling(Registrar_Domain_Contact $c)
    {
        $this->_contact_billing = $c;

        return $this;
    }

    public function getContactBilling()
    {
        return $this->_contact_billing;
    }

    public function setEpp($param)
    {
        $this->_epp = $param;

        return $this;
    }

    public function getEpp()
    {
        return $this->_epp;
    }

    public function setPrivacyEnabled($param)
    {
        $this->_privacy = (bool) $param;

        return $this;
    }

    public function getPrivacyEnabled()
    {
        return $this->_privacy;
    }

    public function setTld($param)
    {
        $this->_tld = $param;

        return $this;
    }

    public function getTld($with_dot = true)
    {
        if ($with_dot === false && $this->_tld[0] == '.') {
            return ltrim($this->_tld, '.');
        }

        return $this->_tld;
    }

    public function setSld($param)
    {
        $this->_sld = $param;

        return $this;
    }

    public function getSld()
    {
        return $this->_sld;
    }

    public function setNs1($param)
    {
        $this->_ns1 = $param;

        return $this;
    }

    public function getNs1()
    {
        return $this->_ns1;
    }

    public function setNs2($param)
    {
        $this->_ns2 = $param;

        return $this;
    }

    public function getNs2()
    {
        return $this->_ns2;
    }

    public function setNs3($param)
    {
        $this->_ns3 = $param;

        return $this;
    }

    public function getNs3()
    {
        return $this->_ns3;
    }

    public function setNs4($param)
    {
        $this->_ns4 = $param;

        return $this;
    }

    public function getNs4()
    {
        return $this->_ns4;
    }

    public function setLocked($param)
    {
        $this->_locked = $param;

        return $this;
    }

    public function getLocked()
    {
        return $this->_locked;
    }

    public function __toString(): string
    {
        $c = '';
        $c .= sprintf('Name: %s', $this->getName()) . PHP_EOL;
        $c .= sprintf('TLD: %s', $this->getTld()) . PHP_EOL;
        $c .= sprintf('SLD: %s', $this->getSld()) . PHP_EOL;

        $c .= sprintf('EPP: %s', $this->getEpp()) . PHP_EOL;
        $c .= sprintf('Registration period: %s year(s)', $this->getRegistrationPeriod()) . PHP_EOL;

        $registered_at = ($this->getRegistrationTime()) ? date('Y-m-d', $this->getRegistrationTime()) : '-';
        $c .= sprintf('Registered at: %s', $registered_at) . PHP_EOL;

        $expires_at = ($this->getExpirationTime()) ? date('Y-m-d', $this->getExpirationTime()) : '-';
        $c .= sprintf('Expires at: %s', $expires_at) . PHP_EOL;

        $privacy = ($this->getPrivacyEnabled()) ? 'Yes' : 'No';
        $c .= sprintf('WHOIS Privacy Protection enabled: %s', $privacy);
        $c .= PHP_EOL;
        $c .= PHP_EOL;

        $c .= sprintf('Nameserver #1: %s', $this->getNs1()) . PHP_EOL;
        $c .= sprintf('Nameserver #2: %s', $this->getNs2()) . PHP_EOL;
        $c .= sprintf('Nameserver #3: %s', $this->getNs3()) . PHP_EOL;
        $c .= sprintf('Nameserver #4: %s', $this->getNs4()) . PHP_EOL;

        if ($this->getContactRegistrar() instanceof Registrar_Domain_Contact) {
            $c .= PHP_EOL;
            $c .= PHP_EOL;
            $c .= 'Contact Registrar' . PHP_EOL;
            $c .= $this->getContactRegistrar()->__toString();
        }

        return $c;
    }
}
