<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension\Registrar\Custom;

use Iodev\Whois\Factory;

class Custom extends \FOSSBilling\Extension\Contract\Registrar\AdapterAbstract
{
    public $config = [
        'use_whois' => false,
    ];

    public function __construct($options)
    {
        if (isset($options['use_whois'])) {
            $this->config['use_whois'] = (bool) $options['use_whois'];
        }
    }

    public static function getConfig(): array
    {
        return [
            'label' => 'Custom Registrar always responds with positive results. Useful if no other registrar is suitable.',
            'form' => [
                'use_whois' => ['radio', [
                    'multiOptions' => ['1' => 'Yes', '0' => 'No'],
                    'label' => 'Use WHOIS to Check for Domain Availability',
                ],
                ],
            ],
        ];
    }

    public function isDomaincanBeTransferred(\FOSSBilling\Extension\Contract\Registrar\Domain $domain): bool
    {
        $this->getLog()->debug('Checking if domain can be transferred: ' . $domain->getName());

        return true;
    }

    public function isDomainAvailable(\FOSSBilling\Extension\Contract\Registrar\Domain $domain)
    {
        $this->getLog()->debug('Checking domain availability: ' . $domain->getName());

        if ($this->config['use_whois']) {
            $whois = Factory::get()->createWhois();

            return $whois->isDomainAvailable($domain->getName());
        }

        return true;
    }

    public function modifyNs(\FOSSBilling\Extension\Contract\Registrar\Domain $domain): bool
    {
        $this->getLog()->debug('Modifying nameservers: ' . $domain->getName());
        $this->getLog()->debug('Ns1: ' . $domain->getNs1());
        $this->getLog()->debug('Ns2: ' . $domain->getNs2());
        $this->getLog()->debug('Ns3: ' . $domain->getNs3());
        $this->getLog()->debug('Ns4: ' . $domain->getNs4());

        return true;
    }

    public function transferDomain(\FOSSBilling\Extension\Contract\Registrar\Domain $domain): bool
    {
        $this->getLog()->debug('Transfering domain: ' . $domain->getName());
        $this->getLog()->debug('Epp code: ' . $domain->getEpp());

        return true;
    }

    public function getDomainDetails(\FOSSBilling\Extension\Contract\Registrar\Domain $domain)
    {
        $this->getLog()->debug('Getting whois: ' . $domain->getName());

        if (!$domain->getRegistrationTime()) {
            $domain->setRegistrationTime(time());
        }
        if (!$domain->getExpirationTime()) {
            $years = $domain->getRegistrationPeriod();
            $domain->setExpirationTime(strtotime("+$years year"));
        }

        return $domain;
    }

    public function deleteDomain(\FOSSBilling\Extension\Contract\Registrar\Domain $domain): bool
    {
        $this->getLog()->debug('Removing domain: ' . $domain->getName());

        return true;
    }

    public function registerDomain(\FOSSBilling\Extension\Contract\Registrar\Domain $domain): bool
    {
        $this->getLog()->debug('Registering domain: ' . $domain->getName() . ' for ' . $domain->getRegistrationPeriod() . ' years');

        return true;
    }

    public function renewDomain(\FOSSBilling\Extension\Contract\Registrar\Domain $domain): bool
    {
        $this->getLog()->debug('Renewing domain: ' . $domain->getName());

        return true;
    }

    public function modifyContact(\FOSSBilling\Extension\Contract\Registrar\Domain $domain): bool
    {
        $this->getLog()->debug('Updating contact info: ' . $domain->getName());

        return true;
    }

    public function enablePrivacyProtection(\FOSSBilling\Extension\Contract\Registrar\Domain $domain): bool
    {
        $this->getLog()->debug('Enabling Privacy protection: ' . $domain->getName());

        return true;
    }

    public function disablePrivacyProtection(\FOSSBilling\Extension\Contract\Registrar\Domain $domain): bool
    {
        $this->getLog()->debug('Disabling Privacy protection: ' . $domain->getName());

        return true;
    }

    public function getEpp(\FOSSBilling\Extension\Contract\Registrar\Domain $domain): string
    {
        $this->getLog()->debug('Retrieving domain transfer code: ' . $domain->getName());

        return '';
    }

    public function lock(\FOSSBilling\Extension\Contract\Registrar\Domain $domain): bool
    {
        $this->getLog()->debug('Locking domain: ' . $domain->getName());

        return true;
    }

    public function unlock(\FOSSBilling\Extension\Contract\Registrar\Domain $domain): bool
    {
        $this->getLog()->debug('Unlocking: ' . $domain->getName());

        return true;
    }
}
