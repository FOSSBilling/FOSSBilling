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

use Box\Mod\Client\Entity\Client as ClientEntity;
use Box\Mod\Order\Entity\Order;
use FOSSBilling\Core\Pagination\Options;
use FOSSBilling\Core\Validation\Api\RequiredParams;

class Client extends \FOSSBilling\Core\Api\AbstractApi
{
    /**
     * Get list of orders.
     *
     * @optional string $sort - sort by one of: id, status, title, created_at, updated_at
     * @optional string $direction - sort direction: ASC or DESC
     *
     * @return array
     */
    public function get_list($data)
    {
        $identity = $this->getIdentity();
        $clientId = $this->getClientId($identity);
        $data['client_id'] = $clientId;

        if (isset($data['expiring'])) {
            [$query, $bindings] = $this->getService()->getSoonExpiringActiveOrdersQuery($data);
        } else {
            [$query, $bindings] = $this->getService()->getSearchQuery($data);
        }

        $pager = $this->getDi()['pager']->getPaginatedResultSet($query, $bindings, Options::fromArray($data));

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

        return $this->getService()->toApiArray($model, true, $this->getIdentity());
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
            $result[] = $this->getService()->toApiArray($order, true, $this->getIdentity());
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
        $status = $order->getStatus();

        if ($status !== Order::STATUS_ACTIVE) {
            throw new \FOSSBilling\Core\Exception\InformationException('Order is not active');
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
        $productId = $model->getProductId();

        return $productService->getUpgradablePairsByProductId((int) $productId);
    }

    /**
     * Get upgrade and downgrade options for an active order with prorated calculations.
     */
    public function upgrade_options($data): array
    {
        $model = $this->_getOrder($data);

        return \Box\Mod\Order\Upgrades::options($this->getDi(), $model);
    }

    /**
     * Requests an upgrade or downgrade for an order.
     */
    public function upgrade($data): array
    {
        $required = [
            'id' => 'Order id required',
            'product_id' => 'Choose the new product',
            'period' => 'Choose the billing cycle',
        ];
        $this->getDi()['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->_getOrder($data);

        return \Box\Mod\Order\Upgrades::request($this->getDi(), $model, (int) $data['product_id'], (string) $data['period']);
    }

    /**
     * Can delete only pending setup and failed setup orders.
     */
    public function delete($data)
    {
        $model = $this->_getOrder($data);
        $status = $model->getStatus();
        if (!in_array($status, [Order::STATUS_PENDING_SETUP, Order::STATUS_FAILED_SETUP])) {
            throw new \FOSSBilling\Core\Exception\InformationException('Only pending and failed setup orders can be deleted.');
        }

        return $this->getService()->deleteFromOrder($model);
    }

    protected function _getOrder($data)
    {
        $required = [
            'id' => 'Order id required',
        ];
        $this->getDi()['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->findOrderForIdentity($this->getIdentity(), (int) $data['id']);
        if (!$order instanceof Order) {
            throw new \FOSSBilling\Core\Exception\InformationException('Order not found');
        }

        return $order;
    }

    /**
     * Asks to cancel the service, with a type (immediate or end_of_period)
     * and a reason.
     */
    #[RequiredParams(['type' => 'Choose when the service ends', 'reason' => 'Please tell us why you want to cancel.'])]
    public function cancel_request($data): bool
    {
        if (!is_string($data['type'] ?? null) || !is_string($data['reason'] ?? null)) {
            throw new \FOSSBilling\Core\Exception\InformationException('Cancellation type and reason must be strings.');
        }
        $this->di['mod_service']('Order', 'CancelRequest')->create($this->_getOrder($data), $data['type'], $data['reason']);

        return true;
    }

    /**
     * The waiting cancellation request of the service, or null.
     *
     * @return array|null
     */
    public function cancel_request_get($data)
    {
        $request = $this->di['mod_service']('Order', 'CancelRequest')->pending($this->_getOrder($data));

        return $request === null ? null : $this->di['mod_service']('Order', 'CancelRequest')->toApiArray($request);
    }

    /**
     * Takes back the waiting cancellation request of the service.
     */
    public function cancel_request_withdraw($data): bool
    {
        $identity = $this->getIdentity();
        if (!$identity instanceof ClientEntity) {
            throw new \FOSSBilling\Core\Exception\InformationException('Client identity not found');
        }
        $id = $data['request_id'] ?? null;
        if ((!is_int($id) && !is_string($id)) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new \FOSSBilling\Core\Exception\InformationException('A positive cancellation request ID is required.');
        }
        $this->di['mod_service']('Order', 'CancelRequest')->withdraw($this->_getOrder($data), $identity, (int) $id);

        return true;
    }

    private function getClientId(object $identity): int
    {
        if (!$identity instanceof ClientEntity) {
            throw new \FOSSBilling\Core\Exception\InformationException('Client identity not found');
        }

        return (int) $identity->getId();
    }

    private function findOrderForIdentity(object $identity, int $id): ?Order
    {
        if (!$identity instanceof ClientEntity) {
            throw new \FOSSBilling\Core\Exception\InformationException('Client identity not found');
        }

        return $this->getService()->findForClientById($identity, $id);
    }
}
