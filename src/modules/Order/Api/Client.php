<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Client orders management.
 */

namespace Box\Mod\Order\Api;

use FOSSBilling\PaginationOptions;

class Client extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get list of orders.
     *
     * @return array
     */
    public function get_list($data)
    {
        $identity = $this->getIdentity();
        $data['client_id'] = $identity->id;

        if (isset($data['expiring'])) {
            [$query, $bindings] = $this->getService()->getSoonExpiringActiveOrdersQuery($data);
        } else {
            [$query, $bindings] = $this->getService()->getSearchQuery($data);
        }

        $pager = $this->getDi()['pager']->getPaginatedResultSet($query, $bindings, PaginationOptions::fromArray($data));

        $pager['list'] = $this->getService()->getBatchForApi(
            array_column($pager['list'], 'id'),
            $identity
        );

        return $pager;
    }

    /**
     * Get order details.
     *
     * @return array
     */
    public function get($data)
    {
        $model = $this->_getOrder($data);

        return $this->getService()->toApiArray($model);
    }

    /**
     * Get order addons.
     */
    public function addons($data): array
    {
        $model = $this->_getOrder($data);
        $list = $this->getService()->getOrderAddonsList($model);
        $result = [];
        foreach ($list as $order) {
            $result[] = $this->getService()->toApiArray($order);
        }

        return $result;
    }

    /**
     * Get order service. Order must be activated before service can be retrieved.
     *
     * @return array
     */
    public function service($data)
    {
        $order = $this->_getOrder($data);

        if ($order->status !== \Model_ClientOrder::STATUS_ACTIVE) {
            throw new \FOSSBilling\InformationException('Order is not active');
        }

        return $this->getService()->getOrderServiceData($order, $this->getIdentity());
    }

    /**
     * List of product pairs offered as an upgrade.
     *
     * @return array
     */
    public function upgradables($data)
    {
        $model = $this->_getOrder($data);
        $productService = $this->di['mod_service']('product');

        return $productService->getUpgradablePairsByProductId((int) $model->product_id);
    }

    /**
     * Can delete only pending setup and failed setup orders.
     */
    public function delete($data)
    {
        $model = $this->_getOrder($data);
        if (!in_array($model->status, [\Model_ClientOrder::STATUS_PENDING_SETUP, \Model_ClientOrder::STATUS_FAILED_SETUP])) {
            throw new \FOSSBilling\InformationException('Only pending and failed setup orders can be deleted.');
        }

        return $this->getService()->deleteFromOrder($model);
    }

    protected function _getOrder($data)
    {
        $required = [
            'id' => 'Order id required',
        ];
        $this->getDi()['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->getService()->findForClientById($this->getIdentity(), $data['id']);
        if (!$order instanceof \Model_ClientOrder) {
            throw new \FOSSBilling\InformationException('Order not found');
        }

        return $order;
    }
}
