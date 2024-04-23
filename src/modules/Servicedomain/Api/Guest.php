<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedomain\Api;

/**
 * Domain service management.
 */
class Guest extends \Api_Abstract
{
    /**
     * Get configured TLDs which can be ordered. Shows only enabled TLDs.
     *
     * @optional bool $allow_register - shows only these TLDs which can be registered
     * @optional bool $allow_transfer - shows only these TLDs which can be transferred
     *
     * @return array - list of TLDs
     */
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

    /**
     * Get TLD pricing information.
     *
     * @return array
     */
    public function pricing($data)
    {
        $required = [
            'tld' => 'TLD is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('TLD not found');
        }

        return $this->getService()->tldToApiArray($model);
    }

    /**
     * Check if domain is available for registration. Domain registrar must be
     * configured in order to get correct results.
     *
     * @return true
     */
    public function check($data)
    {
        $required = [
            'tld' => 'TLD is missing',
            'sld' => 'SLD is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $sld = htmlspecialchars($data['sld'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
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

    /**
     * Check if domain can be transferred. Domain registrar must be
     * configured in order to get correct results.
     *
     * @return true
     */
    public function can_be_transferred($data)
    {
        $required = [
            'tld' => 'TLD is missing',
            'sld' => 'SLD is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

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
