<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Hosting\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class HostingRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('h');

        if (!empty($data['search'])) {
            $qb->andWhere('h.sld LIKE :search OR h.tld LIKE :search OR h.username LIKE :search')
               ->setParameter('search', '%' . $data['search'] . '%');
        }

        if (!empty($data['client_id'])) {
            $qb->andWhere('h.clientId = :clientId')
               ->setParameter('clientId', (int) $data['client_id']);
        }

        if (!empty($data['server_id'])) {
            $qb->andWhere('h.server = :serverId')
               ->setParameter('serverId', (int) $data['server_id']);
        }

        $qb->orderBy('h.id', 'DESC');

        return $qb;
    }

    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }

    public function findByUsername(string $username): ?object
    {
        return $this->findOneBy(['username' => $username]);
    }

    public function countActiveAccountsOnServer(int $serverId): int
    {
        return $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->where('h.server = :serverId')
            ->setParameter('serverId', $serverId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
