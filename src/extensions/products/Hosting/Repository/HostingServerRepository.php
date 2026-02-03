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

class HostingServerRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s');

        if (!empty($data['search'])) {
            $qb->andWhere('s.name LIKE :search OR s.ip LIKE :search OR s.hostname LIKE :search')
               ->setParameter('search', '%' . $data['search'] . '%');
        }

        if (isset($data['active'])) {
            $qb->andWhere('s.active = :active')
               ->setParameter('active', (bool) $data['active']);
        }

        $qb->orderBy('s.name', 'ASC');

        return $qb;
    }

    public function getPairs(): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.id', 's.name')
            ->where('s.active = :active')
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC');

        $results = $qb->getQuery()->getResult();

        $pairs = [];
        foreach ($results as $result) {
            $pairs[$result['id']] = $result['name'];
        }

        return $pairs;
    }

    public function findActiveServers(): array
    {
        return $this->findBy(['active' => true]);
    }
}
