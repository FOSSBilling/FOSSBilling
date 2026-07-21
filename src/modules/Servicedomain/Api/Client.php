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

class Client extends \FOSSBilling\Api\AbstractApi
{
    public function update_nameservers($data): bool
    {
        $s = $this->_getService($data);

        $this->getDi()['events_manager']->fire(['event' => 'onBeforeClientChangeNameservers', 'params' => $data]);

        $this->getService()->updateNameservers($s, $data);

        $this->getDi()['events_manager']->fire(['event' => 'onAfterClientChangeNameservers', 'params' => $data]);

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
        if ((!$s instanceof ServiceDomain) || $order->getStatus() !== Order::STATUS_ACTIVE) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return $s;
    }
}
