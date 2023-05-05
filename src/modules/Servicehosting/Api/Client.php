<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc. 
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicehosting\Api;

/**
 * Hosting service management.
 */
class Client extends \Api_Abstract
{
    /**
     * Change hosting account username.
     *
     * @param int    $order_id - Hosting account order id
     * @param string $username - New username
     *
     * @return bool
     */
    public function change_username($data)
    {
        [$order, $s] = $this->_getService($data);

        return $this->getService()->changeAccountUsername($order, $s, $data);
    }

    /**
     * Change hosting account domain.
     *
     * @param int    $order_id         - Hosting account order id
     * @param string $password         - New second level domain name, ie: mydomain
     * @param string $password_confirm - New top level domain, ie: .com
     *
     * @return bool
     */
    public function change_domain($data)
    {
        [$order, $s] = $this->_getService($data);

        return $this->getService()->changeAccountDomain($order, $s, $data);
    }

    /**
     * Change hosting account password.
     *
     * @param int    $order_id         - Hosting account order id
     * @param string $password         - New account password
     * @param string $password_confirm - Repeat new password
     *
     * @return bool
     */
    public function change_password($data)
    {
        [$order, $s] = $this->_getService($data);

        return $this->getService()->changeAccountPassword($order, $s, $data);
    }

    /**
     * Get hosting plans pairs. Usually for select box.
     *
     * @return array
     */
    public function hp_get_pairs($data)
    {
        return $this->getService()->getHpPairs();
    }

    public function _getService($data)
    {
        if (!isset($data['order_id'])) {
            throw new \Box_Exception('Order id is required');
        }
        $identity = $this->getIdentity();
        $order = $this->di['db']->findOne('ClientOrder', 'id = ? and client_id = ?', [$data['order_id'], $identity->id]);
        if (!$order instanceof \Model_ClientOrder) {
            throw new \Box_Exception('Order not found');
        }

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof \Model_ServiceHosting) {
            throw new \Box_Exception('Order is not activated');
        }

        return [$order, $s];
    }
}
