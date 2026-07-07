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

use FOSSBilling\PaginationOptions;
use FOSSBilling\Validation\Api\RequiredParams;

/**
 * Domain order management.
 */
class Admin extends \FOSSBilling\Api\AbstractApi
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
    #[RequiredParams(['order_id' => 'Order ID is missing'])]
    public function update($data)
    {
        $this->checkPermissions('servicedomain', 'manage_domains');

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
        $this->checkPermissions('servicedomain', 'manage_domains');

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
        $this->checkPermissions('servicedomain', 'manage_domains');

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
        $this->checkPermissions('servicedomain', 'manage_domains');

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
        $this->checkPermissions('servicedomain', 'manage_domains');

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
        $this->checkPermissions('servicedomain', 'manage_domains');

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
        $this->checkPermissions('servicedomain', 'manage_domains');

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
        $this->checkPermissions('servicedomain', 'manage_domains');

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
        $this->checkPermissions('servicedomain', 'manage_tlds');
        [$sql, $params] = $this->getService()->tldGetSearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $tldArr) {
            $tld = $this->getDi()['db']->getExistingModelById('Tld', $tldArr['id'], sprintf('Tld #%s not found', $tldArr['id']));
            $pager['list'][$key] = $this->getService()->tldToApiArray($tld, $this->identity);
        }

        return $pager;
    }

    /**
     * Get top level domain details.
     *
     * @return array
     *
     * @throws \FOSSBilling\InformationException
     */
    #[RequiredParams(['tld' => 'TLD is missing'])]
    public function tld_get($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');

        $tld = $data['tld'];
        if ($tld[0] != '.') {
            $tld = '.' . $tld;
        }

        $model = $this->getService()->tldFindOneByTld($tld);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\InformationException('TLD not found');
        }

        return $this->getService()->tldToApiArray($model, $this->identity);
    }

    /**
     * Get top level domain details by id.
     *
     * @return array
     *
     * @throws \FOSSBilling\InformationException
     */
    #[RequiredParams(['id' => 'ID is missing'])]
    public function tld_get_id($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');

        $model = $this->getService()->tldFindOneById($data['id']);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\InformationException('TLD not found');
        }

        return $this->getService()->tldToApiArray($model, $this->identity);
    }

    /**
     * Delete top level domain.
     *
     * @return bool
     *
     * @throws \FOSSBilling\InformationException
     */
    #[RequiredParams(['tld' => 'TLD is missing'])]
    public function tld_delete($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');

        $model = $this->getService()->tldFindOneByTld($data['tld']);

        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\InformationException('TLD not found');
        }
        $service_domains = $this->getDi()['db']->find('ServiceDomain', 'tld = :tld', [':tld' => $data['tld']]);
        $count = \FOSSBilling\Tools::safeCount($service_domains);
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
    #[RequiredParams([
        'tld' => 'TLD is missing',
        'tld_registrar_id' => 'TLD registrar ID is missing',
        'price_registration' => 'Registration price is missing',
        'price_renew' => 'Renewal price is missing',
        'price_transfer' => 'Transfer price is missing',
    ])]
    public function tld_create($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');

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
     * @throws \FOSSBilling\InformationException
     */
    #[RequiredParams(['tld' => 'TLD is missing'])]
    public function tld_update($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');

        $model = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\InformationException('TLD not found');
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
        $this->checkPermissions('servicedomain', 'manage_registrars');
        [$sql, $params] = $this->getService()->registrarGetSearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        $registrars = $this->getDi()['db']->find('TldRegistrar', 'ORDER By name ASC');

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
        $this->checkPermissions('servicedomain', 'manage_registrars');

        return $this->getService()->registrarGetPairs();
    }

    /**
     * Get available registrars for install.
     *
     * @return array
     */
    public function registrar_get_available($data)
    {
        $this->checkPermissions('servicedomain', 'manage_registrars');

        return $this->getService()->registrarGetAvailable();
    }

    /**
     * Install domain registrar.
     *
     * @return bool
     */
    #[RequiredParams(['code' => 'Registrar code is missing'])]
    public function registrar_install($data)
    {
        $this->checkPermissions('servicedomain', 'manage_registrars');

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
    #[RequiredParams(['id' => 'Registrar ID is missing'])]
    public function registrar_delete($data)
    {
        $this->checkPermissions('servicedomain', 'manage_registrars');

        $model = $this->getDi()['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarRm($model);
    }

    /**
     * Copy domain registrar.
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Registrar ID is missing'])]
    public function registrar_copy($data)
    {
        $this->checkPermissions('servicedomain', 'manage_registrars');

        $model = $this->getDi()['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarCopy($model);
    }

    /**
     * Get domain registrar details.
     *
     * @return array
     */
    #[RequiredParams(['id' => 'Registrar ID is missing'])]
    public function registrar_get($data)
    {
        $this->checkPermissions('servicedomain', 'manage_registrars');

        $registrar = $this->getDi()['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

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
        $this->checkPermissions('servicedomain', 'manage_domains');

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
    #[RequiredParams(['id' => 'Registrar ID is missing'])]
    public function registrar_update($data)
    {
        $this->checkPermissions('servicedomain', 'manage_registrars');

        $model = $this->getDi()['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarUpdate($model, $data);
    }

    #[RequiredParams(['order_id' => 'Order ID is missing'])]
    protected function _getService($data)
    {
        $orderId = $data['order_id'];

        $order = $this->getDi()['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');

        $orderService = $this->getDi()['mod_service']('order');
        $s = $orderService->getOrderService($order);

        if (!$s instanceof \Model_ServiceDomain) {
            throw new \FOSSBilling\Exception('Domain order is not activated');
        }

        return $s;
    }
}
