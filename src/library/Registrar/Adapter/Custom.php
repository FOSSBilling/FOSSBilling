<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Registrar_Adapter_Custom extends Registrar_AdapterAbstract
{
    public $config = [
        'use_rdap' => false,
    ];

    public function __construct($options)
    {
        $this->config['use_rdap'] = (bool) ($options['use_rdap'] ?? $options['use_whois'] ?? false);
    }

    public static function getConfig(): array
    {
        return [
            'label' => 'Custom Registrar always responds with positive results. Useful if no other registrar is suitable.',
            'form' => [
                'use_rdap' => ['radio', [
                    'multiOptions' => ['1' => 'Yes', '0' => 'No'],
                    'label' => 'Use RDAP Registry Lookups to Check for Domain Availability',
                ],
                ],
            ],
        ];
    }

    public function isDomaincanBeTransferred(Registrar_Domain $domain): bool
    {
        $this->getLog()->debug('Checking if domain can be transferred: ' . $domain->getName());

        return true;
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Checking domain availability: ' . $domain->getName());

        if (!$this->config['use_rdap']) {
            return true;
        }

        return $this->getRdap()->isDomainAvailable($domain->getName()) ?? true;
    }

    public function modifyNs(Registrar_Domain $domain): bool
    {
        $this->getLog()->debug('Modifying nameservers: ' . $domain->getName());
        $this->getLog()->debug('Ns1: ' . $domain->getNs1());
        $this->getLog()->debug('Ns2: ' . $domain->getNs2());
        $this->getLog()->debug('Ns3: ' . $domain->getNs3());
        $this->getLog()->debug('Ns4: ' . $domain->getNs4());

        return true;
    }

    public function transferDomain(Registrar_Domain $domain): bool
    {
        $this->getLog()->debug('Transfering domain: ' . $domain->getName());
        $this->getLog()->debug('Epp code: ' . $domain->getEpp());

        return true;
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Getting domain details: ' . $domain->getName());

        if (!$domain->getRegistrationTime()) {
            $domain->setRegistrationTime(time());
        }
        if (!$domain->getExpirationTime()) {
            $years = $domain->getRegistrationPeriod();
            $domain->setExpirationTime(strtotime("+$years year"));
        }

        return $domain;
    }

    public function deleteDomain(Registrar_Domain $domain): bool
    {
        $this->getLog()->debug('Removing domain: ' . $domain->getName());

        return true;
    }

    public function registerDomain(Registrar_Domain $domain): bool
    {
        $this->getLog()->debug('Registering domain: ' . $domain->getName() . ' for ' . $domain->getRegistrationPeriod() . ' years');

        return true;
    }

    public function renewDomain(Registrar_Domain $domain): bool
    {
        $this->getLog()->debug('Renewing domain: ' . $domain->getName());

        return true;
    }

    public function modifyContact(Registrar_Domain $domain): bool
    {
        $this->getLog()->debug('Updating contact info: ' . $domain->getName());

        return true;
    }

    public function enablePrivacyProtection(Registrar_Domain $domain): bool
    {
        $this->getLog()->debug('Enabling Privacy protection: ' . $domain->getName());

        return true;
    }

    public function disablePrivacyProtection(Registrar_Domain $domain): bool
    {
        $this->getLog()->debug('Disabling Privacy protection: ' . $domain->getName());

        return true;
    }

    public function getEpp(Registrar_Domain $domain): string
    {
        $this->getLog()->debug('Retrieving domain transfer code: ' . $domain->getName());

        return '';
    }

    public function lock(Registrar_Domain $domain): bool
    {
        $this->getLog()->debug('Locking domain: ' . $domain->getName());

        return true;
    }

    public function unlock(Registrar_Domain $domain): bool
    {
        $this->getLog()->debug('Unlocking: ' . $domain->getName());

        return true;
    }
}
