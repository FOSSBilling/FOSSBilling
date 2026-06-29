<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support\Repository;

use Box\Mod\Support\Entity\SupportTicket;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class SupportTicketRepository extends EntityRepository
{
    /**
     * Build a QueryBuilder for the admin ticket listing.
     *
     * Accepted keys in `$data`:
     *  - `id`                (int)     exact id match
     *  - `status`            (string)  exact status match
     *  - `priority`          (int)     exact priority match
     *  - `client_id`         (int)     filter by client
     *  - `support_helpdesk_id` (int)   filter by helpdesk
     *  - `helpdesk_id`       (int)     filter by helpdesk (legacy alias)
     *  - `search`            (string)  LIKE on subject / author_email / author_name
     *  - `date_from`         (string)  created_at lower bound (Y-m-d)
     *  - `date_to`           (string)  created_at upper bound (Y-m-d)
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.id', 'DESC');

        if (!empty($data['id'])) {
            $qb->andWhere('t.id = :id')
                ->setParameter('id', (int) $data['id']);
        }

        if (!empty($data['status'])) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $data['status']);
        }

        if (isset($data['priority']) && $data['priority'] !== '') {
            $qb->andWhere('t.priority = :priority')
                ->setParameter('priority', (int) $data['priority']);
        }

        if (!empty($data['client_id'])) {
            $qb->andWhere('t.clientId = :client_id')
                ->setParameter('client_id', (int) $data['client_id']);
        }

        $helpdeskId = $data['support_helpdesk_id'] ?? $data['helpdesk_id'] ?? null;
        if ($helpdeskId !== null && $helpdeskId !== '') {
            $qb->andWhere('IDENTITY(t.helpdesk) = :helpdesk_id')
                ->setParameter('helpdesk_id', (int) $helpdeskId);
        }

        if (!empty($data['search'])) {
            $search = '%' . $data['search'] . '%';
            $qb->andWhere('(t.subject LIKE :search OR t.authorEmail LIKE :search OR t.authorName LIKE :search)')
                ->setParameter('search', $search);
        }

        if (!empty($data['date_from'])) {
            $qb->andWhere('t.createdAt >= :date_from')
                ->setParameter('date_from', $data['date_from'] . ' 00:00:00');
        }

        if (!empty($data['date_to'])) {
            $qb->andWhere('t.createdAt <= :date_to')
                ->setParameter('date_to', $data['date_to'] . ' 23:59:59');
        }

        return $qb;
    }

    /**
     * Find a single ticket owned by the given client.
     */
    public function findOneByClient(int $clientId, int $id): ?SupportTicket
    {
        return $this->findOneBy(['id' => $id, 'clientId' => $clientId]);
    }

    /**
     * Find a single ticket by its public access hash, optionally restricted
     * to a null client (i.e. guest tickets).
     */
    public function findOneByAccessHash(string $hash, ?int $clientId = null): ?SupportTicket
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.accessHash = :hash')
            ->setParameter('hash', $hash);

        if ($clientId === null) {
            $qb->andWhere('t.clientId IS NULL');
        } else {
            $qb->andWhere('t.clientId = :cid')
                ->setParameter('cid', $clientId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Return the N most recent tickets, used by the dashboard widget.
     *
     * @return SupportTicket[]
     */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the first unassigned ticket matching a `rel_type / rel_id / rel_task`
     * triple. Used by SupportTicket to coalesce duplicate automated tickets
     * (e.g. multiple "order cancel" requests for the same order).
     */
    public function findOneUnassignedByRel(string $relType, int $relId, string $relTask): ?SupportTicket
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.relType = :rel_type')
            ->andWhere('t.relId = :rel_id')
            ->andWhere('t.relTask = :rel_task')
            ->andWhere('t.clientId IS NULL')
            ->setParameter('rel_type', $relType)
            ->setParameter('rel_id', $relId)
            ->setParameter('rel_task', $relTask)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count tickets whose status is in the supplied set.
     *
     * @param string ...$statuses One or more status values
     */
    public function countByStatus(string ...$statuses): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)');

        if ($statuses !== []) {
            $qb->andWhere('t.status IN (:statuses)')
                ->setParameter('statuses', $statuses);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count how many active (open / on_hold) tickets reference a given order id.
     */
    public function countActiveTicketsForOrder(int $orderId): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.relType = :type')
            ->andWhere('t.relId = :rid')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('type', SupportTicket::REL_TYPE_ORDER)
            ->setParameter('rid', $orderId)
            ->setParameter('statuses', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_ONHOLD])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
