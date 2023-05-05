<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc. 
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicecustom\Api;

/**
 * Custom product management.
 */
class Client extends \Api_Abstract
{
    /**
     * Universal method to call method from plugin
     * Pass any other params and they will be passed to plugin.
     *
     * @param int $order_id - ID of the order
     *
     * @throws \Box_Exception
     */
    public function __call($name, $arguments)
    {
        if (!isset($arguments[0])) {
            throw new \Box_Exception('API call is missing arguments', null, 7103);
        }

        $data = $arguments[0];

        if (!isset($data['order_id'])) {
            throw new \Box_Exception('Order id is required');
        }
        $model = $this->getService()->getServiceCustomByOrderId($data['order_id']);

        return $this->getService()->customCall($model, $name, $data);
    }
}
