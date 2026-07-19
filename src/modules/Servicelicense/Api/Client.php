<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicelicense\Api;

use Box\Mod\Order\Entity\Order;
use Box\Mod\Servicelicense\Entity\ServiceLicense;

/**
 *License Service management.
 */
class Client extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Reset license validation rules.
     *
     * @return bool
     */
    public function reset($data)
    {
        $s = $this->_getService($data);

        return $this->getService()->reset($s);
    }

    public function _getService(array $data)
    {
        $required = ['order_id' => 'Order ID is required'];
        $this->getDi()['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->getIdentity();

        $bindings = [
            ':id' => $data['order_id'],
            ':client_id' => $client->id,
        ];

        $order = $this->getDi()['db']->findOne('ClientOrder', 'id = :id AND client_id = :client_id', $bindings);

        if (!$order instanceof Order) {
            throw new \FOSSBilling\InformationException('Order not found');
        }

        if ($order->status !== Order::STATUS_ACTIVE) {
            throw new \FOSSBilling\InformationException('Order is not activated');
        }

        $orderService = $this->getDi()['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof ServiceLicense) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return $s;
    }
}
