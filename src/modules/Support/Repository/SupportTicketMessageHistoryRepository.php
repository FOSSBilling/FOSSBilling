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

use Box\Mod\Support\Entity\SupportTicketMessageHistory;
use Doctrine\ORM\EntityRepository;

class SupportTicketMessageHistoryRepository extends EntityRepository
{
    /**
     * Return all recorded revisions of a message, most recent edit first.
     *
     * @return SupportTicketMessageHistory[]
     */
    public function findByMessageId(int $messageId): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.message = :mid')
            ->setParameter('mid', $messageId)
            ->orderBy('h.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
