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
class Client extends \Api_Abstract
{
    /**
     * Change domain nameservers. Method sends action to registrar.
     *
     * @optional string $ns3 - 3 Nameserver hostname, ie: ns3.mydomain.com
     * @optional string $ns4 - 4 Nameserver hostname, ie: ns4.mydomain.com
     *
     * @return true
     */
    public function update_nameservers($data)
    {
        $s = $this->_getService($data);

        $this->di['events_manager']->fire(['event' => 'onBeforeClientChangeNameservers', 'params' => $data]);

        $this->getService()->updateNameservers($s, $data);

        $this->di['events_manager']->fire(['event' => 'onAfterClientChangeNameservers', 'params' => $data]);

        return true;
    }

    /**
     * Change domain WHOIS contact details. Method sends action to registrar.
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
     * @return true
     */
    public function disable_privacy_protection($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->disablePrivacyProtection($s);
    }

    /**
     * Retrieve domain transfer code.
     *
     * @return string - transfer code
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

    protected function _getService($data)
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
        if (!$s instanceof \Model_ServiceDomain) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return $s;
    }
}
