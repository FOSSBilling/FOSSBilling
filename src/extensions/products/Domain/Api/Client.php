<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\ProductType\Domain\Api;

class Client extends \Api_Abstract
{
    public function update_nameservers($data): bool
    {
        $s = $this->_getService($data);
        $this->di['events_manager']->fire(['event' => 'onBeforeClientChangeNameservers', 'params' => $data]);
        $this->getService()->updateNameservers($s, $data);
        $this->di['events_manager']->fire(['event' => 'onAfterClientChangeNameservers', 'params' => $data]);

        return true;
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

    public function toApiArray($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->toApiArray($s, true, $this->getIdentity());
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
