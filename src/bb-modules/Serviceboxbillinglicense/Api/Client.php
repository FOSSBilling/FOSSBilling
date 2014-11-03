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

namespace Box\Mod\Serviceboxbillinglicense\Api;

/**
 * BoxBilling license management
 */
class Client extends \Api_Abstract
{
    private function _getService($data)
    {
        if (!isset($data['order_id'])) {
            throw new \Box_Exception('Order id is required');
        }

        $order = $this->di['db']->findOne('client_order',
            "id=:id
                 AND client_id = :cid
                 AND service_type = 'boxbillinglicense'
                ",
            array(':id' => $data['order_id'], ':cid' => $this->getIdentity()->id));

        if (!$order) {
            throw new \Box_Exception('BoxBilling license order not found');
        }

        $s = $this->di['db']->findOne('service_boxbillinglicense',
            'id=:id AND client_id = :cid',
            array('id' => $order->service_id, 'cid' => $this->getIdentity()->id));
        if (!$s) {
            throw new \Box_Exception('Order is not activated');
        }

        return array($order, $s);
    }

    /**
     * Reset license information. Usually used when moving BoxBilling to
     * new server.
     *
     * @param int $order_id - order id
     * @return bool
     */
    public function reset($data)
    {
        list($order, $service) = $this->_getService($data);
        $this->getService()->reset($order, $service, $data);
        $this->di['logger']->info('Reset license information. Order ID #%s', $order->id);

        return true;
    }
}