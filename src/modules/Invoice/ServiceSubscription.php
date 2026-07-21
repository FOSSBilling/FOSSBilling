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

use Box\Mod\Client\Entity\Client as ClientEntity;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use Box\Mod\Invoice\Repository\SubscriptionRepository;
use Box\Mod\Order\Entity\Order as OrderEntity;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;

class ServiceSubscription implements InjectionAwareInterface
{
    public const STATUS_PENDING_CANCELLATION = 'pending_cancellation';

    protected ?\Pimple\Container $di = null;
    private ?SubscriptionRepository $subscriptionRepository = null;
    private ?PayGatewayRepository $payGatewayRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function create(ClientEntity $client, PayGateway $pg, array $data)
    {
        $model = new Subscription();
        $model->setClientId(isset($data['client_id']) ? (int) $data['client_id'] : null);
        $model->setPayGatewayId(isset($data['gateway_id']) ? (int) $data['gateway_id'] : null);
        $model->setSid($data['sid'] ?? null);
        $model->setStatus($data['status'] ?? null);
        $model->setPeriod($data['period'] ?? null);
        $model->setAmount(isset($data['amount']) ? (float) $data['amount'] : null);
        $model->setCurrency($data['currency'] ?? null);
        $model->setRelId(isset($data['rel_id']) ? (int) $data['rel_id'] : null);
        $model->setRelType($data['rel_type'] ?? null);
        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $newId = $model->getId();

        $this->di['events_manager']->fire(['event' => 'onAfterAdminSubscriptionCreate', 'params' => ['id' => $newId]]);

        $this->di['logger']->info('Created subscription %s', $newId);

        return $newId;
    }

    public function update(Subscription $model, array $data): bool
    {
        $status = $data['status'] ?? null;
        if ($status === 'canceled') {
            $this->cancelAtGateway($model, (string) ($data['sid'] ?? ($model instanceof Subscription ? $model->getSid() : $model->sid)));
        }

        return $this->persistUpdate($model, $data);
    }

    public function updateStatusFromGateway(int $id, string $status): bool
    {
        $model = $this->getSubscriptionRepository()->find($id)
            ?? throw new InformationException('Subscription not found');

        return $this->persistUpdate($model, ['status' => $status]);
    }

    private function persistUpdate(Subscription $model, array $data): bool
    {
        if ($model instanceof Subscription) {
            $model->setStatus($data['status'] ?? $model->getStatus());
            $model->setSid($data['sid'] ?? $model->getSid());
            $model->setPeriod($data['period'] ?? $model->getPeriod());
            $model->setAmount($data['amount'] ?? $model->getAmount());
            $model->setCurrency($data['currency'] ?? $model->getCurrency());
        } else {
            $model->status = $data['status'] ?? $model->status;
            $model->sid = $data['sid'] ?? $model->sid;
            $model->period = $data['period'] ?? $model->period;
            $model->amount = $data['amount'] ?? $model->amount;
            $model->currency = $data['currency'] ?? $model->currency;
            $model->updated_at = date('Y-m-d H:i:s');
        }
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = $model instanceof Subscription ? $model->getId() : $model->id;
        $this->di['logger']->info('Updated subscription %s', $id);

        return true;
    }

    public function toApiArray(Subscription $model, $deep = false, $identity = null): array
    {
        $result = [
            'id' => $model instanceof Subscription ? $model->getId() : $model->id,
            'sid' => $model instanceof Subscription ? $model->getSid() : $model->sid,
            'period' => $model instanceof Subscription ? $model->getPeriod() : $model->period,
            'amount' => $model instanceof Subscription ? $model->getAmount() : $model->amount,
            'currency' => $model instanceof Subscription ? $model->getCurrency() : $model->currency,
            'status' => $model instanceof Subscription ? $model->getStatus() : $model->status,
            'created_at' => $model instanceof Subscription ? $model->getCreatedAt() : $model->created_at,
            'updated_at' => $model instanceof Subscription ? $model->getUpdatedAt() : $model->updated_at,
        ];
        $clientId = $model instanceof Subscription ? $model->getClientId() : $model->client_id;
        $client = $this->di['em']->getRepository(ClientEntity::class)->find($clientId);
        if ($client instanceof ClientEntity) {
            $clientService = $this->di['mod_service']('Client');
            $result['client'] = $clientService->toApiArray($client, false, $identity);
        } else {
            $result['client'] = [];
        }

        $payGatewayId = $model instanceof Subscription ? $model->getPayGatewayId() : $model->pay_gateway_id;
        $gtw = $this->getPayGatewayRepository()->find($payGatewayId);
        if ($gtw instanceof PayGateway) {
            $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
            $result['gateway'] = $payGatewayService->toApiArray($gtw, false, $identity);
        } else {
            $result['gateway'] = [];
        }

        return $result;
    }

    public function delete(Subscription $model): bool
    {
        $id = $model->getId();
        $this->di['em']->remove($model);
        $this->di['em']->flush();

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

    public function getSubscriptionPeriod(Invoice $invoice): ?string
    {
        return $this->getSubscriptionPeriodByInvoiceId((int) $invoice->getId());
    }

    public function unsubscribe(Subscription $model): void
    {
        if ($model instanceof Subscription) {
            $model->setStatus('canceled');
            $model->setUpdatedAt(new \DateTime());
        } else {
            $model->status = 'canceled';
            $model->updated_at = date('Y-m-d H:i:s');
        }
        $this->di['em']->persist($model);
        $this->di['em']->flush();
    }

    public function cancel(Subscription $model): void
    {
        $this->cancelAtGateway($model);
        $this->unsubscribe($model);
    }

    public function scheduleCancellation(Subscription $model): void
    {
        $subscriptionId = trim((string) ($model instanceof Subscription ? $model->getSid() : $model->sid));
        if ($subscriptionId === '') {
            throw new InformationException('The subscription cannot be canceled at the end of its billing period because it has no gateway ID.');
        }

        $adapter = $this->getGatewayAdapter($model);
        if (!method_exists($adapter, 'cancelSubscriptionAtPeriodEnd')) {
            throw new InformationException('The payment gateway does not support cancellation at the end of the billing period.');
        }

        $adapter->cancelSubscriptionAtPeriodEnd($subscriptionId);
        $this->persistUpdate($model, ['status' => self::STATUS_PENDING_CANCELLATION]);
    }

    private function cancelAtGateway(Subscription $model, ?string $subscriptionId = null): void
    {
        $subscriptionId = trim($subscriptionId ?? (string) ($model instanceof Subscription ? $model->getSid() : $model->sid));
        if ($subscriptionId === '') {
            return;
        }

        $adapter = $this->getGatewayAdapter($model);

        if (method_exists($adapter, 'cancelSubscription')) {
            $adapter->cancelSubscription($subscriptionId);
        }
    }

    private function getGatewayAdapter(Subscription $model): object
    {
        $payGatewayId = $model instanceof Subscription ? $model->getPayGatewayId() : $model->pay_gateway_id;
        $gateway = $this->getPayGatewayRepository()->find($payGatewayId)
            ?? throw new InformationException('Payment gateway not found');
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        return $payGatewayService->getPaymentAdapter($gateway);
    }

    public function cancelForOrder(OrderEntity $order): int
    {
        $canceledSubscriptions = 0;
        foreach ($this->getSubscriptionsForOrder($order) as $subscription) {
            $this->cancel($subscription);
            ++$canceledSubscriptions;
        }

        return $canceledSubscriptions;
    }

    public function scheduleCancellationForOrder(OrderEntity $order): int
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
        $subscription = $this->getSubscriptionRepository()->find($id)
            ?? throw new InformationException('Subscription not found');

        if ($subscription->getStatus() === self::STATUS_PENDING_CANCELLATION && $subscription->getRelType() === 'invoice') {
            $query = $this->di['dbal']->createQueryBuilder();
            $orderIds = $query
                ->select('DISTINCT ii.rel_id')
                ->from('invoice_item', 'ii')
                ->innerJoin('ii', 'client_order_meta', 'com', 'com.client_order_id = ii.rel_id')
                ->where('ii.invoice_id = :invoice_id')
                ->andWhere('ii.type = :item_type')
                ->andWhere('com.name = :meta_name')
                ->andWhere('com.value = :meta_value')
                ->setParameter('invoice_id', $subscription->getRelId())
                ->setParameter('item_type', InvoiceItem::TYPE_ORDER)
                ->setParameter('meta_name', \Box\Mod\Order\Service::META_CANCEL_AT_PERIOD_END)
                ->setParameter('meta_value', '1')
                ->executeQuery()
                ->fetchFirstColumn();

            $orderService = $this->di['mod_service']('Order');
            foreach ($orderIds as $orderId) {
                $order = $this->di['em']->getRepository(OrderEntity::class)->find((int) $orderId);
                if (!$order instanceof OrderEntity) {
                    throw new InformationException('Order not found');
                }
                $orderStatus = $order instanceof OrderEntity ? $order->getStatus() : $order->status;
                if (in_array($orderStatus, [OrderEntity::STATUS_CANCELED, OrderEntity::STATUS_PENDING_SETUP, OrderEntity::STATUS_FAILED_SETUP], true)) {
                    continue;
                }

                $orderService->finalizeCancellationFromGateway($order, 'Subscription ended at the payment gateway');
            }
        }

        return $this->persistUpdate($subscription, ['status' => 'canceled']);
    }

    public function canCancelAtPeriodEndForOrder(OrderEntity $order): bool
    {
        $subscriptions = $this->getSubscriptionsForOrder($order, 'active');
        if ($subscriptions === []) {
            return false;
        }

        foreach ($subscriptions as $subscription) {
            if (trim((string) ($subscription instanceof Subscription ? $subscription->getSid() : $subscription->sid)) === '') {
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
     * @return list<Subscription>
     */
    private function getSubscriptionsForOrder(OrderEntity $order, ?string $status = null): array
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
            ->setParameter('item_type', InvoiceItem::TYPE_ORDER)
            ->setParameter('order_id', $order->getId());

        if ($status !== null) {
            $query->andWhere('s.status = :status')->setParameter('status', $status);
        }

        $subscriptionIds = $query->executeQuery()->fetchFirstColumn();

        return array_map(
            fn (mixed $id): Subscription => $this->getSubscriptionRepository()->find((int) $id)
                ?? throw new InformationException('Subscription not found'),
            $subscriptionIds,
        );
    }

    private function getSubscriptionPeriodByInvoiceId(int $invoiceId): ?string
    {
        $query = 'SELECT period, price, quantity
            FROM invoice_item
            WHERE invoice_id = :id
            ORDER BY id ASC';
        $items = $this->di['dbal']->fetchAllAssociative($query, [':id' => $invoiceId]);

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

    private function getPayGatewayRepository(): PayGatewayRepository
    {
        if ($this->payGatewayRepository === null) {
            $this->payGatewayRepository = $this->di['em']->getRepository(PayGateway::class);
        }

        return $this->payGatewayRepository;
    }

    private function getSubscriptionRepository(): SubscriptionRepository
    {
        if ($this->subscriptionRepository === null) {
            $this->subscriptionRepository = $this->di['em']->getRepository(Subscription::class);
        }

        return $this->subscriptionRepository;
    }
}
