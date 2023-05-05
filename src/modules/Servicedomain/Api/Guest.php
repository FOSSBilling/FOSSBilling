<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc. 
 * SPDX-License-Identifier: Apache-2.0
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
    public function tlds($data = [])
    {
        $allow_register = $data['allow_register'] ?? null;
        $allow_transfer = $data['allow_transfer'] ?? null;

        $where = [];
        $where[] = 'active = 1';

        if (null !== $allow_register) {
            $where[] = 'allow_register = 1';
        }

        if (null !== $allow_transfer) {
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
     * @param string $tld - Top level domain, ie: .com
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
            throw new \Box_Exception('TLD not found');
        }

        return $this->getService()->tldToApiArray($model);
    }

    /**
     * Check if domain is available for registration. Domain registrar must be
     * configured in order to get correct results.
     *
     * @param string $sld - second level domain, ie: mydomain
     * @param string $tld - top level domain, ie: .com
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
            throw new \Box_Exception('Domain :domain is not valid', [':domain' => $sld]);
        }

        $tld = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$tld instanceof \Model_Tld) {
            throw new \Box_Exception('Domain availability could not be determined. TLD is not active.');
        }

        if (!$this->getService()->isDomainAvailable($tld, $sld)) {
            throw new \Box_Exception('Domain is not available.');
        }

        return true;
    }

    /**
     * Check if domain can be transferred. Domain registrar must be
     * configured in order to get correct results.
     *
     * @param string $sld - second level domain, ie: mydomain
     * @param string $tld - top level domain, ie: .com
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
            throw new \Box_Exception('TLD is not active.');
        }
        if (!$this->getService()->canBeTransferred($tld, $data['sld'])) {
            throw new \Box_Exception('Domain can not be transferred.');
        }

        return true;
    }
}
