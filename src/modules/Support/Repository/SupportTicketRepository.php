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
     *  - `auth`              (string)  client or guest author filter
     *  - `order_id`          (int)     filter by related order
     *  - `subject`           (string)  LIKE on subject
     *  - `content`           (string)  LIKE on ticket messages
     *  - `name`              (string)  LIKE on author name
     *  - `email`             (string)  LIKE on author email
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

        if (($data['auth'] ?? null) === 'guest') {
            $qb->andWhere('t.clientId IS NULL')
                ->andWhere('t.accessHash IS NOT NULL');
        } elseif (($data['auth'] ?? null) === 'client') {
            $qb->andWhere('t.clientId IS NOT NULL');
        }

        if (!empty($data['name'])) {
            $qb->andWhere('t.authorName LIKE :author_name')
                ->setParameter('author_name', '%' . $data['name'] . '%');
        }

        if (!empty($data['email'])) {
            $qb->andWhere('t.authorEmail LIKE :author_email')
                ->setParameter('author_email', '%' . $data['email'] . '%');
        }

        if (!empty($data['subject'])) {
            $qb->andWhere('t.subject LIKE :filter_subject')
                ->setParameter('filter_subject', '%' . $data['subject'] . '%');
        }

        if (!empty($data['order_id'])) {
            $qb->andWhere('t.relType = :rel_type')
                ->andWhere('t.relId = :rel_id')
                ->setParameter('rel_type', SupportTicket::REL_TYPE_ORDER)
                ->setParameter('rel_id', (int) $data['order_id']);
        }

        if (!empty($data['content'])) {
            $qb->leftJoin('t.messages', 'filter_m')
                ->andWhere('filter_m.content LIKE :filter_content')
                ->setParameter('filter_content', '%' . $data['content'] . '%')
                ->distinct();
        }

        $helpdeskId = $data['support_helpdesk_id'] ?? $data['helpdesk_id'] ?? null;
        if ($helpdeskId !== null && $helpdeskId !== '') {
            $qb->andWhere('IDENTITY(t.helpdesk) = :helpdesk_id')
                ->setParameter('helpdesk_id', (int) $helpdeskId);
        }

        if (!empty($data['search'])) {
            if (is_numeric($data['search'])) {
                $qb->andWhere('t.id = :ticket_id')
                    ->setParameter('ticket_id', (int) $data['search']);
            } else {
                $search = '%' . $data['search'] . '%';
                $qb->leftJoin('t.messages', 'search_m')
                    ->andWhere('(search_m.content LIKE :search OR t.subject LIKE :search OR t.authorEmail LIKE :search OR t.authorName LIKE :search)')
                    ->setParameter('search', $search)
                    ->distinct();
            }
        }

        if (!empty($data['date_from'])) {
            $qb->andWhere('t.createdAt >= :date_from')
                ->setParameter('date_from', new \DateTime($data['date_from'] . ' 00:00:00'));
        }

        if (!empty($data['date_to'])) {
            $qb->andWhere('t.createdAt <= :date_to')
                ->setParameter('date_to', new \DateTime($data['date_to'] . ' 23:59:59'));
        }

        return $qb;
    }

    /**
     * Find a single ticket by id, throwing if it does not exist.
     */
    public function findOneByIdOrFail(int $id): SupportTicket
    {
        $ticket = $this->find($id);
        if (!$ticket instanceof SupportTicket) {
            throw new \FOSSBilling\InformationException('Ticket not found');
        }

        return $ticket;
    }

    /**
     * Find a single ticket owned by the given client.
     */
    public function findOneByClient(int $clientId, int $id): ?SupportTicket
    {
        return $this->findOneBy(['id' => $id, 'clientId' => $clientId]);
    }

    /**
     * @return SupportTicket[]
     */
    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }

    /**
     * @param list<int> $ids
     *
     * @return SupportTicket[]
     */
    public function findByIds(array $ids): array
    {
        return $ids === [] ? [] : $this->findBy(['id' => $ids]);
    }

    /**
     * Find a single ticket owned by the given client, throwing if it does not exist.
     */
    public function findOneByClientOrFail(int $clientId, int $id): SupportTicket
    {
        $ticket = $this->findOneByClient($clientId, $id);
        if (!$ticket instanceof SupportTicket) {
            throw new \FOSSBilling\InformationException('Ticket not found');
        }

        return $ticket;
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

    public function hasPendingTaskForClient(int $clientId, int $relId, string $relType, string $relTask): bool
    {
        return $this->findOneBy([
            'clientId' => $clientId,
            'relId' => $relId,
            'relType' => $relType,
            'relTask' => $relTask,
            'relStatus' => SupportTicket::REL_STATUS_PENDING,
        ]) instanceof SupportTicket;
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
     * Return a `[status => count]` map covering all tickets, grouped by status.
     *
     * @return array<string, int>
     */
    public function countGroupedByStatus(): array
    {
        $result = $this->createQueryBuilder('t')
            ->select('t.status, COUNT(t.id) AS cnt')
            ->groupBy('t.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[(string) $row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Return on_hold tickets whose helpdesk close_after window has elapsed.
     *
     * Implemented via DBAL because the predicate mixes a column argument into
     * `DATE_ADD(... INTERVAL ... HOUR)`, which is awkward to express in DQL.
     * The result is returned as associative rows to stay consistent with the
     * previous RedBean-based return shape.
     *
     * @return list<array<string, mixed>>
     */
    public function findExpiredOnHold(\DateTimeInterface $now): array
    {
        $sql = 'SELECT st.*
                FROM support_ticket AS st
                    LEFT JOIN support_helpdesk sh ON sh.id = st.support_helpdesk_id
                WHERE st.status = :status
                  AND DATE_ADD(st.updated_at, INTERVAL sh.close_after HOUR) < :now
                ORDER BY st.id ASC';

        return $this->getEntityManager()->getConnection()
            ->fetchAllAssociative($sql, [
                'status' => SupportTicket::STATUS_ONHOLD,
                'now' => $now->format('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Return raw ticket rows (associative arrays) for the supplied id list.
     *
     * Used by the performance-sensitive batch fetcher in
     * {@see \Box\Mod\Support\Service::getBatchForApi()}, which renders many
     * tickets at once for the client-area listing and intentionally avoids
     * hydrating entities for that path.
     *
     * @param list<int> $ids
     *
     * @return list<array<string, mixed>>
     */
    public function findBatchRowsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->getEntityManager()->getConnection()
            ->fetchAllAssociative(
                'SELECT * FROM support_ticket WHERE id IN (:ids)',
                ['ids' => $ids],
                ['ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
            );
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
