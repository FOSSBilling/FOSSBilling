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
use Box\Mod\Invoice\Event\AfterAdminSubscriptionCreateEvent;
use Box\Mod\Invoice\Event\AfterAdminSubscriptionDeleteEvent;
use Box\Mod\Invoice\Repository\SubscriptionRepository;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\PromoRedemption;
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

        $this->di['event_dispatcher']->dispatch(new AfterAdminSubscriptionCreateEvent($newId));

        $this->di['logger']->info('Created subscription {subscription_id}', ['subscription_id' => $newId]);

        return $newId;
    }

    public function update(Subscription $model, array $data): bool
    {
        if (($data['status'] ?? null) === 'canceled') {
            $this->cancelAtGateway($model);
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

        $this->di['event_dispatcher']->dispatch(new AfterAdminSubscriptionDeleteEvent((int) $id));

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

    private function cancelAtGateway(Subscription $model): void
    {
        $subscriptionId = trim((string) $model->getSid());
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

    public function cancelForOrder(Order $order, bool $force = false): int
    {
        $canceledSubscriptions = 0;
        foreach ($this->getSubscriptionsForOrder($order) as $subscription) {
            if ($subscription->getStatus() === 'canceled') {
                continue;
            }

            try {
                $this->cancel($subscription);
                ++$canceledSubscriptions;
            } catch (\Exception $e) {
                if (!$force) {
                    throw $e;
                }
                $this->di['logger']->warning('Forced order deletion left a subscription requiring manual cancellation', [
                    'order_id' => $order->getId(),
                    'subscription_id' => $subscription->getId(),
                    'sid' => $subscription->getSid(),
                    'gateway_id' => $subscription->getPayGateway()?->getId(),
                ]);
            }
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
            $query = $this->di['em']->getConnection()->createQueryBuilder();
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
        $id = $this->di['em']->getConnection()->fetchOne('SELECT id FROM subscription WHERE sid = :sid', ['sid' => $sid]);

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Whether the order is paid through an active gateway subscription.
     * Such orders are renewed by the gateway's subscription payment flow and
     * must keep their one-order-per-invoice shape, so the renewal batch
     * excludes them from merging.
     */
    public function hasActiveSubscriptionForOrder(Order $order): bool
    {
        foreach ($this->getSubscriptionsForOrder($order, 'active') as $subscription) {
            if (trim((string) $subscription->getSid()) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<Subscription>
     */
    private function getSubscriptionsForOrder(Order $order, ?string $status = null): array
    {
        $query = $this->di['em']->getConnection()->createQueryBuilder();
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
        $query = 'SELECT period, price, quantity, type, rel_id
            FROM invoice_item
            WHERE invoice_id = :id
            ORDER BY id ASC';
        $items = $this->di['em']->getConnection()->fetchAllAssociative($query, ['id' => $invoiceId]);

        if (empty($items)) {
            return null;
        }

        $subscriptionPeriod = null;
        $discountTotal = 0.0;
        $positiveTotal = 0.0;
        $orderIds = [];
        foreach ($items as $item) {
            $lineTotal = (float) ($item['price'] ?? 0) * (float) ($item['quantity'] ?? 0);
            $period = $item['period'] ?? null;

            if ($lineTotal <= 0) {
                $discountTotal -= $lineTotal;

                continue;
            }

            if (empty($period)) {
                return null;
            }

            $positiveTotal += $lineTotal;
            if (($item['type'] ?? null) === Entity\InvoiceItem::TYPE_ORDER && is_numeric($item['rel_id'] ?? null)) {
                $orderIds[(int) $item['rel_id']] = true;
            }

            if ($subscriptionPeriod === null) {
                $subscriptionPeriod = $period;

                continue;
            }

            if ($subscriptionPeriod !== $period) {
                return null;
            }
        }

        // The gateway bills this invoice's total on every cycle, but renewal
        // invoices only repeat discounts from recurring promos. Keep one-time
        // discounts out of subscriptions so renewals charge the renewal total.
        if ($subscriptionPeriod !== null && $discountTotal > 0) {
            if (!$this->isDiscountRecurring($invoiceId, $discountTotal)) {
                return null;
            }

            if (!$this->matchesRenewalTotal($positiveTotal - $discountTotal, array_keys($orderIds))) {
                return null;
            }
        }

        return $subscriptionPeriod;
    }

    private function isDiscountRecurring(int $invoiceId, float $discountTotal): bool
    {
        // Repository DQL (not raw SQL) so boolean handling stays portable across
        // MySQL/MariaDB, PostgreSQL and SQLite.
        $recurringDiscount = $this->di['em']->getRepository(PromoRedemption::class)->sumRecurringDiscountForInvoice($invoiceId);

        return round($discountTotal - $recurringDiscount, 2) <= 0.0;
    }

    /**
     * Whether the invoice's pre-tax total equals what its orders will bill on
     * renewal. Renewal invoices reprice domains and re-apply only recurring
     * promos (capped at each order total), so any drift — edited lines, capped
     * absolute promos, removed promos — blocks the subscription. Anything
     * unexpected fails closed: eligibility must never break invoice rendering.
     */
    private function matchesRenewalTotal(float $firstTotal, array $orderIds): bool
    {
        if ($orderIds === []) {
            return false;
        }

        try {
            $orders = $this->di['em']->getRepository(Order::class)->findBy(['id' => $orderIds]);
            if (count($orders) !== count($orderIds)) {
                return false;
            }

            $invoiceService = $this->di['mod_service']('Invoice');
            $productService = $this->di['mod_service']('Product');

            $renewalTotal = 0.0;
            foreach ($orders as $order) {
                ['price' => $price, 'line' => $line] = $invoiceService->resolveRenewalLine($order);
                $price = (float) $price;
                $quantity = (float) ($line['quantity'] ?? $order->getQuantity());
                $orderTotal = $price * $quantity;
                if ($orderTotal <= 0) {
                    return false;
                }

                $discount = 0.0;
                foreach ($productService->getRenewalPromoAdjustments($order, $price, $quantity) as $adjustment) {
                    $discount += (float) ($adjustment['discount_amount'] ?? 0);
                }

                $renewalTotal += max(0.0, $orderTotal - $discount);
            }
        } catch (\Throwable) {
            return false;
        }

        return round($firstTotal - $renewalTotal, 2) === 0.0;
    }
}
