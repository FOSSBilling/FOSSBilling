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

namespace Box\Mod\Servicehosting\Api;
/**
 * Hosting service management 
 */
class Client extends \Api_Abstract
{
    /**
     * Change hosting account username
     * 
     * @param int $order_id - Hosting account order id
     * @param string $username - New username
     * 
     * @return boolean 
     */
    public function change_username($data)
    {
        list($order, $s) = $this->_getService($data);
        return $this->getService()->changeAccountUsername($order, $s, $data);
    }

    /**
     * Change hosting account domain
     * 
     * @param int $order_id - Hosting account order id
     * @param string $password - New second level domain name, ie: mydomain
     * @param string $password_confirm - New top level domain, ie: .com
     * 
     * @return boolean 
     */
    public function change_domain($data)
    {
        list($order, $s) = $this->_getService($data);
        return $this->getService()->changeAccountDomain($order, $s, $data);
    }

    /**
     * Change hosting account password
     * 
     * @param int $order_id - Hosting account order id
     * @param string $password          - New account password
     * @param string $password_confirm  - Repeat new password
     * 
     * @return boolean 
     */
    public function change_password($data)
    {
        list($order, $s) = $this->_getService($data);
        return $this->getService()->changeAccountPassword($order, $s, $data);
    }

    /**
     * Get hosting plans pairs. Usually for select box
     * @return array
     */
    public function hp_get_pairs($data)
    {
        return $this->getService()->getHpPairs();
    }

    public function _getService($data)
    {
        if(!isset($data['order_id'])) {
            throw new \Box_Exception('Order id is required');
        }
        $identity = $this->getIdentity();
        $order = $this->di['db']->findOne('ClientOrder', 'id = ? and client_id = ?', array($data['order_id'], $identity->id));
        if(!$order instanceof \Model_ClientOrder ) {
            throw new \Box_Exception('Order not found');
        }

        $orderSerivce = $this->di['mod_service']('order');
        $s = $orderSerivce->getOrderService($order);
        if(!$s instanceof \Model_ServiceHosting) {
            throw new \Box_Exception('Order is not activated');
        }

        return array($order, $s);
    }
}