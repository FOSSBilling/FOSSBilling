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
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use Box\Mod\Invoice\Repository\SubscriptionRepository;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\PromoRedemption;
use FOSSBilling\Core\Container\InjectionAwareInterface;
use FOSSBilling\Core\Doctrine\RowLock;

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

    public function updateStatusForGatewayLink(int $id, string $sid, int $gatewayId, string $status): void
    {
        $this->writeStatusForGatewayLink($id, $sid, $gatewayId, $status);
    }

    public function confirmCancellationFromGateway(int $id, string $sid, int $gatewayId): void
    {
        $this->writeStatusForGatewayLink($id, $sid, $gatewayId, 'canceled', confirmedCancellation: true);
    }

    /** @return array{sid: string, pay_gateway_id: int|string, rel_type: ?string, rel_id: int|string|null, status: ?string}|null */
    private function writeStatusForGatewayLink(int $id, string $sid, int $gatewayId, string $status, bool $onlyIfActive = false, bool $confirmedCancellation = false): ?array
    {
        $connection = $this->di['em']->getConnection();
        $link = $connection->transactional(function () use ($connection, $id, $sid, $gatewayId, $status, $onlyIfActive, $confirmedCancellation): ?array {
            $row = $connection->fetchAssociative(
                'SELECT sid, pay_gateway_id, rel_type, rel_id, status FROM subscription WHERE id = ?' . RowLock::suffix($connection),
                [$id],
            );
            if ($row === false || $row['sid'] !== $sid || (int) $row['pay_gateway_id'] !== $gatewayId) {
                return null;
            }
            if ($onlyIfActive && $row['status'] !== 'active') {
                return $row;
            }
            $changes = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
            if ($confirmedCancellation) {
                $changes['gateway_cancellation_confirmation'] = Subscription::cancellationConfirmation($sid, $gatewayId);
            }
            $connection->update('subscription', $changes, ['id' => $id]);

            return $row;
        });
        if ($link !== null) {
            $model = $this->subscriptionRepository->find($id);
            if ($model instanceof Subscription) {
                $this->di['em']->refresh($model);
            }
        }

        return $link;
    }

    private function persistUpdate(Subscription $model, array $data): bool
    {
        $model->setStatus($data['status'] ?? $model->getStatus());
        $model->setSid($data['sid'] ?? $model->getSid());
        $model->setPeriod($data['period'] ?? $model->getPeriod());
        $model->setAmount($data['amount'] ?? $model->getAmount());
        $model->setCurrency($data['currency'] ?? $model->getCurrency());
        $model->setUpdatedAt(new \DateTimeImmutable());
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
        $model->setUpdatedAt(new \DateTimeImmutable());
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

        $id = (int) $model->getId();
        $gatewayId = (int) $model->getPayGateway()?->getId();
        $adapter->cancelSubscriptionAtPeriodEnd($subscriptionId);
        $link = $this->writeStatusForGatewayLink($id, $subscriptionId, $gatewayId, self::STATUS_PENDING_CANCELLATION, onlyIfActive: true);
        if ($link === null || !in_array($link['status'], ['active', self::STATUS_PENDING_CANCELLATION, 'canceled'], true)) {
            throw new \FOSSBilling\Core\Exception\InformationException('The subscription changed while the payment gateway was scheduling cancellation. Staff must reconcile it.');
        }
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
            try {
                if ($subscription->getStatus() === 'canceled') {
                    if ($subscription->getPayGateway()?->getGateway() === 'PayPalEmail' && !$this->isCanceledSubscriptionConfirmed($subscription)) {
                        throw new \FOSSBilling\Core\Exception\InformationException('PayPal has not confirmed cancellation of this subscription.');
                    }

                    continue;
                }
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
        $subscriptions = $this->periodEndSubscriptions($order);
        if ($subscriptions === null) {
            throw new \FOSSBilling\Core\Exception\InformationException('Reconcile every gateway subscription before scheduling this cancellation.');
        }
        foreach ($subscriptions as $subscription) {
            // Stripe checks the provider first and only submits an update if
            // needed, so a recovered schedule can acknowledge the same link.
            $this->scheduleCancellation($subscription);
        }

        return count($subscriptions);
    }

    public function finalizeCancellationFromGateway(int $id, string $sid, int $gatewayId): bool
    {
        // Commit the provider result before service cleanup and use the link
        // captured with that write, even if a later update changes the row.
        $link = $this->writeStatusForGatewayLink($id, $sid, $gatewayId, 'canceled');
        if ($link === null) {
            return false;
        }

        if ($link['rel_type'] === 'invoice') {
            // Legacy links first: a backfill page converting a row between the
            // two reads must not hide it from both. Reads are one-directional
            // (order_id only ever fills in), so matching the legacy shape
            // before the typed one covers every interleave.
            $legacyRelIds = $this->di['em']->getConnection()->fetchFirstColumn(
                'SELECT ii.rel_id FROM invoice_item ii WHERE ii.invoice_id = :invoice_id AND ii.type = :item_type AND ii.order_id IS NULL',
                ['invoice_id' => $link['rel_id'], 'item_type' => Entity\InvoiceItem::TYPE_ORDER]
            );
            $legacyOrderIds = [];
            foreach ($legacyRelIds as $relId) {
                // Text-side counterpart of the join below: a text-to-integer
                // comparison is coercion on MySQL, an error on PostgreSQL for
                // junk like '7.5', and loose matching on SQLite. Only a
                // digit-only order id written as text resolves, mirroring the
                // backfill's own rule.
                $legacyOrderId = ServiceInvoiceItem::resolveOrderReference(Entity\InvoiceItem::TYPE_ORDER, null, is_scalar($relId) ? (string) $relId : null);
                if ($legacyOrderId !== null) {
                    $legacyOrderIds[] = $legacyOrderId;
                }
            }

            $query = $this->di['em']->getConnection()->createQueryBuilder();
            $typedOrderIds = array_map(
                intval(...),
                $query
                    ->select('DISTINCT ii.order_id')
                    ->from('invoice_item', 'ii')
                    ->innerJoin('ii', 'client_order_meta', 'com', 'com.client_order_id = ii.order_id')
                    ->where('ii.invoice_id = :invoice_id')
                    ->andWhere('ii.type = :item_type')
                    ->andWhere('com.name = :meta_name')
                    ->andWhere('com.value = :meta_value')
                    ->setParameter('invoice_id', $link['rel_id'])
                    ->setParameter('item_type', Entity\InvoiceItem::TYPE_ORDER)
                    ->setParameter('meta_name', \Box\Mod\Order\Service::META_CANCEL_AT_PERIOD_END)
                    ->setParameter('meta_value', '1')
                    ->executeQuery()
                    ->fetchFirstColumn()
            );

            $orderService = $this->di['mod_service']('Order');
            foreach (array_values(array_unique(array_merge($typedOrderIds, $legacyOrderIds))) as $orderId) {
                $order = $this->di['em']->getRepository(Order::class)->find($orderId);
                if (!$order instanceof Order) {
                    continue;
                }
                if (in_array($order->getStatus(), [Order::STATUS_CANCELED, Order::STATUS_PENDING_SETUP, Order::STATUS_FAILED_SETUP], true)) {
                    continue;
                }
                if (!in_array($orderId, $typedOrderIds, true)) {
                    // Typed candidates proved the scheduled-cancellation flag
                    // through the join; legacy-only ones prove it here.
                    $pairs = $orderService->getOrderMetaRepository()->getPairsForOrder($orderId);
                    if (($pairs[\Box\Mod\Order\Service::META_CANCEL_AT_PERIOD_END] ?? null) !== '1') {
                        continue;
                    }
                }

                $orderService->finalizeCancellationFromGateway($order, 'Subscription ended at the payment gateway');
            }
        }

        return true;
    }

    private function hasOtherLiveOrder(Subscription $subscription, Order $order): bool
    {
        if ($subscription->getRelType() !== 'invoice') {
            return false;
        }

        $connection = $this->di['em']->getConnection();
        $legacyMatch = $this->sourceLegacyOrderLink($connection, 'o.id');

        return (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM invoice_item ii LEFT JOIN invoice i ON i.id = ii.invoice_id INNER JOIN client_order o ON (ii.order_id = o.id OR (ii.order_id IS NULL AND $legacyMatch)) WHERE ii.invoice_id = ? AND ii.type = ? AND o.id <> ? AND (o.status IS NULL OR o.status <> ?)",
            [$subscription->getRelId(), Entity\InvoiceItem::TYPE_ORDER, $order->getId(), Order::STATUS_CANCELED]
        ) > 0;
    }

    public function assertNoSharedSubscriptionForOrder(Order $order): void
    {
        foreach ($this->getSubscriptionsForOrder($order) as $subscription) {
            if ($this->hasOtherLiveOrder($subscription, $order)
                && ($subscription->getStatus() !== 'canceled' || !$this->isCanceledSubscriptionConfirmed($subscription))) {
                throw new \FOSSBilling\Core\Exception\InformationException('Reconcile the shared gateway subscription before canceling this service.');
            }
        }
    }

    public function canCancelImmediatelyForOrder(Order $order): bool
    {
        foreach ($this->getSubscriptionsForOrder($order) as $subscription) {
            if ($subscription->getStatus() === 'canceled') {
                if (!$this->isCanceledSubscriptionConfirmed($subscription)) {
                    return false;
                }

                continue;
            }

            try {
                if ($this->hasOtherLiveOrder($subscription, $order) || trim((string) $subscription->getSid()) === ''
                    || !method_exists($this->getGatewayAdapter($subscription), 'cancelSubscription')) {
                    return false;
                }
            } catch (\Exception) {
                return false;
            }
        }

        return true;
    }

    public function canCancelAtPeriodEndForOrder(Order $order): bool
    {
        return !empty($this->periodEndSubscriptions($order));
    }

    /** @return list<Subscription>|null Null when an unresolved link needs reconciliation. */
    private function periodEndSubscriptions(Order $order): ?array
    {
        $supported = [];
        foreach ($this->getSubscriptionsForOrder($order) as $subscription) {
            if ($subscription->getStatus() === 'canceled') {
                if (!$this->isCanceledSubscriptionConfirmed($subscription)) {
                    return null;
                }

                continue;
            }
            if (!in_array($subscription->getStatus(), ['active', self::STATUS_PENDING_CANCELLATION], true)
                || $this->hasOtherLiveOrder($subscription, $order) || trim((string) $subscription->getSid()) === '') {
                return null;
            }

            try {
                if (!method_exists($this->getGatewayAdapter($subscription), 'cancelSubscriptionAtPeriodEnd')) {
                    return null;
                }
            } catch (\Exception) {
                return null;
            }
            $supported[] = $subscription;
        }

        return $supported;
    }

    public function findIdBySid(string $sid, ?int $gatewayId = null): ?int
    {
        $sql = 'SELECT id FROM subscription WHERE sid = :sid';
        $parameters = ['sid' => $sid];
        if ($gatewayId !== null) {
            $sql .= ' AND pay_gateway_id = :gateway';
            $parameters['gateway'] = $gatewayId;
        }
        $id = $this->di['em']->getConnection()->fetchOne($sql, $parameters);

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

    /** A pending cancellation remains live at the gateway until it ends there. */
    public function hasUncanceledSubscriptionForOrder(Order $order): bool
    {
        foreach ($this->getSubscriptionsForOrder($order) as $subscription) {
            if (trim((string) $subscription->getSid()) !== ''
                && ($subscription->getStatus() !== 'canceled' || !$this->isCanceledSubscriptionConfirmed($subscription))) {
                return true;
            }
        }

        return false;
    }

    private function isCanceledSubscriptionConfirmed(Subscription $subscription): bool
    {
        $sid = trim((string) $subscription->getSid());
        if ($sid === '') {
            return true;
        }
        if ($subscription->getPayGateway()?->getGateway() === 'PayPalEmail') {
            return $subscription->hasGatewayCancellationConfirmation();
        }
        if ($subscription->getPayGateway()?->getGateway() !== 'Stripe') {
            return true;
        }

        try {
            return $this->getGatewayAdapter($subscription)->getSubscriptionStatus($sid) === 'canceled';
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @return list<Subscription>
     */
    private function getSubscriptionsForOrder(Order $order, ?string $status = null): array
    {
        $orderId = $order->getId();
        if ($orderId === null) {
            return [];
        }

        $connection = $this->di['em']->getConnection();
        $legacyMatch = $this->sourceLegacyOrderLink($connection, ':order_id');
        $query = 'SELECT s.id FROM subscription s INNER JOIN invoice_item ii ON ii.invoice_id = s.rel_id
                    LEFT JOIN invoice i ON i.id = ii.invoice_id
                  WHERE s.rel_type = :rel_type AND ii.type = :item_type';
        $params = ['rel_type' => 'invoice', 'item_type' => Entity\InvoiceItem::TYPE_ORDER, 'order_id' => $orderId];
        if ($status !== null) {
            $query .= ' AND s.status = :status';
            $params['status'] = $status;
        }

        // Read both link types together so backfill cannot move a row between reads.
        $subscriptionIds = $connection->fetchFirstColumn(
            "$query AND ii.order_id = :order_id
             UNION
             $query AND ii.order_id IS NULL AND $legacyMatch",
            $params,
        );

        $subscriptions = [];
        foreach ($subscriptionIds as $id) {
            $subscription = $this->subscriptionRepository->find((int) $id);
            if ($subscription instanceof Subscription) {
                $subscriptions[] = $subscription;
            }
        }

        return $subscriptions;
    }

    private function sourceLegacyOrderLink(\Doctrine\DBAL\Connection $connection, string $orderId): string
    {
        $reference = InvoiceItemRepository::orderIdAsReference($connection, $orderId);
        $legacy = InvoiceItemRepository::normalizedLegacyOrderReference($connection, 'ii.rel_id');
        $paidLegacy = InvoiceItemRepository::normalizedLegacyPaidOrderReference($connection, 'ii.rel_id');

        return "($legacy = $reference OR (i.status = 'paid' AND $paidLegacy = $reference))";
    }

    private function getSubscriptionPeriodByInvoiceId(int $invoiceId): ?string
    {
        $query = 'SELECT period, price, quantity, type, order_id, rel_id, task
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
            // The gateway fee is part of each payment, not a line of its own period.
            if (($item['task'] ?? null) === ServiceGatewayFees::TASK) {
                continue;
            }
            if (($item['type'] ?? null) === Entity\InvoiceItem::TYPE_ORDER) {
                $orderId = isset($item['order_id'])
                    ? (int) $item['order_id']
                    : ServiceInvoiceItem::resolveOrderReference(Entity\InvoiceItem::TYPE_ORDER, null, $item['rel_id'] ?? null);
                if ($orderId === null) {
                    return null;
                }
                $orderIds[$orderId] = true;
                if (count($orderIds) > 1) {
                    return null;
                }
            }
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
        }
        if ($subscriptionPeriod !== null && !$this->matchesRenewalTotal($positiveTotal - $discountTotal, array_keys($orderIds))) {
            return null;
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
