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

use Box\Mod\Support\Entity\SupportTicketMessage;
use Doctrine\ORM\EntityRepository;

class SupportTicketMessageRepository extends EntityRepository
{
    /**
     * Find a single ticket message by id, throwing if it does not exist.
     */
    public function findOneByIdOrFail(int $id): SupportTicketMessage
    {
        $message = $this->find($id);
        if (!$message instanceof SupportTicketMessage) {
            throw new \FOSSBilling\InformationException('Ticket message not found');
        }

        return $message;
    }

    /**
     * Return all messages belonging to a ticket, oldest first.
     *
     * @return SupportTicketMessage[]
     */
    public function findByTicketId(int $ticketId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.ticket = :tid')
            ->setParameter('tid', $ticketId)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $ids
     *
     * @return SupportTicketMessage[]
     */
    public function findByIds(array $ids): array
    {
        return $ids === [] ? [] : $this->findBy(['id' => $ids]);
    }

    /**
     * Return the first (oldest) message on a ticket — used to show a preview
     * snippet in ticket listings. Returns null if the ticket has no messages.
     */
    public function findFirstByTicketId(int $ticketId): ?SupportTicketMessage
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.ticket = :tid')
            ->setParameter('tid', $ticketId)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count messages on a ticket — used to gate "first reply" notifications
     * and similar rules.
     */
    public function countByTicketId(int $ticketId): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.ticket = :tid')
            ->setParameter('tid', $ticketId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Return a `[ticket_id => reply_count]` map for the supplied ticket ids.
     *
     * Implemented via DBAL because the calling code operates on raw rows (not
     * entities) in a performance-sensitive batch path, and a GROUP BY over the
     * entity manager would hydrate every message.
     *
     * @param list<int> $ticketIds
     *
     * @return array<int, int>
     */
    public function countRepliesByTicketIds(array $ticketIds): array
    {
        if (empty($ticketIds)) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()
            ->fetchAllAssociative(
                'SELECT support_ticket_id, COUNT(id) AS counter
                 FROM support_ticket_message
                 WHERE support_ticket_id IN (:ids)
                 GROUP BY support_ticket_id',
                ['ids' => $ticketIds],
                ['ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
            );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['support_ticket_id']] = (int) $row['counter'];
        }

        return $counts;
    }

    /**
     * Return the lowest message id for each supplied ticket id.
     *
     * Used by the batch ticket fetcher to pull a single "first message" per
     * ticket without hydrating the entire thread.
     *
     * @param list<int> $ticketIds
     *
     * @return array<int, int> `[ticket_id => first_message_id]`
     */
    public function findFirstIdsByTicketIds(array $ticketIds): array
    {
        if (empty($ticketIds)) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()
            ->fetchAllAssociative(
                'SELECT support_ticket_id, MIN(id) AS message_id
                 FROM support_ticket_message
                 WHERE support_ticket_id IN (:ids)
                 GROUP BY support_ticket_id',
                ['ids' => $ticketIds],
                ['ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
            );

        $first = [];
        foreach ($rows as $row) {
            $first[(int) $row['support_ticket_id']] = (int) $row['message_id'];
        }

        return $first;
    }
}
