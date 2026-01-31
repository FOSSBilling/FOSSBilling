<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\ProductType\License\Api;

class Client extends \Api_Abstract
{
    public function reset($data)
    {
        $s = $this->getServiceModel($data);

        return $this->getService()->reset($s);
    }

    public function getServiceModel(array $data)
    {
        $required = ['order_id' => 'Order ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->getIdentity();

        $bindings = [
            ':id' => $data['order_id'],
            ':client_id' => $client->id,
        ];

        $order = $this->di['db']->findOne('ClientOrder', 'id = :id AND client_id = :client_id', $bindings);

        if (!$order instanceof \Model_ClientOrder) {
            throw new \FOSSBilling\Exception('Order not found');
        }

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof \Model_ServiceLicense) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return $s;
    }
}
