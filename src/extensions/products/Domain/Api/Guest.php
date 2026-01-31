<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\ProductType\Domain\Api;

use FOSSBilling\Validation\Api\RequiredParams;

class Guest extends \Api_Abstract
{
    public function tlds($data = []): array
    {
        $allow_register = $data['allow_register'] ?? null;
        $allow_transfer = $data['allow_transfer'] ?? null;

        $where = [];
        $where[] = 'active = 1';

        if ($allow_register !== null) {
            $where[] = 'allow_register = 1';
        }

        if ($allow_transfer !== null) {
            $where[] = 'allow_transfer = 1';
        }

        $query = implode(' AND ', $where);

        $tlds = $this->di['db']->find('Tld', $query, []);
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
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('TLD not found');
        }

        return $this->getService()->tldToApiArray($model);
    }

    #[RequiredParams(['tld' => 'TLD is missing', 'sld' => 'SLD is missing'])]
    public function check($data): bool
    {
        $sld = htmlspecialchars((string) $data['sld'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $validator = $this->di['validator'];
        if (!$validator->isSldValid($sld)) {
            throw new \FOSSBilling\InformationException('Domain :domain is invalid', [':domain' => $sld]);
        }

        $tld = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$tld instanceof \Model_Tld) {
            throw new \FOSSBilling\InformationException('Domain availability could not be determined. TLD is not active.');
        }

        if (!$this->getService()->isDomainAvailable($tld, $sld)) {
            throw new \FOSSBilling\InformationException('Domain is not available.');
        }

        return true;
    }

    #[RequiredParams(['tld' => 'TLD is missing', 'sld' => 'SLD is missing'])]
    public function can_be_transferred($data): bool
    {
        $tld = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$tld instanceof \Model_Tld) {
            throw new \FOSSBilling\InformationException('TLD is not active.');
        }
        if (!$this->getService()->canBeTransferred($tld, $data['sld'])) {
            throw new \FOSSBilling\InformationException('Domain cannot be transferred.');
        }

        return true;
    }
}
