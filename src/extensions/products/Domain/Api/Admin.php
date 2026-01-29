<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\ProductType\Domain\Api;

final class Admin extends \Api_Abstract
{
    public function update($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->updateDomain($s, $data);
    }

    public function update_nameservers($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->updateNameservers($s, $data);
    }

    public function update_contacts($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->updateContacts($s, $data);
    }

    public function enable_privacy_protection($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->enablePrivacyProtection($s);
    }

    public function disable_privacy_protection($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->disablePrivacyProtection($s);
    }

    public function get_transfer_code($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->getTransferCode($s);
    }

    public function lock($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->lock($s);
    }

    public function unlock($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->unlock($s);
    }

    public function tld_get_list($data)
    {
        [$sql, $params] = $this->getService()->tldGetSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $tldArr) {
            $tld = $this->di['db']->getExistingModelById('Tld', $tldArr['id'], sprintf('Tld #%s not found', $tldArr['id']));
            $pager['list'][$key] = $this->getService()->tldToApiArray($tld);
        }

        return $pager;
    }

    public function tld_get($data)
    {
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

    public function tld_get_id($data)
    {
        $model = $this->getService()->tldFindOneById($data['id']);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('ID not found');
        }

        return $this->getService()->tldToApiArray($model);
    }

    public function tld_delete($data)
    {
        $model = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('TLD not found');
        }
        $service_domains = $this->di['db']->find('ServiceDomain', 'tld = :tld', [':tld' => $data['tld']]);
        $count = is_countable($service_domains) ? count($service_domains) : 0;
        if ($count > 0) {
            throw new \FOSSBilling\InformationException('TLD is used by :count: domains', [':count:' => $count], 707);
        }

        return $this->getService()->tldRm($model);
    }

    public function tld_create($data)
    {
        if ($this->getService()->tldAlreadyRegistered($data['tld'])) {
            throw new \FOSSBilling\InformationException('TLD already registered');
        }

        return $this->getService()->tldCreate($data);
    }

    public function tld_update($data)
    {
        $model = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('TLD not found');
        }

        return $this->getService()->tldUpdate($model, $data);
    }

    public function registrar_get_list($data)
    {
        [$sql, $params] = $this->getService()->registrarGetSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);
        $registrars = $this->di['db']->find('TldRegistrar', 'ORDER By name ASC');
        $registrarsArr = [];
        foreach ($registrars as $registrar) {
            $registrarsArr[] = $this->getService()->registrarToApiArray($registrar);
        }
        $pager['list'] = $registrarsArr;

        return $pager;
    }

    public function registrar_get_pairs($data)
    {
        return $this->getService()->registrarGetPairs();
    }

    public function registrar_get_available($data)
    {
        return $this->getService()->registrarGetAvailable();
    }

    public function registrar_install($data)
    {
        $code = $data['code'];
        if (!in_array($code, $this->getService()->registrarGetAvailable())) {
            throw new \FOSSBilling\Exception('Registrar is not available for installation.');
        }

        return $this->getService()->registrarCreate($data['code']);
    }

    public function registrar_delete($data)
    {
        $model = $this->di['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarRm($model);
    }

    public function registrar_copy($data)
    {
        $model = $this->di['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarCopy($model);
    }

    public function registrar_get($data)
    {
        $registrar = $this->di['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarToApiArray($registrar);
    }

    public function registrar_update($data)
    {
        $model = $this->di['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarUpdate($model, $data);
    }

    public function batch_sync_expiration_dates($data)
    {
        return $this->getService()->batchSyncExpirationDates();
    }

    public function toApiArray($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->toApiArray($s, true, $this->getIdentity());
    }

    protected function _getService($data)
    {
        $orderId = $data['order_id'] ?? $data['id'] ?? null;
        if (!$orderId) {
            throw new \FOSSBilling\Exception('Order ID is missing');
        }
        $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');
        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof \Model_ServiceDomain) {
            throw new \FOSSBilling\Exception('Domain order is not activated');
        }

        return $s;
    }
}
