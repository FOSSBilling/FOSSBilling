<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


namespace Box\Mod\Servicedomain\Api;

/**
 * Domain order management
 */
class Admin extends \Api_Abstract
{
    /**
     * Update domain service.
     * Does not send actions to domain registar. Used to sync domain details
     * on BoxBilling
     *
     * @param int $order_id - domain order id
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
     * @return boolean
     */
    public function update($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->updateDomain($s, $data);
    }

    /**
     * Update domain nameservers
     *
     * @param int $order_id - domain order id
     * @param string $ns1 - 1 Nameserver hostname, ie: ns1.mydomain.com
     * @param string $ns2 - 2 Nameserver hostname, ie: ns2.mydomain.com
     *
     * @optional string $ns3 - 3 Nameserver hostname, ie: ns3.mydomain.com
     * @optional string $ns4 - 4 Nameserver hostname, ie: ns4.mydomain.com
     *
     * @return boolean
     */
    public function update_nameservers($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->updateNameservers($s, $data);
    }

    /**
     * Update domain contact details
     *
     * @param int $order_id - domain order id
     * @param array $contact - Contact array must contain these fields: first_name, last_name, email, company, address1, address2, country, city, state, postcode, phone_cc, phone
     *
     * @return boolean
     */
    public function update_contacts($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->updateContacts($s, $data);
    }

    /**
     * Enable domain privacy protection
     *
     * @param int $order_id - domain order id
     *
     * @return boolean
     */
    public function enable_privacy_protection($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->enablePrivacyProtection($s);
    }

    /**
     * Disable domain privacy protection
     *
     * @param int $order_id - domain order id
     *
     * @return boolean
     */
    public function disable_privacy_protection($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->disablePrivacyProtection($s);
    }

    /**
     * Get domain transfer code
     *
     * @param int $order_id - domain order id
     *
     * @return boolean
     */
    public function get_transfer_code($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->getTransferCode($s);
    }

    /**
     * Lock domain
     *
     * @param int $order_id - domain order id
     *
     * @return boolean
     */
    public function lock($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->lock($s);;
    }

    /**
     * Unlock domain
     *
     * @param int $order_id - domain order id
     *
     * @return boolean
     */
    public function unlock($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->unlock($s);;
    }

    /**
     * Get paginated top level domains list
     * @return array
     */
    public function tld_get_list($data)
    {
        list($sql, $params) = $this->getService()->tldGetSearchQuery($data);
        $per_page = isset($data['per_page']) ? $data['per_page'] : $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach($pager['list'] as $key => $tldArr){
            $tld = $this->di['db']->getExistingModelById('Tld', $tldArr['id'], sprintf('Tld #%s not found', $tldArr['id']));
            $pager['list'][$key] = $this->getService()->tldToApiArray($tld);
        }

        return $pager;
    }

    /**
     * Get top level domain details
     *
     * @param string $tld - top level domain, ie: .com
     *
     * @return array
     * @throws Box_Exception
     */
    public function tld_get($data)
    {
        if (!isset($data['tld'])) {
            throw new \Box_Exception('TLD is missing');
        }

        $tld = $data['tld'];
        if ($tld[0] != '.') {
            $tld = '.' . $tld;
        }

        $model = $this->getService()->tldFindOneByTld($tld);
        if (!$model instanceof \Model_Tld) {
            throw new \Box_Exception('TLD not found');
        }

        return $this->getService()->tldToApiArray($model);
    }

    /**
     * Delete top level domain
     *
     * @param string $tld - top level domain, ie: .com
     *
     * @return bool
     * @throws Box_Exception
     */
    public function tld_delete($data)
    {
        if (!isset($data['tld'])) {
            throw new \Box_Exception('TLD is missing');
        }

        $model = $this->getService()->tldFindOneByTld($data['tld']);

        if (!$model instanceof \Model_Tld) {
            throw new \Box_Exception('TLD not found');
        }

        return $this->getService()->tldRm($model);
    }

    /**
     * Add new top level domain
     *
     * @param string $tld - top level domain, ie: .com
     * @param int $tld_registrar_id - domain registrar id
     * @param float $price_registration - registration price
     * @param float $price_renew - renewal price
     * @param float $price_transfer - transfer price
     *
     * @return bool
     * @throws Box_Exception
     */
    public function tld_create($data)
    {
        if (!isset($data['tld'])) {
            throw new \Box_Exception('TLD is missing');
        }

        if ($this->getService()->tldAlreadyRegistered($data['tld'])) {
            throw new \Box_Exception('Tld already registered');
        }

        if (!isset($data['tld_registrar_id'])) {
            throw new \Box_Exception('TLD registrar id is missing');
        }

        if (!isset($data['price_registration'])) {
            throw new \Box_Exception('Registration price is missing');
        }

        if (!isset($data['price_renew'])) {
            throw new \Box_Exception('Renewal price is missing');
        }

        if (!isset($data['price_transfer'])) {
            throw new \Box_Exception('Transfer price is missing');
        }

        return $this->getService()->tldCreate($data);
    }

    /**
     * Update top level domain
     *
     * @param string $tld - top level domain, ie: .com
     *
     * @optional int $tld_registrar_id - domain registrar id
     * @optional float $price_registration - registration price
     * @optional float $price_renew - renewal price
     * @optional float $price_transfer - transfer price
     *
     * @return bool
     * @throws Box_Exception
     */
    public function tld_update($data)
    {
        if (!isset($data['tld'])) {
            throw new \Box_Exception('TLD is missing');
        }

        $model = $this->getService()->tldFindOneByTld($data['tld']);
        if (!$model instanceof \Model_Tld) {
            throw new \Box_Exception('TLD not found');
        }

        return $this->getService()->tldUpdate($model, $data);
    }

    /**
     * Get paginated registars list
     *
     * @return array
     */
    public function registrar_get_list($data)
    {
        list($sql, $params) = $this->getService()->registrarGetSearchQuery($data);
        $per_page = isset($data['per_page']) ? $data['per_page'] : $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

        $registrars = $this->di['db']->find('TldRegistrar', 'ORDER By name ASC');

        $registrarsArr = array();
        foreach ($registrars as $registrar) {
            $registrarsArr[] = $this->getService()->registrarToApiArray($registrar);
        }

        $pager['list'] = $registrarsArr;

        return $pager;
    }

    /**
     * Get registrars pairs
     *
     * @return array
     */
    public function registrar_get_pairs($data)
    {
        return $this->getService()->registrarGetPairs();
    }

    /**
     * Get available registars for install
     *
     * @return type
     */
    public function registrar_get_available($data)
    {
        return $this->getService()->registrarGetAvailable();
    }

    /**
     * Install domain registrar
     *
     * @param string $code - registar code
     *
     * @return bool
     */
    public function registrar_install($data)
    {
        if (!isset($data['code'])) {
            throw new \Box_Exception('Registrar code not passed');
        }

        $code = $data['code'];
        if (!in_array($code, $this->getService()->registrarGetAvailable())) {
            throw new \Box_Exception('Registrar is not available for installation.');
        }

        return $this->getService()->registrarCreate($data['code']);
    }

    /**
     * Uninstall domain registrar
     *
     * @param int $id - registar id
     *
     * @return bool
     */
    public function registrar_delete($data)
    {
        if (!isset($data['id'])) {
            throw new \Box_Exception('Registrar id not passed');
        }

        $model = $this->di['db']->load('TldRegistrar', $data['id']);
        if (!$model instanceof \Model_TldRegistrar) {
            throw new \Box_Exception('Registrar not found');
        }

        return $this->getService()->registrarRm($model);
    }

    /**
     * Copy domain registrar
     *
     * @param int $id - registar id
     *
     * @return bool
     */
    public function registrar_copy($data)
    {
        if (!isset($data['id'])) {
            throw new \Box_Exception('Registrar id not passed');
        }

        $model = $this->di['db']->load('TldRegistrar', $data['id']);

        if (!$model instanceof \Model_TldRegistrar) {
            throw new \Box_Exception('Registrar not found');
        }

        return $this->getService()->registrarCopy($model);
    }

    /**
     * Get domain registrar details
     *
     * @param int $id - registar id
     *
     * @return array
     */
    public function registrar_get($data)
    {
        if (!isset($data['id'])) {
            throw new \Box_Exception('Registrar id is missing');
        }

        $registrar = $this->di['db']->load('TldRegistrar', $data['id']);

        if (!$registrar instanceof \Model_TldRegistrar) {
            throw new \Box_Exception('Registrar not found');
        }

        return $this->getService()->registrarToApiArray($registrar);
    }

    /**
     * Sync domain expiration dates with registrars.
     * This action is run once a month
     * @return bool
     */
    public function batch_sync_expiration_dates($data)
    {
        return $this->getService()->batchSyncExpirationDates();
    }

    /**
     * Update domain registrar
     *
     * @param int $id - registar id
     *
     * @optional string $title - registar title
     * @optional array $config - registar configuration array
     *
     * @return bool
     */
    public function registrar_update($data)
    {
        if (!isset($data['id'])) {
            throw new \Box_Exception('Registrar id is missing');
        }

        $model = $this->di['db']->load('TldRegistrar', $data['id']);

        if (!$model instanceof \Model_TldRegistrar) {
            throw new \Box_Exception('Registrar not found');
        }

        return $this->getService()->registrarUpdate($model, $data);
    }

    protected function _getService($data)
    {
        if (!isset($data['order_id'])){
            throw new \Box_Exception('Order id is missing');
        }

        $orderId = $data['order_id'];

        $order = $this->di['db']->load('ClientOrder', $orderId);
        if (!$order instanceof \Model_ClientOrder) {
            throw new \Box_Exception('Order not found');
        }

        $orderService = $this->di['mod_service']('order');
        $s            = $orderService->getOrderService($order);

        if (!$s instanceof \Model_ServiceDomain) {
            throw new \Box_Exception('Domain order is not activated');
        }

        return $s;
    }
}