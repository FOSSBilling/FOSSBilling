<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\ProductType\Download\Api;

class Client extends \Api_Abstract
{
    public function send_file($data): bool
    {
        if (!isset($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required');
        }
        $identity = $this->getIdentity();
        $order = $this->di['db']->findOne('ClientOrder', 'id = :id AND client_id = :client_id', [':id' => $data['order_id'], ':client_id' => $identity->id]);
        if (!$order instanceof \Model_ClientOrder) {
            throw new \FOSSBilling\Exception('Order not found');
        }

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof \Model_ServiceDownload || $order->status !== 'active') {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        $service = $this->getService();

        return (bool) $service->sendFile($s);
    }
}
