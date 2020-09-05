<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Servicecustom\Api;

/**
 * Custom service management
 */
class Admin extends \Api_Abstract
{
    /**
     * Update custom service configuration
     *
     * @return bool
     */
    public function update($data)
    {
        if (!isset($data['order_id'])) {
            throw new \Box_Exception('Order id is required');
        }

        if (isset($data['config']) && is_array($data['config'])) {
            $this->getService()->updateConfig($data['order_id'], $data['config']);
        }

        return TRUE;
    }

    /**
     * Universal method to call method from plugin
     * Pass any other params and they will be passed to plugin
     *
     * @param int $order_id - ID of the order
     *
     * @throws Box_Exception
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