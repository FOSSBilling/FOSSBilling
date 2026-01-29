<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\ProductType\Custom\Api;

final class Admin extends \Api_Abstract
{
    public function update($data): bool
    {
        if (!isset($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required');
        }

        if (isset($data['config']) && is_array($data['config'])) {
            $this->getService()->updateConfig($data['order_id'], $data['config']);
        }

        return true;
    }

    public function __call($name, $arguments)
    {
        if (!isset($arguments[0])) {
            throw new \FOSSBilling\Exception('API call is missing arguments', null, 7103);
        }

        $data = $arguments[0];

        if (!isset($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required');
        }
        $model = $this->getService()->getServiceCustomByOrderId($data['order_id']);

        return $this->getService()->customCall($model, $name, $data);
    }
}
