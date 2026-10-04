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
use Box\Mod\Servicedomain\Event\AfterClientChangeNameserversEvent;
use Box\Mod\Servicedomain\Event\BeforeClientChangeNameserversEvent;

/**
 * Domain service management.
 */
class Client extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Change domain nameservers. Method sends action to registrar.
     *
     * @optional string $ns3 - 3 Nameserver hostname, ie: ns3.mydomain.com
     * @optional string $ns4 - 4 Nameserver hostname, ie: ns4.mydomain.com
     *
     * @return true
     */
    public function update_nameservers($data): bool
    {
        $s = $this->_getService($data);

        $nameservers = [];
        foreach (['ns1', 'ns2', 'ns3', 'ns4'] as $key) {
            $nameservers[$key] = isset($data[$key]) && is_string($data[$key]) ? $data[$key] : null;
        }

        $this->getDi()['event_dispatcher']->dispatch(new BeforeClientChangeNameserversEvent(
            $s->getId(),
            $s->getClientId(),
            $nameservers['ns1'],
            $nameservers['ns2'],
            $nameservers['ns3'],
            $nameservers['ns4'],
        ));

        $this->getService()->updateNameservers($s, $data);

        $this->getDi()['event_dispatcher']->dispatch(new AfterClientChangeNameserversEvent(
            $s->getId(),
            $s->getClientId(),
            $nameservers['ns1'],
            $nameservers['ns2'],
            $nameservers['ns3'],
            $nameservers['ns4'],
        ));

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
     * Synchronize domain registration details with the registrar.
     *
     * @return true
     */
    public function sync($data): bool
    {
        $s = $this->_getService($data);
        $this->getService()->synchronizeDomain($s);

        return true;
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
        $orderService = $this->getDi()['mod_service']('order');

        $order = $orderService->findForClientById($this->getIdentity(), $data['order_id']);
        if (!$order instanceof Order) {
            throw new \FOSSBilling\InformationException('Order not found');
        }

        $orderService->assertOrderUsable($order);

        $s = $orderService->getOrderService($order);
        if (!$s instanceof ServiceDomain || $order->getStatus() !== Order::STATUS_ACTIVE) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return $s;
    }
}
