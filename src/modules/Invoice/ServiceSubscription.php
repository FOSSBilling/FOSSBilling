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

use Box\Mod\Client\Entity\Client;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Repository\SubscriptionRepository;
use Box\Mod\Order\Entity\Order;
use FOSSBilling\Core\Container\InjectionAwareInterface;

class ServiceSubscription implements InjectionAwareInterface
{
    public const STATUS_PENDING_CANCELLATION = 'pending_cancellation';

    protected ?\Pimple\Container $di = null;
    private SubscriptionRepository $subscriptionRepository;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->subscriptionRepository = $di['em']->getRepository(Subscription::class);
    }

    public function getSubscriptionRepository(): SubscriptionRepository
    {
        return $this->subscriptionRepository;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function create(Client $client, PayGateway $pg, array $data): int
    {
        $model = new Subscription();
        $model->setClientId($client->getId() ? (int) $client->getId() : null);
        $model->setPayGateway($pg);

        $model->setSid($data['sid'] ?? null);
        $model->setStatus($data['status'] ?? null);
        $model->setPeriod($data['period'] ?? null);
        $model->setAmount($data['amount'] ?? null);
        $model->setCurrency($data['currency'] ?? null);
        $model->setRelId(isset($data['rel_id']) ? (int) $data['rel_id'] : null);
        $model->setRelType($data['rel_type'] ?? null);
        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $newId = (int) $model->getId();

        $this->di['events_manager']->fire(['event' => 'onAfterAdminSubscriptionCreate', 'params' => ['id' => $newId]]);

        $this->di['logger']->info('Created subscription {subscription_id}', ['subscription_id' => $newId]);

        return $newId;
    }

    public function update(Subscription $model, array $data): bool
    {
        if (($data['status'] ?? null) === 'canceled') {
            $this->cancelAtGateway($model, (string) ($data['sid'] ?? $model->getSid()));
        }

        return $this->persistUpdate($model, $data);
    }

    public function updateStatusFromGateway(int $id, string $status): bool
    {
        $model = $this->subscriptionRepository->find($id);
        if ($model === null) {
            throw new \FOSSBilling\Core\Exception\BaseException('Subscription not found');
        }

        return $this->persistUpdate($model, ['status' => $status]);
    }

    private function persistUpdate(Subscription $model, array $data): bool
    {
        $model->setStatus($data['status'] ?? $model->getStatus());
        $model->setSid($data['sid'] ?? $model->getSid());
        $model->setPeriod($data['period'] ?? $model->getPeriod());
        $model->setAmount($data['amount'] ?? $model->getAmount());
        $model->setCurrency($data['currency'] ?? $model->getCurrency());
        $model->setUpdatedAt(new \DateTime());
        $this->di['em']->flush();
        $newId = (int) $model->getId();

        $this->di['logger']->info('Updated subscription {subscription_id}', ['subscription_id' => $newId]);

        return true;
    }

    public function toApiArray(Subscription $model, $deep = false, $identity = null): array
    {
        $result = [
            'id' => $model->getId(),
            'sid' => $model->getSid(),
            'period' => $model->getPeriod(),
            'amount' => $model->getAmount(),
            'currency' => $model->getCurrency(),
            'status' => $model->getStatus(),
            'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
        $client = $this->di['em']->getRepository(Client::class)->find($model->getClientId());
        if ($client instanceof Client) {
            $clientService = $this->di['mod_service']('Client');
            $result['client'] = $clientService->toApiArray($client, false, $identity);
        } else {
            $result['client'] = [];
        }

        $gtw = $model->getPayGateway();
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

        $this->di['logger']->info('Removed subscription {id}', ['id' => $id]);

        return true;
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
        $model->setStatus('canceled');
        $model->setUpdatedAt(new \DateTime());
        $this->di['em']->flush();
    }

    public function cancel(Subscription $model): void
    {
        $this->cancelAtGateway($model);
        $this->unsubscribe($model);
    }

    public function scheduleCancellation(Subscription $model): void
    {
        $subscriptionId = trim((string) $model->getSid());
        if ($subscriptionId === '') {
            throw new \FOSSBilling\Core\Exception\InformationException('The subscription cannot be canceled at the end of its billing period because it has no gateway ID.');
        }

        $adapter = $this->getGatewayAdapter($model);
        if (!method_exists($adapter, 'cancelSubscriptionAtPeriodEnd')) {
            throw new \FOSSBilling\Core\Exception\InformationException('The payment gateway does not support cancellation at the end of the billing period.');
        }

        $adapter->cancelSubscriptionAtPeriodEnd($subscriptionId);
        $this->persistUpdate($model, ['status' => self::STATUS_PENDING_CANCELLATION]);
    }

    private function cancelAtGateway(Subscription $model, ?string $subscriptionId = null): void
    {
        $subscriptionId = trim($subscriptionId ?? (string) $model->getSid());
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
        $gateway = $model->getPayGateway();
        if (!$gateway instanceof PayGateway) {
            throw new \FOSSBilling\Core\Exception\BaseException('Payment gateway not found');
        }
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        return $payGatewayService->getPaymentAdapter($gateway);
    }

    public function cancelForOrder(Order $order): int
    {
        $canceledSubscriptions = 0;
        foreach ($this->getSubscriptionsForOrder($order) as $subscription) {
            $this->cancel($subscription);
            ++$canceledSubscriptions;
        }

        return $canceledSubscriptions;
    }

    public function scheduleCancellationForOrder(Order $order): int
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
        $subscription = $this->subscriptionRepository->find($id);
        if ($subscription === null) {
            throw new \FOSSBilling\Core\Exception\BaseException('Subscription not found');
        }

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
                ->setParameter('item_type', Entity\InvoiceItem::TYPE_ORDER)
                ->setParameter('meta_name', \Box\Mod\Order\Service::META_CANCEL_AT_PERIOD_END)
                ->setParameter('meta_value', '1')
                ->executeQuery()
                ->fetchFirstColumn();

            $orderService = $this->di['mod_service']('Order');
            foreach ($orderIds as $orderId) {
                $order = $this->di['em']->getRepository(Order::class)->find((int) $orderId);
                if (!$order instanceof Order) {
                    continue;
                }
                if (in_array($order->getStatus(), [Order::STATUS_CANCELED, Order::STATUS_PENDING_SETUP, Order::STATUS_FAILED_SETUP], true)) {
                    continue;
                }

                $orderService->finalizeCancellationFromGateway($order, 'Subscription ended at the payment gateway');
            }
        }

        return $this->persistUpdate($subscription, ['status' => 'canceled']);
    }

    public function canCancelAtPeriodEndForOrder(Order $order): bool
    {
        $subscriptions = $this->getSubscriptionsForOrder($order, 'active');
        if ($subscriptions === []) {
            return false;
        }

        foreach ($subscriptions as $subscription) {
            if (trim((string) $subscription->getSid()) === '') {
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
    private function getSubscriptionsForOrder(Order $order, ?string $status = null): array
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
            ->setParameter('item_type', Entity\InvoiceItem::TYPE_ORDER)
            ->setParameter('order_id', $order->getId());

        if ($status !== null) {
            $query->andWhere('s.status = :status')->setParameter('status', $status);
        }

        $subscriptionIds = $query->executeQuery()->fetchFirstColumn();

        $subscriptions = [];
        foreach ($subscriptionIds as $id) {
            $subscription = $this->subscriptionRepository->find((int) $id);
            if ($subscription instanceof Subscription) {
                $subscriptions[] = $subscription;
            }
        }

        return $subscriptions;
    }

    private function getSubscriptionPeriodByInvoiceId(int $invoiceId): ?string
    {
        $query = 'SELECT period, price, quantity
            FROM invoice_item
            WHERE invoice_id = :id
            ORDER BY id ASC';
        $items = $this->di['em']->getConnection()->fetchAllAssociative($query, ['id' => $invoiceId]);

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
