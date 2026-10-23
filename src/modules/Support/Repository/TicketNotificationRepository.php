<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Support\Repository;

use Box\Mod\Support\Entity\SupportTicketMessage;
use Box\Mod\Support\Entity\TicketNotification;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use FOSSBilling\InformationException;

class TicketNotificationRepository extends EntityRepository
{
    private function forClient(int $clientId): QueryBuilder
    {
        // Check current ticket ownership too: a reassigned ticket must disappear from the old account.
        return $this->createQueryBuilder('n')
            ->innerJoin('n.message', 'm')
            ->innerJoin('m.ticket', 't')
            ->where('n.clientId = :client AND t.clientId = :client')
            ->setParameter('client', $clientId);
    }

    public function summary(int $clientId): array
    {
        $row = $this->forClient($clientId)
            ->select('SUM(CASE WHEN n.readAt IS NULL THEN 1 ELSE 0 END) AS unread_count, MAX(n.id) AS latest_id')
            ->getQuery()->getSingleResult();

        return ['unread_count' => (int) $row['unread_count'], 'latest_id' => (int) $row['latest_id']];
    }

    /** @return list<TicketNotification> */
    public function recent(int $clientId): array
    {
        return $this->forClient($clientId)
            ->addSelect('m', 't', 'CASE WHEN n.readAt IS NULL THEN 0 ELSE 1 END AS HIDDEN read_order')
            ->orderBy('read_order', 'ASC')->addOrderBy('n.id', 'DESC')
            ->setMaxResults(10)->getQuery()->getResult();
    }

    public function markRead(int $clientId, int $id): void
    {
        $notification = $this->forClient($clientId)->andWhere('n.id = :id')->setParameter('id', $id)
            ->getQuery()->getOneOrNullResult();
        if (!$notification instanceof TicketNotification) {
            throw new InformationException('Notification not found');
        }

        $this->updateReadAt($clientId)->andWhere('n.id = :id')->setParameter('id', $id)->getQuery()->execute();
    }

    public function markAllRead(int $clientId, int $throughId): void
    {
        // Bound the update to the feed the user saw; a concurrent reply must stay unread.
        $this->updateReadAt($clientId)->andWhere('n.id <= :through')->setParameter('through', $throughId)->getQuery()->execute();
    }

    public function markTicketRead(int $clientId, int $ticketId, int $throughMessageId): void
    {
        $messages = $this->getEntityManager()->createQueryBuilder()->select('m.id')
            ->from(SupportTicketMessage::class, 'm')->innerJoin('m.ticket', 't')
            ->where('t.id = :ticket AND t.clientId = :client AND m.id <= :through');

        $this->updateReadAt($clientId)->andWhere('n.message IN (' . $messages->getDQL() . ')')
            ->setParameter('ticket', $ticketId)->setParameter('through', $throughMessageId)->getQuery()->execute();
    }

    private function updateReadAt(int $clientId): QueryBuilder
    {
        return $this->getEntityManager()->createQueryBuilder()->update(TicketNotification::class, 'n')
            ->set('n.readAt', ':now')->where('n.clientId = :client AND n.readAt IS NULL')
            ->setParameter('client', $clientId)->setParameter('now', new \DateTimeImmutable(), Types::DATETIME_IMMUTABLE);
    }
}
