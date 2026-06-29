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
}
