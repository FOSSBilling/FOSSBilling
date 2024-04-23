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
 * Orders management.
 */

namespace Box\Mod\Order\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get order details.
     *
     * @return array
     */
    public function get($data)
    {
        $deep = isset($data['deep']) ? (bool) $data['deep'] : true;
        $order = $this->_getOrder($data);

        return $this->getService()->toApiArray($order, $deep, $this->getIdentity());
    }

    /**
     * Return paginated list of orders.
     *
     * @optional string $date_from - show only order places after this date
     * @optional string $date_to - show only order places till this date
     *
     * @return array
     */
    public function get_list($data)
    {
        $orderConfig = $this->di['mod']('order')->getConfig();
        $data['hide_addons'] = (isset($orderConfig['show_addons']) && $orderConfig['show_addons']) ? 0 : 1;
        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $paginator = $this->di['pager'];
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $resultSet = $paginator->getAdvancedResultSet($sql, $params, $per_page);

        foreach ($resultSet['list'] as $key => $result) {
            $orderObj = $this->di['db']->getExistingModelById('ClientOrder', $result['id'], 'Order not found');
            $resultSet['list'][$key] = $this->getService()->toApiArray($orderObj, true, $this->getIdentity());
        }

        return $resultSet;
    }

    /**
     * Place new order for client. Admin is able to order disabled products.
     *
     * @optional array $config - Depending on product type, you may need to pass product configuration options
     * @optional int $quantity - Quantity of products to order. Default 1
     * @optional float $price - Overridden unit price in default currency. Default is product price for selected period.
     * @optional string $group_id - Order group id. Assign order to be as an addon for other order
     * @optional string $currency - Order currency. If not passed, default is used
     * @optional string $title - Order title. If not passed, product title is used
     * @optional bool $activate - activate immediately
     * @optional string $invoice_option - Options: "no-invoice", "issue-invoice"; Default: no-invoice
     * @optional string $created_at - date when order was created. Default: now
     * @optional string $updated_at - date when order was updated. Default: now
     *
     * @return array
     */
    public function create($data)
    {
        $required = [
            'client_id' => 'Client id not passed',
            'product_id' => 'Product id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');
        $product = $this->di['db']->getExistingModelById('Product', $data['product_id'], 'Product not found');

        return $this->getService()->createOrder($client, $product, $data);
    }

    /**
     * Update order settings.
     *
     * @optional string $period - Order billing period, ie: 1Y
     * @optional string $expires_at - Order expiration date, ie: 2022-12-29
     * @optional string $activated_at - Order activation date, ie: 2022-12-29
     * @optional string $title - new order title
     * @optional string $price - new order price, new invoice will be issued with this amount
     * @optional string $status - manual orders status change. Does not perform action on service
     * @optional string $reason - order status change reason
     * @optional string $notes - order notes
     * @optional array  $meta - list of meta properties
     *
     * @return bool
     */
    public function update($data)
    {
        $order = $this->_getOrder($data);

        return $this->getService()->updateOrder($order, $data);
    }

    /**
     * Activate order depending on current status.
     *
     * @optional bool $force - Skip order status checking. Force activate even active order
     *
     * @return bool
     */
    public function activate($data)
    {
        $order = $this->_getOrder($data);

        return $this->getService()->activateOrder($order, $data);
    }

    /**
     * Activate order depending on current status.
     *
     * @return bool
     */
    public function renew($data)
    {
        $order = $this->_getOrder($data);

        if ($order->status == \Model_ClientOrder::STATUS_PENDING_SETUP || $order->status == \Model_ClientOrder::STATUS_FAILED_SETUP) {
            return $this->activate($data);
        }

        return $this->getService()->renewOrder($order, $data);
    }

    /**
     * Suspend order.
     *
     * @optional string $reason - Suspension reason message
     * @optional bool $skip_event - Skip calling event hooks
     *
     * @return bool
     */
    public function suspend($data)
    {
        $order = $this->_getOrder($data);
        $skip_event = isset($data['skip_event']) && (bool) $data['skip_event'];

        $reason = $data['reason'] ?? null;

        return $this->getService()->suspendFromOrder($order, $reason, $skip_event);
    }

    /**
     * Unsuspend suspended order.
     *
     * @return bool
     */
    public function unsuspend($data)
    {
        $order = $this->_getOrder($data);
        if ($order->status != \Model_ClientOrder::STATUS_SUSPENDED) {
            throw new \FOSSBilling\InformationException('Only suspended orders can be unsuspended');
        }

        return $this->getService()->unsuspendFromOrder($order);
    }

    /**
     * Cancel order.
     *
     * @optional bool $skip_event - Skip calling event hooks
     *
     * @return bool
     */
    public function cancel($data)
    {
        $order = $this->_getOrder($data);
        $skip_event = isset($data['skip_event']) && (bool) $data['skip_event'];

        $reason = $data['reason'] ?? null;

        return $this->getService()->cancelFromOrder($order, $reason, $skip_event);
    }

    /**
     * Uncancel canceled order.
     *
     * @return bool
     */
    public function uncancel($data)
    {
        $order = $this->_getOrder($data);
        if ($order->status != \Model_ClientOrder::STATUS_CANCELED) {
            throw new \FOSSBilling\InformationException('Only canceled orders can be uncanceled');
        }

        return $this->getService()->uncancelFromOrder($order);
    }

    /**
     * Delete order.
     *
     * @optional bool $delete_addons - Remove addons also. Default false.
     *
     * @return bool
     */
    public function delete($data)
    {
        $order = $this->_getOrder($data);
        $delete_addons = isset($data['delete_addons']) && (bool) $data['delete_addons'];
        $forceDelete = (bool) ($data['force_delete'] ?? false);

        if ($delete_addons) {
            $list = $this->getService()->getOrderAddonsList($order);
            foreach ($list as $addon) {
                $this->getService()->deleteFromOrder($addon, $forceDelete);
            }
        }

        return $this->getService()->deleteFromOrder($order, $forceDelete);
    }

    /**
     * Suspend all expired orders.
     *
     * @return bool
     */
    public function batch_suspend_expired($data)
    {
        return $this->getService()->batchSuspendExpired();
    }

    /**
     * Cancel all suspended orders.
     * Configure how many days suspended order should be kept before canceling.
     *
     * @return bool
     */
    public function batch_cancel_suspended($data)
    {
        return $this->getService()->batchCancelSuspended();
    }

    /**
     * Update order config.
     *
     * @return bool
     */
    public function update_config($data)
    {
        $order = $this->_getOrder($data);

        if (!isset($data['config']) || !is_array($data['config'])) {
            throw new \FOSSBilling\Exception('Order config not passed');
        }

        $config = $data['config'];

        return $this->getService()->updateOrderConfig($order, $config);
    }

    /**
     * Get order service data.
     *
     * @return array
     */
    public function service($data)
    {
        $order = $this->_getOrder($data);

        return $this->getService()->getOrderServiceData($order, $this->getIdentity());
    }

    /**
     * Get paginated order statuses history list.
     *
     * @return array
     */
    public function status_history_get_list($data)
    {
        $order = $this->_getOrder($data);

        $data['client_order_id'] = $order->id;

        [$sql, $bindings] = $this->getService()->getOrderStatusSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();

        return $this->di['pager']->getSimpleResultSet($sql, $bindings, $per_page);
    }

    /**
     * Add order status history change.
     *
     * @return array
     */
    public function status_history_add($data)
    {
        $order = $this->_getOrder($data);

        $required = [
            'status' => 'Order status was not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $notes = $data['notes'] ?? null;

        return $this->getService()->orderStatusAdd($order, $data['status'], $notes);
    }

    /**
     * Remove order status history item.
     *
     * @return bool
     */
    public function status_history_delete($data)
    {
        $required = [
            'id' => 'Order history line id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->orderStatusRm($data['id']);
    }

    /**
     * Return order statuses codes with counter.
     *
     * @return array
     */
    public function get_statuses()
    {
        return $this->getService()->counter();
    }

    /**
     * Return available invoice options.
     *
     * @return array
     */
    public function get_invoice_options($data)
    {
        return [
            'issue-invoice' => __trans('Automatically issue renewal invoices'),
            'no-invoice' => __trans('Issue invoices manually'),
        ];
    }

    /**
     * Return order statuses codes with titles.
     *
     * @return array
     */
    public function get_status_pairs($data)
    {
        return [
            \Model_ClientOrder::STATUS_PENDING_SETUP => 'Pending setup',
            \Model_ClientOrder::STATUS_FAILED_SETUP => 'Setup failed',
            \Model_ClientOrder::STATUS_ACTIVE => 'Active',
            \Model_ClientOrder::STATUS_SUSPENDED => 'Suspended',
            \Model_ClientOrder::STATUS_CANCELED => 'Canceled',
        ];
    }

    /**
     * Return order addons list.
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

    protected function _getOrder($data)
    {
        $required = [
            'id' => 'Order id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->di['db']->getExistingModelById('ClientOrder', $data['id'], 'Order not found');
    }

    /**
     * Deletes orders with given IDs.
     *
     * @optional bool $delete_addons - Remove addons also. Default false.
     *
     * @return bool
     */
    public function batch_delete($data)
    {
        $required = [
            'ids' => 'Orders ids not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $delete_addons = isset($data['delete_addons']) && (bool) $data['delete_addons'];

        foreach ($data['ids'] as $id) {
            $this->delete(['id' => $id, 'delete_addons' => $delete_addons]);
        }

        return true;
    }

    public function export_csv($data)
    {
        $data['headers'] ??= [];

        return $this->getService()->exportCSV($data['headers']);
    }
}
