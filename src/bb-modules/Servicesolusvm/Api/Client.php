<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Servicesolusvm\Api;

/**
 * Solusvm service management.
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
                 AND service_type = 'solusvm'
                ",
                [':id' => $data['order_id'], ':cid' => $this->getIdentity()->id]);

        if (!$order) {
            throw new \Box_Exception('Solusvm order not found');
        }

        $s = $this->di['db']->findOne('service_solusvm',
                'id=:id AND client_id = :cid',
                [':id' => $order->service_id, ':cid' => $this->getIdentity()->id]);
        if (!$s) {
            throw new \Box_Exception('Order is not activated');
        }

        return [$order, $s];
    }

    /**
     * Reboot VPS.
     *
     * @param int $order_id - order id
     *
     * @return bool
     */
    public function reboot($data)
    {
        [$order, $vps] = $this->_getService($data);
        $this->getService()->reboot($order, $vps, $data);
        $this->di['logger']->info('Rebooted VPS. Order ID #%s', $order->id);

        return true;
    }

    /**
     * Boot VPS.
     *
     * @param int $order_id - order id
     *
     * @return bool
     */
    public function boot($data)
    {
        [$order, $vps] = $this->_getService($data);
        $this->getService()->boot($order, $vps, $data);
        $this->di['logger']->info('Booted VPS. Order ID #%s', $order->id);

        return true;
    }

    /**
     * Shutdown VPS.
     *
     * @param int $order_id - order id
     *
     * @return bool
     */
    public function shutdown($data)
    {
        [$order, $vps] = $this->_getService($data);
        $this->getService()->shutdown($order, $vps, $data);
        $this->di['logger']->info('Shut down VPS. Order ID #%s', $order->id);

        return true;
    }

    /**
     * Get status VPS.
     *
     * @param int $order_id - order id
     *
     * @return online|offline
     */
    public function status($data)
    {
        [$order, $vps] = $this->_getService($data);

        return $this->getService()->status($order, $vps, $data);
    }

    /**
     * Retrieve more information about vps from sulusvm server.
     *
     * @param int $order_id - order id
     *
     * @return array
     */
    public function info($data)
    {
        [, $vps] = $this->_getService($data);
        try {
            $result = $this->getService()->info($vps->vserverid);
        } catch (\Exception $exc) {
            error_log($exc);
            $result = [];
        }

        return $result;
    }

    /**
     * Change root password for VPS.
     *
     * @param int    $order_id - order id
     * @param string $password - new password
     *
     * @return bool
     */
    public function set_root_password($data)
    {
        [$order, $vps] = $this->_getService($data);
        $this->getService()->set_root_password($order, $vps, $data);
        $this->di['logger']->info('Changed VPS root password. Order ID #%s', $order->id);

        return true;
    }

    /**
     * Change hostname for VPS.
     *
     * @param int $order_id - order id
     *
     * @return bool
     */
    public function set_hostname($data)
    {
        [$order, $vps] = $this->_getService($data);
        $this->getService()->set_hostname($order, $vps, $data);
        $this->di['logger']->info('Changed VPS hostname. Order ID #%s', $order->id);

        return true;
    }

    /**
     * Change client area password for solusvm user.
     *
     * @param int    $order_id - order id
     * @param string $password - new password
     *
     * @return bool
     */
    public function change_password($data)
    {
        [$order, $vps] = $this->_getService($data);
        $this->getService()->client_change_password($order, $vps, $data);
        $this->di['logger']->info('Changed SolusVM client area password. Order ID #%s', $order->id);

        return true;
    }

    /**
     * Rebuild vps operating system with new template.
     *
     * @param int    $order_id - order id
     * @param string $template - template idetification
     *
     * @return bool
     */
    public function rebuild($data)
    {
        [$order, $vps] = $this->_getService($data);
        $this->getService()->rebuild($order, $vps, $data);
        $this->di['logger']->info('Changed VPS template. Order ID #%s', $order->id);

        return true;
    }
}
