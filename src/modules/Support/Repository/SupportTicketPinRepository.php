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

use Doctrine\ORM\EntityRepository;

class SupportTicketPinRepository extends EntityRepository
{
    /**
     * @return list<int>
     */
    public function findAllIds(): array
    {
        return array_map(
            intval(...),
            $this->createQueryBuilder('p')
                ->select('p.ticketId')
                ->orderBy('p.ticketId', 'DESC')
                ->getQuery()
                ->getSingleColumnResult()
        );
    }
}
