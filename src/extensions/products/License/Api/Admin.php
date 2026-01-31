<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\ProductType\License\Api;

class Admin extends \Api_Abstract
{
    public function plugin_get_pairs(array $data): array
    {
        $plugins = $this->getService()->getLicensePlugins();
        $result = [];
        foreach ($plugins as $plugin) {
            $filename = $plugin['filename'];
            $result[$filename] = $filename;
        }

        return $result;
    }

    public function update($data)
    {
        $s = $this->getServiceModel($data);

        return $this->getService()->update($s, $data);
    }

    public function reset($data)
    {
        $s = $this->getServiceModel($data);

        return $this->getService()->reset($s);
    }

    public function getServiceModel(array $data)
    {
        $required = ['order_id' => 'Order ID is required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->getExistingModelById('clientOrder', $data['order_id'], 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof \Model_ServiceLicense) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return $s;
    }
}
