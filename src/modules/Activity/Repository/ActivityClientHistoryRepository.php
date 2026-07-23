<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Activity\Repository;

use Box\Mod\Activity\Entity\ActivityClientHistory;
use Doctrine\ORM\EntityRepository;

class ActivityClientHistoryRepository extends EntityRepository
{
    public function deleteByClientId(int $clientId): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->delete(ActivityClientHistory::class, 'h')
            ->where('h.clientId = :client_id')
            ->setParameter('client_id', $clientId)
            ->getQuery()
            ->execute();
    }
}
