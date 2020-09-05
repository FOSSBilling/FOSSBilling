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

namespace Box\Mod\Servicedownloadable\Api;
/**
 * Downloadable service management
 */
class Client extends \Api_Abstract
{
    /**
     * Use GET to call this method. Sends file attached to order.
     * Sends file as attachment.
     * 
     * @param int $order_id - downloadable service order id
     * @return bool
     */
    public function send_file($data)
    {
        if(!isset($data['order_id'])) {
            throw new \Box_Exception('Order id is required');
        }
        $identity = $this->getIdentity();
        $order = $this->di['db']->findOne('ClientOrder', 'id = :id AND client_id = :client_id', array(':id' => $data['order_id'], ':client_id' => $identity->id));
        if(!$order instanceof \Model_ClientOrder ) {
            throw new \Box_Exception('Order not found');
        }

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if(!$s instanceof \Model_ServiceDownloadable) {
            throw new \Box_Exception('Order is not activated');
        }

        $service = $this->getService();
        return (bool) $service->sendFile($s);
    }
}