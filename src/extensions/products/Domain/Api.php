<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Domain;

use FOSSBilling\Validation\Api\RequiredParams;
use FOSSBilling\Validation\Api\RequiredRole;

class Api extends \Api_Abstract
{
    #[RequiredRole(['admin'])]
    public function admin_update($data)
    {
        $s = $this->getServiceForAdmin($data);

        return $this->getService()->updateDomain($s, $data);
    }

    #[RequiredRole(['admin'])]
    public function admin_update_nameservers($data)
    {
        $s = $this->getServiceForAdmin($data);

        return $this->getService()->updateNameservers($s, $data);
    }

    #[RequiredRole(['admin'])]
    public function admin_update_contacts($data)
    {
        $s = $this->getServiceForAdmin($data);

        return $this->getService()->updateContacts($s, $data);
    }

    #[RequiredRole(['admin'])]
    public function admin_enable_privacy_protection($data)
    {
        $s = $this->getServiceForAdmin($data);

        return $this->getService()->enablePrivacyProtection($s);
    }

    #[RequiredRole(['admin'])]
    public function admin_disable_privacy_protection($data)
    {
        $s = $this->getServiceForAdmin($data);

        return $this->getService()->disablePrivacyProtection($s);
    }

    #[RequiredRole(['admin'])]
    public function admin_get_transfer_code($data)
    {
        $s = $this->getServiceForAdmin($data);

        return $this->getService()->getTransferCode($s);
    }

    #[RequiredRole(['admin'])]
    public function admin_lock($data)
    {
        $s = $this->getServiceForAdmin($data);

        return $this->getService()->lock($s);
    }

    #[RequiredRole(['admin'])]
    public function admin_unlock($data)
    {
        $s = $this->getServiceForAdmin($data);

        return $this->getService()->unlock($s);
    }

    #[RequiredRole(['admin'])]
    public function admin_tld_get_list($data)
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

    #[RequiredRole(['admin'])]
    public function admin_tld_get($data)
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

    #[RequiredRole(['admin'])]
    public function admin_tld_get_id($data)
    {
        $model = $this->getService()->tldFindOneById($data['id']);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('ID not found');
        }

        return $this->getService()->tldToApiArray($model);
    }

    #[RequiredRole(['admin'])]
    public function admin_tld_delete($data)
    {
        $model = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('TLD not found');
        }
        $service_domains = $this->di['db']->find('ExtProductDomain', 'tld = :tld', [':tld' => $data['tld']]);
        $count = is_countable($service_domains) ? count($service_domains) : 0;
        if ($count > 0) {
            throw new \FOSSBilling\InformationException('TLD is used by :count: domains', [':count:' => $count], 707);
        }

        return $this->getService()->tldRm($model);
    }

    #[RequiredRole(['admin'])]
    public function admin_tld_create($data)
    {
        if ($this->getService()->tldAlreadyRegistered($data['tld'])) {
            throw new \FOSSBilling\InformationException('TLD already registered');
        }

        return $this->getService()->tldCreate($data);
    }

    #[RequiredRole(['admin'])]
    public function admin_tld_update($data)
    {
        $model = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('TLD not found');
        }

        return $this->getService()->tldUpdate($model, $data);
    }

    #[RequiredRole(['admin'])]
    public function admin_registrar_get_list($data)
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

    #[RequiredRole(['admin'])]
    public function admin_registrar_get_pairs($data)
    {
        return $this->getService()->registrarGetPairs();
    }

    #[RequiredRole(['admin'])]
    public function admin_registrar_get_available($data)
    {
        return $this->getService()->registrarGetAvailable();
    }

    #[RequiredRole(['admin'])]
    public function admin_registrar_install($data)
    {
        $code = $data['code'];
        if (!in_array($code, $this->getService()->registrarGetAvailable())) {
            throw new \FOSSBilling\Exception('Registrar is not available for installation.');
        }

        return $this->getService()->registrarCreate($data['code']);
    }

    #[RequiredRole(['admin'])]
    public function admin_registrar_delete($data)
    {
        $required = ['id' => 'Registrar ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarRm($model);
    }

    #[RequiredRole(['admin'])]
    public function admin_registrar_copy($data)
    {
        $required = ['id' => 'Registrar ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarCopy($model);
    }

    #[RequiredRole(['admin'])]
    public function admin_registrar_get($data)
    {
        $required = ['id' => 'Registrar ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $registrar = $this->di['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarToApiArray($registrar);
    }

    #[RequiredRole(['admin'])]
    public function admin_registrar_update($data)
    {
        $required = ['id' => 'Registrar ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('TldRegistrar', $data['id'], 'Registrar not found');

        return $this->getService()->registrarUpdate($model, $data);
    }

    #[RequiredRole(['admin'])]
    public function admin_batch_sync_expiration_dates($data)
    {
        return $this->getService()->batchSyncExpirationDates();
    }

    #[RequiredRole(['admin'])]
    public function admin_toApiArray($data)
    {
        $s = $this->getServiceForAdmin($data);

        return $this->getService()->toApiArray($s, true, $this->getIdentity());
    }

    #[RequiredRole(['client'])]
    public function client_update_nameservers($data): bool
    {
        $s = $this->getServiceForClient($data);
        $this->di['events_manager']->fire(['event' => 'onBeforeClientChangeNameservers', 'params' => $data]);
        $this->getService()->updateNameservers($s, $data);
        $this->di['events_manager']->fire(['event' => 'onAfterClientChangeNameservers', 'params' => $data]);

        return true;
    }

    #[RequiredRole(['client'])]
    public function client_update_contacts($data)
    {
        $s = $this->getServiceForClient($data);

        return $this->getService()->updateContacts($s, $data);
    }

    #[RequiredRole(['client'])]
    public function client_enable_privacy_protection($data)
    {
        $s = $this->getServiceForClient($data);

        return $this->getService()->enablePrivacyProtection($s);
    }

    #[RequiredRole(['client'])]
    public function client_disable_privacy_protection($data)
    {
        $s = $this->getServiceForClient($data);

        return $this->getService()->disablePrivacyProtection($s);
    }

    #[RequiredRole(['client'])]
    public function client_get_transfer_code($data)
    {
        $s = $this->getServiceForClient($data);

        return $this->getService()->getTransferCode($s);
    }

    #[RequiredRole(['client'])]
    public function client_lock($data)
    {
        $s = $this->getServiceForClient($data);

        return $this->getService()->lock($s);
    }

    #[RequiredRole(['client'])]
    public function client_unlock($data)
    {
        $s = $this->getServiceForClient($data);

        return $this->getService()->unlock($s);
    }

    #[RequiredRole(['client'])]
    public function client_toApiArray($data)
    {
        $s = $this->getServiceForClient($data);

        return $this->getService()->toApiArray($s, true, $this->getIdentity());
    }

    #[RequiredRole(['guest'])]
    public function guest_tlds($data = []): array
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

    #[RequiredRole(['guest'])]
    #[RequiredParams(['tld' => 'TLD is missing'])]
    public function guest_pricing($data)
    {
        $model = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$model instanceof \Model_Tld) {
            throw new \FOSSBilling\Exception('TLD not found');
        }

        return $this->getService()->tldToApiArray($model);
    }

    #[RequiredRole(['guest'])]
    #[RequiredParams(['tld' => 'TLD is missing', 'sld' => 'SLD is missing'])]
    public function guest_check($data): bool
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

    #[RequiredRole(['guest'])]
    #[RequiredParams(['tld' => 'TLD is missing', 'sld' => 'SLD is missing'])]
    public function guest_can_be_transferred($data): bool
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

    private function getServiceForAdmin($data)
    {
        $orderId = $data['order_id'] ?? $data['id'] ?? null;
        if (!$orderId) {
            throw new \FOSSBilling\Exception('Order ID is missing');
        }
        $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');
        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof \Model_ExtProductDomain) {
            throw new \FOSSBilling\Exception('Domain order is not activated');
        }

        return $s;
    }

    private function getServiceForClient($data)
    {
        if (!isset($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required');
        }
        $orderService = $this->di['mod_service']('order');
        $order = $orderService->findForClientById($this->getIdentity(), $data['order_id']);
        if (!$order instanceof \Model_ClientOrder) {
            throw new \FOSSBilling\Exception('Order not found');
        }
        $s = $orderService->getOrderService($order);
        if (!$s instanceof \Model_ExtProductDomain) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return $s;
    }
}
