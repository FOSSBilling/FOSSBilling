<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Order;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Order\Entity\ClientOrderCancelRequest;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Repository\ClientOrderCancelRequestRepository;
use FOSSBilling\Core\Container\InjectionAwareInterface;
use FOSSBilling\Core\Doctrine\NamedLock;
use FOSSBilling\Core\Exception\InformationException;

class ServiceCancelRequest implements InjectionAwareInterface
{
    final public const int MAX_REASON = 2000;

    /** @var array<string, true> */
    private static array $activeRequests = [];

    protected ?\Pimple\Container $di = null;

    protected ClientOrderCancelRequestRepository $cancelRequestRepository;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->cancelRequestRepository = $di['em']->getRepository(ClientOrderCancelRequest::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getCancelRequestRepository(): ClientOrderCancelRequestRepository
    {
        return $this->cancelRequestRepository;
    }

    public function create(Order $order, string $type, string $reason): ClientOrderCancelRequest
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InformationException('Please tell us why you want to cancel.');
        }
        if (!in_array($type, [ClientOrderCancelRequest::TYPE_IMMEDIATE, ClientOrderCancelRequest::TYPE_END_OF_PERIOD], true)) {
            throw new InformationException('Invalid cancellation type.');
        }
        if ($order->getStatus() !== Order::STATUS_ACTIVE) {
            throw new InformationException('Only an active service can be cancelled.');
        }
        if ($type === ClientOrderCancelRequest::TYPE_END_OF_PERIOD && $order->getExpiresAt() === null) {
            throw new InformationException('This service cannot be cancelled at the end of its period. Choose Immediate, or open a ticket.');
        }
        if ($this->pending($order) instanceof ClientOrderCancelRequest) {
            throw new InformationException('We have already received this request.');
        }

        $request = new ClientOrderCancelRequest();
        $request->setOrder($order);
        $request->setClient($this->di['em']->getRepository(Client::class)->find($order->getClientId()));
        $request->setType($type);
        $request->setReason(mb_substr($reason, 0, self::MAX_REASON));
        $request->setStatus(ClientOrderCancelRequest::STATUS_PENDING);
        $this->di['em']->persist($request);
        $this->di['em']->flush();

        $this->di['logger']->info('Client requested the cancellation of order #{order_id} ({type})', ['order_id' => $order->getId(), 'type' => $type]);
        $this->notify($request, $type);

        return $request;
    }

    public function pending(Order $order): ?ClientOrderCancelRequest
    {
        return $this->cancelRequestRepository->findPendingByOrderId((int) $order->getId());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingList(): array
    {
        return $this->cancelRequestRepository->getPendingList();
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(ClientOrderCancelRequest $request): array
    {
        return [
            'id' => $request->getId(),
            'client_order_id' => $request->getOrder()?->getId(),
            'client_id' => $request->getClient()?->getId(),
            'type' => $request->getType(),
            'reason' => $request->getReason(),
            'status' => $request->getStatus(),
            'created_at' => $request->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $request->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function withdraw(Order $order, Client $client): void
    {
        $request = $this->pending($order);
        if (!$request instanceof ClientOrderCancelRequest || (int) $request->getClient()?->getId() !== (int) $client->getId()) {
            throw new InformationException('This service has no cancellation request waiting.');
        }

        $this->withRequestLock((int) $request->getId(), function () use ($request, $order, $client): void {
            $request = $this->find((int) $request->getId());
            if ((int) $request->getClient()?->getId() !== (int) $client->getId() || (int) $request->getOrder()?->getId() !== (int) $order->getId()) {
                throw new InformationException('This service has no cancellation request waiting.');
            }
            $this->close($request, ClientOrderCancelRequest::STATUS_WITHDRAWN);
            $this->di['logger']->info('Client withdrew the cancellation request of order #{order_id}', ['order_id' => $order->getId()]);
        });
    }

    public function stop(int $id): void
    {
        $this->withRequestLock($id, function () use ($id): void {
            $request = $this->find($id);
            $this->close($request, ClientOrderCancelRequest::STATUS_STOPPED);
            $this->di['logger']->info('Stopped cancellation request #{id}', ['id' => $id]);
        });
    }

    /**
     * A failing request stays waiting for staff.
     */
    public function accept(int $id): void
    {
        $this->withRequestLock($id, fn () => $this->acceptLocked($id));
    }

    private function acceptLocked(int $id): void
    {
        $request = $this->find($id);
        $orderService = $this->di['mod_service']('Order');
        $order = $orderService->findByClientIdAndOrderId((int) $request->getClient()?->getId(), (int) $request->getOrder()?->getId());
        // Staff deleted or canceled it already: nothing is left to do.
        if ($order instanceof Order && $order->getStatus() !== Order::STATUS_CANCELED) {
            $reason = 'Cancellation request #' . $request->getId();
            if ($request->getType() === ClientOrderCancelRequest::TYPE_END_OF_PERIOD) {
                $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
                if ($subscriptionService->hasActiveSubscriptionForOrder($order)) {
                    if (!$subscriptionService->canCancelAtPeriodEndForOrder($order)) {
                        $this->di['logger']->warning('Order #{order_id}: cancel its gateway subscription at the gateway; it ends at the period end here.', ['order_id' => $order->getId()]);
                        $orderService->updateOrderMeta($order, [Service::META_CANCEL_AT_PERIOD_END => '1']);
                    } else {
                        $orderService->scheduleCancellationFromOrder($order, $reason);
                    }
                } else {
                    $orderService->updateOrderMeta($order, [Service::META_CANCEL_AT_PERIOD_END => '1']);
                }
            } else {
                $orderService->cancelFromOrder($order, $reason);
            }
        }

        $this->close($request, ClientOrderCancelRequest::STATUS_ACCEPTED);
        $this->di['logger']->info('Accepted cancellation request #{id}', ['id' => $id]);
    }

    /**
     * Runs at most once a day. A failing request stays waiting for staff.
     */
    public function runAutoProcess(): bool
    {
        $config = $this->di['mod']('order')->getConfig();
        if (empty($config['cancel_requests_auto'])) {
            return false;
        }

        $systemService = $this->di['mod_service']('system');
        $today = date('Y-m-d');
        if ($systemService->getParamValue('cancel_requests_last_day') === $today) {
            return true;
        }

        foreach ($this->pendingList() as $row) {
            try {
                $this->accept((int) ($row['id'] ?? 0));
            } catch (\Throwable $e) {
                $this->di['logger']->error('Order: cancellation request #{id} was not processed: {message}', ['id' => $row['id'] ?? 0, 'message' => $e->getMessage()]);
            }
        }

        $systemService->setParamValue('cancel_requests_last_day', $today, true);

        return true;
    }

    private function withRequestLock(int $id, \Closure $operation): void
    {
        $connection = $this->di['em']->getConnection();
        if ($connection->isTransactionActive()) {
            throw new InformationException('Cancellation request changes cannot run inside a database transaction.');
        }
        $name = 'fb:cancel-request:' . $id;
        $key = spl_object_id($connection) . ':' . $id;
        if (isset(self::$activeRequests[$key]) || !NamedLock::acquire($connection, $name, 0)) {
            throw new InformationException('Another request is being processed. Please try again.');
        }
        self::$activeRequests[$key] = true;

        try {
            $operation();
        } finally {
            unset(self::$activeRequests[$key]);
            NamedLock::release($connection, $name);
        }
    }

    private function find(int $id): ClientOrderCancelRequest
    {
        $request = $this->cancelRequestRepository->findOneBy(['id' => $id, 'status' => ClientOrderCancelRequest::STATUS_PENDING]);
        if (!$request instanceof ClientOrderCancelRequest) {
            throw new InformationException('This cancellation request is no longer waiting.');
        }

        $this->di['em']->refresh($request);
        if ($request->getStatus() !== ClientOrderCancelRequest::STATUS_PENDING) {
            throw new InformationException('This cancellation request is no longer waiting.');
        }

        return $request;
    }

    private function notify(ClientOrderCancelRequest $request, string $type): void
    {
        $id = (int) $request->getId();
        $order = $request->getOrder();
        $client = $request->getClient();
        $clientName = $client instanceof Client ? trim($client->getFirstName() . ' ' . $client->getLastName()) : '';

        try {
            $this->di['mod_service']('email')->sendTemplate([
                'to_client' => (int) $request->getClient()?->getId(),
                'code' => 'mod_order_cancel_request',
                'order' => ['id' => $order?->getId(), 'title' => $order?->getTitle(), 'expires_at' => $order?->getExpiresAt()?->format('Y-m-d H:i:s')],
                'request' => ['id' => $id, 'type' => $type],
            ]);
        } catch (\Throwable $e) {
            $this->di['logger']->warning('Cancellation request #{id}: client email not sent: {message}', ['id' => $id, 'message' => $e->getMessage()]);
        }

        try {
            $this->di['mod_service']('email')->sendTemplate([
                'to_staff' => true,
                'code' => 'mod_staff_order_cancel_request',
                'order' => [
                    'id' => $order?->getId(),
                    'title' => $order?->getTitle(),
                    'expires_at' => $order?->getExpiresAt()?->format('Y-m-d H:i:s'),
                    'url' => $this->di['url']->adminLink('order/manage/' . $order?->getId()),
                ],
                'client' => ['name' => $clientName],
                'request' => ['id' => $id],
            ]);
        } catch (\Throwable $e) {
            $this->di['logger']->warning('Cancellation request #{id}: staff email not sent: {message}', ['id' => $id, 'message' => $e->getMessage()]);
        }
    }

    private function close(ClientOrderCancelRequest $request, string $status): void
    {
        $this->di['em']->createQueryBuilder()
            ->update(ClientOrderCancelRequest::class, 'r')
            ->set('r.status', ':status')
            ->set('r.updatedAt', ':now')
            ->where('r.id = :id')
            ->andWhere('r.status = :pending')
            ->setParameter('status', $status)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('id', $request->getId())
            ->setParameter('pending', ClientOrderCancelRequest::STATUS_PENDING)
            ->getQuery()
            ->execute();
    }
}
