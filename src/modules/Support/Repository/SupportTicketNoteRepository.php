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

use Box\Mod\Support\Entity\SupportTicketNote;
use Doctrine\ORM\EntityRepository;

class SupportTicketNoteRepository extends EntityRepository
{
    /**
     * Find a single ticket note by id, throwing if it does not exist.
     */
    public function findOneByIdOrFail(int $id): SupportTicketNote
    {
        $note = $this->find($id);
        if (!$note instanceof SupportTicketNote) {
            throw new \FOSSBilling\InformationException('Note not found');
        }

        return $note;
    }

    /**
     * @return SupportTicketNote[]
     */
    public function findByTicketId(int $ticketId): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.ticket = :tid')
            ->setParameter('tid', $ticketId)
            ->orderBy('n.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
