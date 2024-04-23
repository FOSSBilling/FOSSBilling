<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Client orders management.
 */

namespace Box\Mod\Order\Api;

class Client extends \Api_Abstract
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
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getAdvancedResultSet($query, $bindings, $per_page);

        foreach ($pager['list'] as $key => $item) {
            $order = $this->di['db']->getExistingModelById('ClientOrder', $item['id'], 'Client order not found');
            $pager['list'][$key] = $this->getService()->toApiArray($order);
        }

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

        return $this->getService()->getOrderServiceData($order, $data['id'], $this->getIdentity());
    }

    /**
     * List of product pairs offered as an upgrade.
     *
     * @return array
     */
    public function upgradables($data)
    {
        $model = $this->_getOrder($data);
        $product = $this->di['db']->getExistingModelById('Product', $model->product_id);
        $productService = $this->di['mod_service']('product');

        return $productService->getUpgradablePairs($product);
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
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->getService()->findForClientById($this->getIdentity(), $data['id']);
        if (!$order instanceof \Model_ClientOrder) {
            throw new \FOSSBilling\Exception('Order not found');
        }

        return $order;
    }
}
