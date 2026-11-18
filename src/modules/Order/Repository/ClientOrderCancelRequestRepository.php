<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Order\Repository;

use Box\Mod\Order\Entity\ClientOrderCancelRequest;
use Doctrine\ORM\EntityRepository;

class ClientOrderCancelRequestRepository extends EntityRepository
{
    public function findPendingByOrderId(int $orderId): ?ClientOrderCancelRequest
    {
        $request = $this->findOneBy(
            ['order' => $this->getEntityManager()->getReference(\Box\Mod\Order\Entity\Order::class, $orderId), 'status' => ClientOrderCancelRequest::STATUS_PENDING],
            ['id' => 'ASC']
        );

        return $request instanceof ClientOrderCancelRequest ? $request : null;
    }

    /**
     * Waiting requests, oldest first, with their service and client.
     *
     * @return list<array<string, mixed>>
     */
    public function getPendingList(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.id', 'r.type', 'r.reason', 'r.status', 'r.createdAt AS created_at', 'o.id AS client_order_id', 'o.title AS order_title', 'o.status AS order_status', 'o.expiresAt AS expires_at', 'c.id AS client_id', 'c.firstName AS client_first_name', 'c.lastName AS client_last_name')
            ->leftJoin('r.order', 'o')
            ->leftJoin('r.client', 'c')
            ->andWhere('r.status = :status')
            ->setParameter('status', ClientOrderCancelRequest::STATUS_PENDING)
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /** @return list<int> */
    public function getAutomaticRequestIds(): array
    {
        return array_map(intval(...), array_column($this->createQueryBuilder('r')
            ->select('MIN(r.id) AS id')
            ->where('r.status = :status')
            ->setParameter('status', ClientOrderCancelRequest::STATUS_PENDING)
            ->groupBy('r.order')
            ->orderBy('id', 'ASC')
            ->getQuery()->getArrayResult(), 'id'));
    }
}
