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
 * Domain order management.
 */
class Admin extends \Api_Abstract
{
    /**
     * Update domain service.
     * Does not send actions to domain registrar. Used to sync domain details
     * on FOSSBilling.
     *
     * @optional string $ns1 - 1 Nameserver hostname, ie: ns1.mydomain.com
     * @optional string $ns2 - 2 Nameserver hostname, ie: ns2.mydomain.com
     * @optional string $ns3 - 3 Nameserver hostname, ie: ns3.mydomain.com
     * @optional string $ns4 - 4 Nameserver hostname, ie: ns4.mydomain.com
     * @optional int $period - domain registration years
     * @optional bool $privacy - flag to define if domain privacy protection is enabled/disabled
     * @optional bool $locked - flag to define if domain is locked or not
     * @optional string $transfer_code - domain EPP code
     *
     * @return bool
     */
    public function update($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->updateDomain($s, $data);
    }

    /**
     * Update domain nameservers.
     *
     * @optional string $ns3 - 3 Nameserver hostname, ie: ns3.mydomain.com
     * @optional string $ns4 - 4 Nameserver hostname, ie: ns4.mydomain.com
     *
     * @return bool
     */
    public function update_nameservers($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->updateNameservers($s, $data);
    }

    /**
     * Update domain contact details.
     *
     * @return bool
     */
    public function update_contacts($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->updateContacts($s, $data);
    }

    /**
     * Enable domain privacy protection.
     *
     * @return bool
     */
    public function enable_privacy_protection($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->enablePrivacyProtection($s);
    }

    /**
     * Disable domain privacy protection.
     *
     * @return bool
     */
    public function disable_privacy_protection($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->disablePrivacyProtection($s);
    }

    /**
     * Get domain transfer code.
     *
     * @return bool
     */
    public function get_transfer_code($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->getTransferCode($s);
    }

    /**
     * Lock domain.
     *
     * @return bool
     */
    public function lock($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->lock($s);
    }

    /**
     * Unlock domain.
     *
     * @return bool
     */
    public function unlock($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->unlock($s);
    }

    /**
     * Get paginated top level domains list.
     *
     * @return array
     */
    public function tld_get_list($data)
    {
        [$sql, $params] = $this->getService()->tldGetSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $tldArr) {
            $tld = $this->di['db']->getExistingModelById('Tld', $tldArr['id'], sprintf('Tld #%s not found', $tldArr['id']));
            $pager['list'][$key] = $this->getService()->tldToApiArray($tld);
        }

        return $pager;
    }

    /**
     * Get top level domain details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function tld_get($data)
    {
        $required = [
            'tld' => 'TLD is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $tld = $data['tld'];
        if ($tld[0] != '.') {
            $tld = '.' . $tld;
        }

        $model = $this->getService()->tldFindOneByTld($tld);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('TLD not found');
        }

        return $this->getService()->tldToApiArray($model);
    }

    /**
     * Get top level domain details by id.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function tld_get_id($data)
    {
        $required = [
            'id' => 'ID is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->getService()->tldFindOneById($data['id']);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('ID not found');
        }

        return $this->getService()->tldToApiArray($model);
    }

    /**
     * Delete top level domain.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function tld_delete($data)
    {
        $required = [
            'tld' => 'TLD is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->getService()->tldFindOneByTld($data['tld']);

        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('TLD not found');
        }
        // check if tld is used by any domain
        $service_domains = $this->di['db']->find('ServiceDomain', 'tld = :tld', [':tld' => $data['tld']]);
        $count = is_countable($service_domains) ? count($service_domains) : 0;
        if ($count > 0) {
            throw new \FOSSBilling\InformationException('TLD is used by :count: domains', [':count:' => $count], 707);
        }

        return $this->getService()->tldRm($model);
    }

    /**
     * Add new top level domain.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function tld_create($data)
    {
        $required = [
            'tld' => 'TLD is missing',
            'tld_registrar_id' => 'TLD registrar id is missing',
            'price_registration' => 'Registration price is missing',
            'price_renew' => 'Renewal price is missing',
            'price_transfer' => 'Transfer price is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if ($this->getService()->tldAlreadyRegistered($data['tld'])) {
            throw new \FOSSBilling\InformationException('TLD already registered');
        }

        return $this->getService()->tldCreate($data);
    }

    /**
     * Update top level domain.
     *
     * @optional int $tld_registrar_id - domain registrar id
     * @optional float $price_registration - registration price
     * @optional float $price_renew - renewal price
     * @optional float $price_transfer - transfer price
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function tld_update($data)
    {
        $required = [
            'tld' => 'TLD is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('TLD not found');
        }

        return $this->getService()->tldUpdate($model, $data);
    }

    /**
     * Get paginated registrars list.
     *
     * @return array
     */
    public function registrar_get_list($data)
    {
        [$sql, $params] = $this->getService()->registrarGetSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

        $registrars = $this->di['db']->find('TldRegistrar', 'ORDER By name ASC');

        $registrarsArr = [];
        foreach ($registrars as $registrar) {
            $registrarsArr[] = $this->getService()->registrarToApiArray($registrar);
        }

        $pager['list'] = $registrarsArr;

        return $pager;
    }

    /**
     * Get registrars pairs.
     *
     * @return array
     */
    public function registrar_get_pairs($data)
    {
        return $this->getService()->registrarGetPairs();
    }

    /**
     * Get available registrars for install.
     *
     * @return array
     */
    public function registrar_get_available($data)
    {
        return $this->getService()->registrarGetAvailable();
    }

    /**
     * Install domain registrar.
     *
     * @return bool
     */
    public function registrar_install($data)
    {
        $required = [
            'code' => 'registrar code is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $code = $data['code'];
        if (!in_array($code, $this->getService()->registrarGetAvailable())) {
            throw new \FOSSBilling\Exception('Registrar is not available for installation.');
        }

        return $this->getService()->registrarCreate($data['code']);
    }

    /**
     * Uninstall domain registrar.
     *
     * @return bool
     */
    public function registrar_delete($data)
    {
        $required = [
            'id' => 'Registrar ID is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarRm($model);
    }

    /**
     * Copy domain registrar.
     *
     * @return bool
     */
    public function registrar_copy($data)
    {
        $required = [
            'id' => 'Registrar ID is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarCopy($model);
    }

    /**
     * Get domain registrar details.
     *
     * @return array
     */
    public function registrar_get($data)
    {
        $required = [
            'id' => 'Registrar ID is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $registrar = $this->di['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarToApiArray($registrar);
    }

    /**
     * Sync domain expiration dates with registrars.
     * This action is run once a month.
     *
     * @return bool
     */
    public function batch_sync_expiration_dates($data)
    {
        return $this->getService()->batchSyncExpirationDates();
    }

    /**
     * Update domain registrar.
     *
     * @optional string $title - registrar title
     * @optional array $config - registrar configuration array
     *
     * @return bool
     */
    public function registrar_update($data)
    {
        $required = [
            'id' => 'Registrar ID is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarUpdate($model, $data);
    }

    protected function _getService($data)
    {
        $required = [
            'order_id' => 'Order ID is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $orderId = $data['order_id'];

        $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);

        if (!$s instanceof \Model_ServiceDomain) {
            throw new \FOSSBilling\Exception('Domain order is not activated');
        }

        return $s;
    }
}
