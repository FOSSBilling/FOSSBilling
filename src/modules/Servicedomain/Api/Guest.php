<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedomain\Api;

use Box\Mod\Servicedomain\Entity\Tld;
use FOSSBilling\Validation\Api\RequiredParams;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    public function tlds($data = []): array
    {
        $allow_register = $data['allow_register'] ?? null;
        $allow_transfer = $data['allow_transfer'] ?? null;

        $criteria = ['active' => true];

        if ($allow_register !== null) {
            $criteria['allowRegister'] = true;
        }

        if ($allow_transfer !== null) {
            $criteria['allowTransfer'] = true;
        }

        $tlds = $this->getService()->getTldRepository()->findBy($criteria, ['id' => 'ASC']);
        $result = [];
        foreach ($tlds as $model) {
            $result[] = $this->getService()->tldToApiArray($model);
        }

        return $result;
    }

    #[RequiredParams(['tld' => 'TLD is missing'])]
    public function pricing($data)
    {
        $model = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$model instanceof Tld) {
            throw new \FOSSBilling\InformationException('TLD not found');
        }

        return $this->getService()->tldToApiArray($model);
    }

    #[RequiredParams([
        'tld' => 'TLD is missing',
        'sld' => 'SLD is missing',
    ])]
    public function check($data): bool
    {
        $this->getDi()['rate_limiter']->consumeOrThrow('domain_lookup_ip', (string) $this->getIp());

        $sld = htmlspecialchars((string) $data['sld'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $validator = $this->getDi()['validator'];
        if (!$validator->isSldValid($sld)) {
            throw new \FOSSBilling\InformationException('Domain :domain is invalid', [':domain' => $sld]);
        }

        $tld = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$tld instanceof Tld) {
            throw new \FOSSBilling\InformationException('Domain availability could not be determined. TLD is not active.');
        }

        if (!$this->getService()->isDomainAvailable($tld, $sld)) {
            throw new \FOSSBilling\InformationException('Domain is not available.');
        }

        return true;
    }

    #[RequiredParams([
        'tld' => 'TLD is missing',
        'sld' => 'SLD is missing',
    ])]
    public function can_be_transferred($data): bool
    {
        $this->getDi()['rate_limiter']->consumeOrThrow('domain_lookup_ip', (string) $this->getIp());

        $tld = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$tld instanceof Tld) {
            throw new \FOSSBilling\InformationException('TLD is not active.');
        }
        if (!$this->getService()->canBeTransferred($tld, $data['sld'])) {
            throw new \FOSSBilling\InformationException('Domain cannot be transferred.');
        }

        return true;
    }
}
