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
 * Domain service management
 */
class Client extends \Api_Abstract
{
    /**
     * Change domain nameservers. Method sends action to registrar.
     *
     * @param int $order_id - domain order id
     * @param string $ns1 - 1 Nameserver hostname, ie: ns1.mydomain.com
     * @param string $ns2 - 2 Nameserver hostname, ie: ns2.mydomain.com
     *
     * @optional string $ns3 - 3 Nameserver hostname, ie: ns3.mydomain.com
     * @optional string $ns4 - 4 Nameserver hostname, ie: ns4.mydomain.com
     *
     * @return true
     */
    public function update_nameservers($data)
    {
        $s = $this->_getService($data);

        $this->di['events_manager']->fire(array('event' => 'onBeforeClientChangeNameservers', 'params' => $data));

        $this->getService()->updateNameservers($s, $data);

        $this->di['events_manager']->fire(array('event' => 'onAfterClientChangeNameservers', 'params' => $data));

        return true;
    }

    /**
     * Change domain WHOIS contact details. Method sends action to registrar.
     *
     * @param int $order_id - domain order id
     * @param array $contact - Contact array must contain these fields: first_name, last_name, email, company, address1, address2, country, city, state, postcode, phone_cc, phone
     *
     * @return true
     */
    public function update_contacts($data)
    {
        $s = $this->_getService($data);

        $this->getService()->updateContacts($s, $data);

        return $this->getService()->updateContacts($s, $data);
    }

    /**
     * Enable domain privacy protection.
     *
     * @param int $order_id - domain order id
     *
     * @return true
     */
    public function enable_privacy_protection($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->enablePrivacyProtection($s);
    }

    /**
     * Disable domain privacy protection.
     *
     * @param int $order_id - domain order id
     *
     * @return true
     */
    public function disable_privacy_protection($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->disablePrivacyProtection($s);
    }

    /**
     * Retireve domain transfer code
     *
     * @param int $order_id - domain order id
     *
     * @return string - transfer code
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
     * @return bool
     */
    public function lock($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->lock($s);
    }

    /**
     * Unlock domain
     *
     * @param int $order_id - domain order id
     *
     * @return bool
     */
    public function unlock($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->unlock($s);
    }

    protected function _getService($data)
    {
        if (!isset($data['order_id'])) {
            throw new \Box_Exception('Order id is required');
        }
        $orderService = $this->di['mod_service']('order');

        $order = $orderService->findForClientById($this->getIdentity(), $data['order_id']);
        if (!$order instanceof \Model_ClientOrder) {
            throw new \Box_Exception('Order not found');
        }

        $s = $orderService->getOrderService($order);
        if (!$s instanceof \Model_ServiceDomain) {
            throw new \Box_Exception('Order is not activated');
        }

        return $s;
    }
}