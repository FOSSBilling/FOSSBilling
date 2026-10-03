<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Repository;

use Doctrine\ORM\EntityRepository;

class ClientGroupMembershipRepository extends EntityRepository
{
    /**
     * @return array<int>
     */
    public function getGroupIdsForClient(int $clientId): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.clientGroup) AS group_id')
            ->where('IDENTITY(m.client) = :client_id')
            ->setParameter('client_id', $clientId)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['group_id'], $rows);
    }
}
