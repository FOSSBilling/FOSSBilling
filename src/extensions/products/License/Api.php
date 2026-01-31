<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\License;

use FOSSBilling\Validation\Api\RequiredRole;

class Api extends \Api_Abstract
{
    #[RequiredRole(['admin'])]
    public function admin_plugin_get_pairs(array $data): array
    {
        $plugins = $this->getService()->getLicensePlugins();
        $result = [];
        foreach ($plugins as $plugin) {
            $filename = $plugin['filename'];
            $result[$filename] = $filename;
        }

        return $result;
    }

    #[RequiredRole(['admin'])]
    public function admin_update($data)
    {
        $s = $this->getServiceModelForAdmin($data);

        return $this->getService()->update($s, $data);
    }

    #[RequiredRole(['admin'])]
    public function admin_reset($data)
    {
        $s = $this->getServiceModelForAdmin($data);

        return $this->getService()->reset($s);
    }

    #[RequiredRole(['client'])]
    public function client_reset($data)
    {
        $s = $this->getServiceModelForClient($data);

        return $this->getService()->reset($s);
    }

    #[RequiredRole(['guest'])]
    public function guest_check($data)
    {
        return $this->getService()->checkLicenseDetails($data);
    }

    private function getServiceModelForAdmin(array $data)
    {
        $required = ['order_id' => 'Order ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->getExistingModelById('clientOrder', $data['order_id'], 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof \Model_ExtProductLicense) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return $s;
    }

    private function getServiceModelForClient(array $data)
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
        if (!$s instanceof \Model_ExtProductLicense) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return $s;
    }
}
