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

namespace Box\Mod\Servicemembership;

class Service implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function validateOrderData(array &$data)
    {
        return true;
    }

    /**
     * @return void
     */
    public function action_create(\Model_ClientOrder $order)
    {
        $model = $this->di['db']->dispense('ServiceMembership');
        $model->client_id = $order->client_id;
        $model->config = $order->config;

        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return $model;
    }

    /**
     * @return bool
     */
    public function action_activate(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_renew(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_suspend(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_unsuspend(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_cancel(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @return bool
     */
    public function action_uncancel(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @return void
     */
    public function action_delete(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);

        if ($service instanceof \Model_ServiceMembership) {
            $this->di['db']->trash($service);
        }
    }

    public function toApiArray(\Model_ServiceMembership $model, $deep = false, $identity = null)
    {
        $result = $this->di['db']->toArray($model);

        return $result;
    }
}
