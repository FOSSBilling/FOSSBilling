<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice;

use FOSSBilling\InjectionAwareInterface;

class ServiceSubscription implements InjectionAwareInterface
{
    public const STATUS_PENDING_CANCELLATION = 'pending_cancellation';

    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function create(\Model_Client $client, \Model_PayGateway $pg, array $data)
    {
        $model = $this->di['db']->dispense('Subscription');
        $model->client_id = $data['client_id'];
        $model->pay_gateway_id = $data['gateway_id'];

        $model->sid = $data['sid'] ?? null;
        $model->status = $data['status'] ?? null;
        $model->period = $data['period'] ?? null;
        $model->amount = $data['amount'] ?? null;
        $model->currency = $data['currency'] ?? null;
        $model->rel_id = $data['rel_id'] ?? null;
        $model->rel_type = $data['rel_type'] ?? null;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $newId = $this->di['db']->store($model);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminSubscriptionCreate', 'params' => ['id' => $newId]]);

        $this->di['logger']->info('Created subscription %s', $newId);

        return $newId;
    }

    public function update(\Model_Subscription $model, array $data): bool
    {
        if (($data['status'] ?? null) === 'canceled') {
            $this->cancelAtGateway($model, (string) ($data['sid'] ?? $model->sid));
        }

        return $this->persistUpdate($model, $data);
    }

    public function updateStatusFromGateway(int $id, string $status): bool
    {
        $model = $this->di['db']->getExistingModelById('Subscription', $id, 'Subscription not found');

        return $this->persistUpdate($model, ['status' => $status]);
    }

    private function persistUpdate(\Model_Subscription $model, array $data): bool
    {
        $model->status = $data['status'] ?? $model->status;
        $model->sid = $data['sid'] ?? $model->sid;
        $model->period = $data['period'] ?? $model->period;
        $model->amount = $data['amount'] ?? $model->amount;
        $model->currency = $data['currency'] ?? $model->currency;
        $model->updated_at = date('Y-m-d H:i:s');
        $newId = $this->di['db']->store($model);

        $this->di['logger']->info('Updated subscription %s', $newId);

        return true;
    }

    public function toApiArray(\Model_Subscription $model, $deep = false, $identity = null): array
    {
        $result = [
            'id' => $model->id,
            'sid' => $model->sid,
            'period' => $model->period,
            'amount' => $model->amount,
            'currency' => $model->currency,
            'status' => $model->status,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
        ];
        $client = $this->di['db']->load('Client', $model->client_id);
        if ($client instanceof \Model_Client) {
            $clientService = $this->di['mod_service']('Client');
            $result['client'] = $clientService->toApiArray($client, false, $identity);
        } else {
            $result['client'] = [];
        }

        $gtw = $this->di['db']->load('PayGateway', $model->pay_gateway_id);
        if ($gtw instanceof \Model_PayGateway) {
            $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
            $result['gateway'] = $payGatewayService->toApiArray($gtw, false, $identity);
        } else {
            $result['gateway'] = [];
        }

        return $result;
    }

    public function delete(\Model_Subscription $model): bool
    {
        $id = $model->id;
        $this->di['db']->trash($model);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminSubscriptionDelete', 'params' => ['id' => $id]]);

        $this->di['logger']->info('Removed subscription %s', $id);

        return true;
    }

    public function getSearchQuery(array $data): array
    {
        $sql = 'SELECT *
            FROM subscription
            WHERE 1 ';

        $id = $data['id'] ?? null;
        $sid = $data['sid'] ?? null;
        $search = $data['search'] ?? null;
        $invoice_id = $data['invoice_id'] ?? null;
        $gateway_id = $data['gateway_id'] ?? null;
        $client_id = $data['client_id'] ?? null;
        $status = $data['status'] ?? null;
        $currency = $data['currency'] ?? null;

        $date_from = $data['date_from'] ?? null;
        $date_to = $data['date_to'] ?? null;
        $params = [];

        if ($status) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        if ($invoice_id) {
            $sql .= ' AND invoice_id = :invoice_id';
            $params[':invoice_id'] = $invoice_id;
        }

        if ($gateway_id) {
            $sql .= ' AND gateway_id = :gateway_id';
            $params[':gateway_id'] = $gateway_id;
        }

        if ($client_id) {
            $sql .= ' AND client_id  = :client_id';
            $params[':client_id'] = $client_id;
        }

        if ($currency) {
            $sql .= ' AND currency =  :currency ';
            $params[':currency'] = $currency;
        }

        if ($date_from) {
            $sql .= ' AND UNIX_TIMESTAMP(created_at) >= :date_from';
            $params[':date_from'] = ctype_digit((string) $date_from) ? $date_from : strtotime($date_from . ' 00:00:00');
        }

        if ($date_to) {
            $sql .= ' AND UNIX_TIMESTAMP(created_at) <= :date_to';
            $params[':date_to'] = ctype_digit((string) $date_to) ? $date_to : strtotime($date_to . ' 23:59:59');
        }

        if ($search) {
            $sql .= ' AND (sid = :sid OR id = :mid) ';
            $params[':sid'] = $search;
            $params[':mid'] = $search;
        }

        if ($id) {
            $sql .= ' AND id = :id';
            $params[':id'] = $id;
        }

        if ($sid) {
            $sql .= ' AND sid = :sid';
            $params[':sid'] = $sid;
        }

        $sql .= ' ORDER BY id DESC';

        return [$sql, $params];
    }

    public function isSubscribable($invoice_id): bool
    {
        return $this->getSubscriptionPeriodByInvoiceId((int) $invoice_id) !== null;
    }

    public function getSubscriptionPeriod(\Model_Invoice $invoice): ?string
    {
        return $this->getSubscriptionPeriodByInvoiceId((int) $invoice->id);
    }

    public function unsubscribe(\Model_Subscription $model): void
    {
        $model->status = 'canceled';
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
    }

    public function cancel(\Model_Subscription $model): void
    {
        $this->cancelAtGateway($model);
        $this->unsubscribe($model);
    }

    public function scheduleCancellation(\Model_Subscription $model): void
    {
        $subscriptionId = trim((string) $model->sid);
        if ($subscriptionId === '') {
            throw new \FOSSBilling\InformationException('The subscription cannot be canceled at the end of its billing period because it has no gateway ID.');
        }

        $adapter = $this->getGatewayAdapter($model);
        if (!method_exists($adapter, 'cancelSubscriptionAtPeriodEnd')) {
            throw new \FOSSBilling\InformationException('The payment gateway does not support cancellation at the end of the billing period.');
        }

        $adapter->cancelSubscriptionAtPeriodEnd($subscriptionId);
        $this->persistUpdate($model, ['status' => self::STATUS_PENDING_CANCELLATION]);
    }

    private function cancelAtGateway(\Model_Subscription $model, ?string $subscriptionId = null): void
    {
        $subscriptionId = trim($subscriptionId ?? (string) $model->sid);
        if ($subscriptionId === '') {
            return;
        }

        $adapter = $this->getGatewayAdapter($model);

        if (method_exists($adapter, 'cancelSubscription')) {
            $adapter->cancelSubscription($subscriptionId);
        }
    }

    private function getGatewayAdapter(\Model_Subscription $model): object
    {
        $gateway = $this->di['db']->getExistingModelById('PayGateway', $model->pay_gateway_id, 'Payment gateway not found');
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        return $payGatewayService->getPaymentAdapter($gateway);
    }

    public function cancelForOrder(\Model_ClientOrder $order): int
    {
        $canceledSubscriptions = 0;
        foreach ($this->getSubscriptionsForOrder($order) as $subscription) {
            $this->cancel($subscription);
            ++$canceledSubscriptions;
        }

        return $canceledSubscriptions;
    }

    public function scheduleCancellationForOrder(\Model_ClientOrder $order): int
    {
        $scheduledSubscriptions = 0;
        foreach ($this->getSubscriptionsForOrder($order, 'active') as $subscription) {
            $this->scheduleCancellation($subscription);
            ++$scheduledSubscriptions;
        }

        return $scheduledSubscriptions;
    }

    public function finalizeCancellationFromGateway(int $id): bool
    {
        $subscription = $this->di['db']->getExistingModelById('Subscription', $id, 'Subscription not found');

        if ($subscription->status === self::STATUS_PENDING_CANCELLATION && $subscription->rel_type === 'invoice') {
            $query = $this->di['dbal']->createQueryBuilder();
            $orderIds = $query
                ->select('DISTINCT ii.rel_id')
                ->from('invoice_item', 'ii')
                ->innerJoin('ii', 'client_order_meta', 'com', 'com.client_order_id = ii.rel_id')
                ->where('ii.invoice_id = :invoice_id')
                ->andWhere('ii.type = :item_type')
                ->andWhere('com.name = :meta_name')
                ->andWhere('com.value = :meta_value')
                ->setParameter('invoice_id', $subscription->rel_id)
                ->setParameter('item_type', \Model_InvoiceItem::TYPE_ORDER)
                ->setParameter('meta_name', \Box\Mod\Order\Service::META_CANCEL_AT_PERIOD_END)
                ->setParameter('meta_value', '1')
                ->executeQuery()
                ->fetchFirstColumn();

            $orderService = $this->di['mod_service']('Order');
            foreach ($orderIds as $orderId) {
                $order = $this->di['db']->getExistingModelById('ClientOrder', (int) $orderId, 'Order not found');
                if (in_array($order->status, [\Model_ClientOrder::STATUS_CANCELED, \Model_ClientOrder::STATUS_PENDING_SETUP, \Model_ClientOrder::STATUS_FAILED_SETUP], true)) {
                    continue;
                }

                $orderService->finalizeCancellationFromGateway($order, 'Subscription ended at the payment gateway');
            }
        }

        return $this->persistUpdate($subscription, ['status' => 'canceled']);
    }

    public function canCancelAtPeriodEndForOrder(\Model_ClientOrder $order): bool
    {
        $subscriptions = $this->getSubscriptionsForOrder($order, 'active');
        if ($subscriptions === []) {
            return false;
        }

        foreach ($subscriptions as $subscription) {
            if (trim((string) $subscription->sid) === '') {
                return false;
            }

            try {
                $adapter = $this->getGatewayAdapter($subscription);
            } catch (\Exception) {
                return false;
            }

            if (!method_exists($adapter, 'cancelSubscriptionAtPeriodEnd')) {
                return false;
            }
        }

        return true;
    }

    public function findIdBySid(string $sid): ?int
    {
        $id = $this->di['dbal']->fetchOne('SELECT id FROM subscription WHERE sid = :sid', ['sid' => $sid]);

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * @return list<\Model_Subscription>
     */
    private function getSubscriptionsForOrder(\Model_ClientOrder $order, ?string $status = null): array
    {
        $query = $this->di['dbal']->createQueryBuilder();
        $query
            ->select('DISTINCT s.id')
            ->from('subscription', 's')
            ->innerJoin('s', 'invoice_item', 'ii', 'ii.invoice_id = s.rel_id')
            ->where('s.rel_type = :rel_type')
            ->andWhere('ii.type = :item_type')
            ->andWhere('ii.rel_id = :order_id')
            ->setParameter('rel_type', 'invoice')
            ->setParameter('item_type', \Model_InvoiceItem::TYPE_ORDER)
            ->setParameter('order_id', $order->id);

        if ($status !== null) {
            $query->andWhere('s.status = :status')->setParameter('status', $status);
        }

        $subscriptionIds = $query->executeQuery()->fetchFirstColumn();

        return array_map(
            fn (mixed $id): \Model_Subscription => $this->di['db']->getExistingModelById('Subscription', (int) $id, 'Subscription not found'),
            $subscriptionIds,
        );
    }

    private function getSubscriptionPeriodByInvoiceId(int $invoiceId): ?string
    {
        $query = 'SELECT period, price, quantity
            FROM invoice_item
            WHERE invoice_id = :id
            ORDER BY id ASC';
        $items = $this->di['db']->getAll($query, [':id' => $invoiceId]);

        if (empty($items)) {
            return null;
        }

        $subscriptionPeriod = null;
        foreach ($items as $item) {
            $lineTotal = (float) ($item['price'] ?? 0) * (float) ($item['quantity'] ?? 0);
            $period = $item['period'] ?? null;

            if ($lineTotal <= 0) {
                continue;
            }

            if (empty($period)) {
                return null;
            }

            if ($subscriptionPeriod === null) {
                $subscriptionPeriod = $period;

                continue;
            }

            if ($subscriptionPeriod !== $period) {
                return null;
            }
        }

        return $subscriptionPeriod;
    }
}
