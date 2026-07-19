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

use Box\Mod\Order\Entity\Order;
use Box\Mod\Servicedomain\Entity\ServiceDomain;
use Box\Mod\Servicedomain\Entity\Tld;
use Box\Mod\Servicedomain\Entity\TldRegistrar;
use FOSSBilling\InformationException;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    #[RequiredParams(['order_id' => 'Order ID is missing'])]
    public function update($data)
    {
        $this->checkPermissions('servicedomain', 'manage_domains');

        $s = $this->_getService($data);

        return $this->getService()->updateDomain($s, $data);
    }

    public function update_nameservers($data)
    {
        $this->checkPermissions('servicedomain', 'manage_domains');

        $s = $this->_getService($data);

        return $this->getService()->updateNameservers($s, $data);
    }

    public function update_contacts($data)
    {
        $this->checkPermissions('servicedomain', 'manage_domains');

        $s = $this->_getService($data);

        return $this->getService()->updateContacts($s, $data);
    }

    public function enable_privacy_protection($data)
    {
        $this->checkPermissions('servicedomain', 'manage_domains');

        $s = $this->_getService($data);

        return $this->getService()->enablePrivacyProtection($s);
    }

    public function disable_privacy_protection($data)
    {
        $this->checkPermissions('servicedomain', 'manage_domains');

        $s = $this->_getService($data);

        return $this->getService()->disablePrivacyProtection($s);
    }

    public function get_transfer_code($data)
    {
        $this->checkPermissions('servicedomain', 'manage_domains');

        $s = $this->_getService($data);

        return $this->getService()->getTransferCode($s);
    }

    public function lock($data)
    {
        $this->checkPermissions('servicedomain', 'manage_domains');

        $s = $this->_getService($data);

        return $this->getService()->lock($s);
    }

    public function unlock($data)
    {
        $this->checkPermissions('servicedomain', 'manage_domains');

        $s = $this->_getService($data);

        return $this->getService()->unlock($s);
    }

    public function tld_get_list($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');
        [$sql, $params] = $this->getService()->tldGetSearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $tldArr) {
            $tld = $this->getService()->tldFindOneById($tldArr['id']);
            if (!$tld instanceof Tld) {
                throw new \FOSSBilling\InformationException(sprintf('Tld #%s not found', $tldArr['id']));
            }
            $pager['list'][$key] = $this->getService()->tldToApiArray($tld, $this->identity);
        }

        return $pager;
    }

    #[RequiredParams(['tld' => 'TLD is missing'])]
    public function tld_get($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');

        $tld = $data['tld'];
        if ($tld[0] != '.') {
            $tld = '.' . $tld;
        }

        $model = $this->getService()->tldFindOneByTld($tld);
        if (!$model instanceof Tld) {
            throw new \FOSSBilling\InformationException('TLD not found');
        }

        return $this->getService()->tldToApiArray($model, $this->identity);
    }

    #[RequiredParams(['id' => 'ID is missing'])]
    public function tld_get_id($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');

        $model = $this->getService()->tldFindOneById($data['id']);
        if (!$model instanceof Tld) {
            throw new \FOSSBilling\InformationException('TLD not found');
        }

        return $this->getService()->tldToApiArray($model, $this->identity);
    }

    #[RequiredParams(['tld' => 'TLD is missing'])]
    public function tld_delete($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');

        $model = $this->getService()->tldFindOneByTld($data['tld']);

        if (!$model instanceof Tld) {
            throw new \FOSSBilling\InformationException('TLD not found');
        }
        $service_domains = $this->getService()->getDomainRepository()->findByTld($data['tld']);
        if (count($service_domains) > 0) {
            throw new \FOSSBilling\InformationException('TLD is used by :count: domains', [':count:' => count($service_domains)], 707);
        }

        return $this->getService()->tldRm($model);
    }

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

    #[RequiredParams(['tld' => 'TLD is missing'])]
    public function tld_update($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');

        $model = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$model instanceof Tld) {
            throw new \FOSSBilling\InformationException('TLD not found');
        }

        return $this->getService()->tldUpdate($model, $data);
    }

    public function registrar_get_list($data)
    {
        $this->checkPermissions('servicedomain', 'manage_registrars');
        [$sql, $params] = $this->getService()->registrarGetSearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        $registrars = $this->getService()->getTldRegistrarRepository()->findBy([], ['name' => 'ASC']);

        $registrarsArr = [];
        foreach ($registrars as $registrar) {
            $registrarsArr[] = $this->getService()->registrarToApiArray($registrar);
        }

        $pager['list'] = $registrarsArr;

        return $pager;
    }

    public function registrar_get_pairs($data)
    {
        $this->checkPermissions('servicedomain', 'manage_registrars');

        return $this->getService()->registrarGetPairs();
    }

    public function registrar_get_available($data)
    {
        $this->checkPermissions('servicedomain', 'manage_registrars');

        return $this->getService()->registrarGetAvailable();
    }

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

    #[RequiredParams(['id' => 'Registrar ID is missing'])]
    public function registrar_delete($data)
    {
        $this->checkPermissions('servicedomain', 'manage_registrars');

        $model = $this->getService()->getTldRegistrarRepository()->find($data['id']);
        if (!$model instanceof TldRegistrar) {
            throw new \FOSSBilling\InformationException('Registrar not found');
        }

        return $this->getService()->registrarRm($model);
    }

    #[RequiredParams(['id' => 'Registrar ID is missing'])]
    public function registrar_copy($data)
    {
        $this->checkPermissions('servicedomain', 'manage_registrars');

        $model = $this->getService()->getTldRegistrarRepository()->find($data['id']);
        if (!$model instanceof TldRegistrar) {
            throw new \FOSSBilling\InformationException('Registrar not found');
        }

        return $this->getService()->registrarCopy($model);
    }

    #[RequiredParams(['id' => 'Registrar ID is missing'])]
    public function registrar_get($data)
    {
        $this->checkPermissions('servicedomain', 'manage_registrars');

        $registrar = $this->getService()->getTldRegistrarRepository()->find($data['id']);
        if (!$registrar instanceof TldRegistrar) {
            throw new \FOSSBilling\InformationException('Registrar not found');
        }

        return $this->getService()->registrarToApiArray($registrar);
    }

    public function batch_sync_expiration_dates($data)
    {
        $this->checkPermissions('servicedomain', 'manage_domains');

        return $this->getService()->batchSyncExpirationDates();
    }

    #[RequiredParams(['id' => 'Registrar ID is missing'])]
    public function registrar_update($data)
    {
        $this->checkPermissions('servicedomain', 'manage_registrars');

        $model = $this->getService()->getTldRegistrarRepository()->find($data['id']);
        if (!$model instanceof TldRegistrar) {
            throw new \FOSSBilling\InformationException('Registrar not found');
        }

        return $this->getService()->registrarUpdate($model, $data);
    }

    #[RequiredParams(['order_id' => 'Order ID is missing'])]
    protected function _getService($data)
    {
        $orderId = $data['order_id'];

        $order = $this->getDi()['em']->getRepository(Order::class)->find($orderId) ?? throw new InformationException('Order not found');

        $orderService = $this->getDi()['mod_service']('order');
        $s = $orderService->getOrderService($order);

        if (!$s instanceof ServiceDomain) {
            throw new \FOSSBilling\Exception('Domain order is not activated');
        }

        return $s;
    }
}
