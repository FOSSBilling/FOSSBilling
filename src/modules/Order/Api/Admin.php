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
 * Orders management.
 */

namespace Box\Mod\Order\Api;

use Box\Mod\Client\Entity\Client as ClientEntity;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Repository\OrderRepository;
use FOSSBilling\InformationException;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Tools;
use FOSSBilling\Validation\Api\RequiredParams;
use Symfony\Component\HttpFoundation\Response;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    private ?OrderRepository $orderRepository = null;

    protected function getOrderRepository(): OrderRepository
    {
        if ($this->orderRepository === null) {
            $this->orderRepository = $this->getDi()['em']->getRepository(Order::class);
        }

        return $this->orderRepository;
    }

    /**
     * Get order details.
     *
     * @return array
     */
    public function get($data)
    {
        $this->checkPermissions('order', 'view');

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
        $this->checkPermissions('order', 'view');

        $orderConfig = $this->getDi()['mod']('order')->getConfig();
        $data['hide_addons'] = (isset($orderConfig['show_addons']) && $orderConfig['show_addons']) ? 0 : 1;

        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $resultSet = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        $resultSet['list'] = $this->getService()->getBatchForApi(
            array_column($resultSet['list'], 'id'),
            $this->getIdentity()
        );

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
     * @optional bool $mark_invoice_paid - Mark the generated invoice as paid after the order is created
     * @optional int $gateway_id - Payment gateway to associate with the invoice when mark_invoice_paid is used
     * @optional string $transactionId - Custom transaction ID to use when the selected gateway is Custom
     * @optional string $created_at - date when order was created. Default: now
     * @optional string $updated_at - date when order was updated. Default: now
     *
     * @return int
     */
    #[RequiredParams([
        'client_id' => 'Client ID was not passed',
        'product_id' => 'Product ID was not passed',
    ])]
    public function create($data)
    {
        $this->checkPermissions('order', 'manage');

        $markInvoicePaid = Tools::normalizeBoolean($data['mark_invoice_paid'] ?? false);
        $data['mark_invoice_paid'] = $markInvoicePaid;

        if ($markInvoicePaid) {
            $this->checkPermissions('invoice');

            if (($data['invoice_option'] ?? 'no-invoice') !== 'issue-invoice') {
                throw new InformationException('Marking an invoice as paid requires the order to issue an invoice.');
            }

            $this->getDi()['mod_service']('Invoice')->validateAdminMarkAsPaidRequest($data);
        }

        $client = $this->di['em']->getRepository(ClientEntity::class)->find($data['client_id'])
            ?? throw new InformationException('Client not found');
        $product = $this->di['mod_service']('product')->findProductById((int) $data['product_id']);

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
        $this->checkPermissions('order', 'manage');

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
        $this->checkPermissions('order', 'manage');

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
        $this->checkPermissions('order', 'manage');

        $order = $this->_getOrder($data);

        if ($order->getStatus() == Order::STATUS_PENDING_SETUP || $order->getStatus() == Order::STATUS_FAILED_SETUP) {
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
        $this->checkPermissions('order', 'manage');

        $order = $this->_getOrder($data);
        $skip_event = Tools::normalizeBoolean($data['skip_event'] ?? false);

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
        $this->checkPermissions('order', 'manage');

        $order = $this->_getOrder($data);
        if ($order->getStatus() != Order::STATUS_SUSPENDED) {
            throw new InformationException('Only suspended orders can be unsuspended');
        }

        return $this->getService()->unsuspendFromOrder($order);
    }

    /**
     * Cancel order.
     *
     * @optional bool $skip_event - Skip calling event hooks
     * @optional bool $cancel_at_period_end - Keep the order active until its gateway subscription ends
     *
     * @return bool
     */
    public function cancel($data)
    {
        $this->checkPermissions('order', 'manage');

        $order = $this->_getOrder($data);
        $skip_event = Tools::normalizeBoolean($data['skip_event'] ?? false);
        $cancelAtPeriodEnd = Tools::normalizeBoolean($data['cancel_at_period_end'] ?? false);

        $reason = $data['reason'] ?? null;

        if ($cancelAtPeriodEnd) {
            return $this->getService()->scheduleCancellationFromOrder($order, $reason);
        }

        return $this->getService()->cancelFromOrder($order, $reason, $skip_event);
    }

    /**
     * Check whether an order's active gateway subscription supports cancellation at period end.
     */
    public function can_cancel_at_period_end($data): bool
    {
        $this->checkPermissions('order', 'view');

        $order = $this->_getOrder($data);
        $subscriptionService = $this->getDi()['mod_service']('Invoice', 'Subscription');

        return $subscriptionService->canCancelAtPeriodEndForOrder($order);
    }

    /**
     * Uncancel canceled order.
     *
     * @return bool
     */
    public function uncancel($data)
    {
        $this->checkPermissions('order', 'manage');

        $order = $this->_getOrder($data);
        if ($order->getStatus() != Order::STATUS_CANCELED) {
            throw new InformationException('Only canceled orders can be uncanceled');
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
        $this->checkPermissions('order', 'manage');

        $order = $this->_getOrder($data);
        $delete_addons = Tools::normalizeBoolean($data['delete_addons'] ?? false);
        $forceDelete = Tools::normalizeBoolean($data['force_delete'] ?? false);

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
        $this->checkPermissions('order', 'manage');

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
        $this->checkPermissions('order', 'manage');

        return $this->getService()->batchCancelSuspended();
    }

    /**
     * Update order config.
     *
     * @return bool
     */
    #[RequiredParams(['config' => 'Order config not passed'])]
    public function update_config($data)
    {
        $this->checkPermissions('order', 'manage');

        $order = $this->_getOrder($data);

        $config = $data['config'] ?? null;
        if (!is_array($config)) {
            throw new \FOSSBilling\Exception('Order config not passed');
        }

        return $this->getService()->updateOrderConfig($order, $config);
    }

    /**
     * Get order service data.
     *
     * @return array
     */
    public function service($data)
    {
        $this->checkPermissions('order', 'view');

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
        $this->checkPermissions('order', 'view');

        $order = $this->_getOrder($data);

        $data['client_order_id'] = $order->getId();

        [$sql, $bindings] = $this->getService()->getOrderStatusSearchQuery($data);

        return $this->getDi()['pager']->getPaginatedResultSet($sql, $bindings, PaginationOptions::fromArray($data));
    }

    /**
     * Add order status history change.
     *
     * @return array
     */
    #[RequiredParams(['status' => 'Order status was not passed'])]
    public function status_history_add($data)
    {
        $this->checkPermissions('order', 'manage');

        $order = $this->_getOrder($data);

        $notes = $data['notes'] ?? null;

        return $this->getService()->orderStatusAdd($order, $data['status'], $notes);
    }

    /**
     * Remove order status history item.
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Order history line ID was not passed'])]
    public function status_history_delete($data)
    {
        $this->checkPermissions('order', 'manage');

        return $this->getService()->orderStatusRm($data['id']);
    }

    /**
     * Return order statuses codes with counter.
     *
     * @return array
     */
    public function get_statuses()
    {
        $this->checkPermissions('order', 'view');

        return $this->getService()->counter();
    }

    /**
     * Return available invoice options.
     */
    public function get_invoice_options($data): array
    {
        $this->checkPermissions('order', 'view');

        return [
            'issue-invoice' => __trans('Automatically Issue Renewal Invoices'),
            'no-invoice' => __trans('Issue Invoices Manually'),
        ];
    }

    /**
     * Return order statuses codes with titles.
     */
    public function get_status_pairs($data): array
    {
        $this->checkPermissions('order', 'view');

        return [
            Order::STATUS_PENDING_SETUP => 'Pending Setup',
            Order::STATUS_FAILED_SETUP => 'Setup Failed',
            Order::STATUS_ACTIVE => 'Active',
            Order::STATUS_SUSPENDED => 'Suspended',
            Order::STATUS_CANCELED => 'Canceled',
        ];
    }

    /**
     * Return order addons list.
     */
    public function addons($data): array
    {
        $this->checkPermissions('order', 'view');

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
            'id' => 'Order ID was not passed',
        ];
        $this->getDi()['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->getOrderRepository()->find((int) $data['id']);
        if (!$order instanceof Order) {
            throw new InformationException('Order Not Found');
        }

        return $order;
    }

    /**
     * Deletes orders with given IDs.
     *
     * @optional bool $delete_addons - Remove addons also. Default false.
     */
    #[RequiredParams(['ids' => 'Order IDs were not passed'])]
    public function batch_delete($data): bool
    {
        $this->checkPermissions('order', 'manage');

        $delete_addons = Tools::normalizeBoolean($data['delete_addons'] ?? false);

        foreach ($data['ids'] as $id) {
            $this->delete(['id' => $id, 'delete_addons' => $delete_addons]);
        }

        return true;
    }

    public function export_csv($data): Response
    {
        $this->checkPermissions('order', 'export');

        $data['headers'] ??= [];

        return $this->getService()->exportCSV($data['headers']);
    }
}
