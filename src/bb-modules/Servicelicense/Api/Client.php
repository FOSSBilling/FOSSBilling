<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Servicelicense\Api;
/**
 *License Service management
 */
class Client extends \Api_Abstract
{
    /**
     * Reset license validation rules.
     * 
     * @param int $order_id - License service order id
     * @return boolean 
     */
    public function reset($data)
    {
        $s = $this->_getService($data);
        return $this->getService()->reset($s);
    }

    public function _getService(array $data)
    {
        $required = array('order_id' => 'Order id is required');
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->getIdentity();

        $bindings = array(
            ':id'        => $data['order_id'],
            ':client_id' => $client->id
        );

        $order = $this->di['db']->findOne('ClientOrder', 'id = :id AND client_id = :client_id', $bindings);

        if (!$order instanceof \Model_ClientOrder) {
            throw new \Box_Exception('Order not found');
        }

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if(!$s instanceof \Model_ServiceLicense) {
            throw new \Box_Exception('Order is not activated');
        }
        
        return $s;
    }
}