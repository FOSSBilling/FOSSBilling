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
use FOSSBilling\Pagination\Options;
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
     * Synchronize domain registration details with the registrar.
     */
    #[RequiredParams(['order_id' => 'Order ID is missing'])]
    public function sync($data): bool
    {
        $this->checkPermissions('servicedomain', 'manage_domains');

        $s = $this->_getService($data);
        $this->getService()->synchronizeDomain($s);

        return true;
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
        $query = $this->getService()->tldGetSearchQuery($data);

        return $this->getDi()['pager']->paginateMappedQuery(
            $query,
            Options::fromArray($data),
            fn (Tld $tld): array => $this->getService()->tldToApiArray($tld, $this->identity),
        );
    }

    /**
     * Get top level domain details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception\InformationException
     */
    #[RequiredParams(['tld' => 'TLD is missing'])]
    public function tld_get($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');

        $model = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$model instanceof Tld) {
            throw new \FOSSBilling\Exception\InformationException('TLD not found');
        }

        return $this->getService()->tldToApiArray($model, $this->identity);
    }

    /**
     * Get top level domain details by id.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception\InformationException
     */
    #[RequiredParams(['id' => 'ID is missing'])]
    public function tld_get_id($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');

        $model = $this->getService()->tldFindOneById($data['id']);
        if (!$model instanceof Tld) {
            throw new \FOSSBilling\Exception\InformationException('TLD not found');
        }

        return $this->getService()->tldToApiArray($model, $this->identity);
    }

    /**
     * Delete top level domain.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception\InformationException
     */
    #[RequiredParams(['tld' => 'TLD is missing'])]
    public function tld_delete($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');

        $normalizedTld = $this->getService()->normalizeTld($data['tld']);
        $model = $this->getService()->tldFindOneByTld($normalizedTld);

        if (!$model instanceof Tld) {
            throw new \FOSSBilling\Exception\InformationException('TLD not found');
        }
        $service_domains = $this->getDi()['em']->getConnection()->fetchAllAssociative(
            'SELECT id FROM service_domain WHERE LOWER(TRIM(TRAILING \'.\' FROM TRIM(tld))) IN (?, ?)',
            [$normalizedTld, ltrim((string) $normalizedTld, '.')],
        );
        $count = \FOSSBilling\Tools::safeCount($service_domains);
        if ($count > 0) {
            throw new \FOSSBilling\Exception\InformationException('TLD is used by :count: domains', [':count:' => $count], 707);
        }

        return $this->getService()->tldRm($model);
    }

    /**
     * Add new top level domain.
     *
     * @optional int $min_years - minimum registration period, in years
     * @optional string $periods - comma-separated list of the exact registration periods
     *                             (in years) allowed for this TLD, e.g. "1,2,3,5,10". When
     *                             omitted, any period from min_years upwards is allowed.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception\BaseException
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
            throw new \FOSSBilling\Exception\InformationException('TLD already registered');
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
     * @optional int $min_years - minimum registration period, in years
     * @optional string $periods - comma-separated list of the exact registration periods
     *                             (in years) allowed for this TLD, e.g. "1,2,3,5,10". Pass
     *                             an empty string to clear it and allow any period from
     *                             min_years upwards again.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception\InformationException
     */
    #[RequiredParams(['tld' => 'TLD is missing'])]
    public function tld_update($data)
    {
        $this->checkPermissions('servicedomain', 'manage_tlds');

        $model = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$model instanceof Tld) {
            throw new \FOSSBilling\Exception\InformationException('TLD not found');
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
        $query = $this->getService()->registrarGetSearchQuery($data);

        return $this->getDi()['pager']->paginateMappedQuery(
            $query,
            Options::fromArray($data),
            fn (TldRegistrar $registrar): array => $this->getService()->registrarToApiArray($registrar),
        );
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
            throw new \FOSSBilling\Exception\BaseException('Registrar is not available for installation.');
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

        $model = $this->_getRegistrar((int) $data['id']);

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

        $model = $this->_getRegistrar((int) $data['id']);

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

        $registrar = $this->_getRegistrar((int) $data['id']);

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

        $model = $this->_getRegistrar((int) $data['id']);

        return $this->getService()->registrarUpdate($model, $data);
    }

    #[RequiredParams(['order_id' => 'Order ID is missing'])]
    protected function _getService($data)
    {
        $orderId = $data['order_id'];

        $order = $this->getDi()['em']->getRepository(Order::class)->find($orderId);
        if (!$order instanceof Order) {
            throw new \FOSSBilling\Exception\BaseException('Order not found');
        }

        $orderService = $this->getDi()['mod_service']('order');
        $s = $orderService->getOrderService($order);

        if (!$s instanceof ServiceDomain) {
            throw new \FOSSBilling\Exception\BaseException('Domain order is not activated');
        }

        return $s;
    }

    private function _getRegistrar(int $id): TldRegistrar
    {
        $model = $this->getDi()['em']->getRepository(TldRegistrar::class)->find($id);
        if (!$model instanceof TldRegistrar) {
            throw new \FOSSBilling\Exception\BaseException('Registrar not found');
        }

        return $model;
    }
}
