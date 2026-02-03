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

class HostingPlanRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p');

        if (!empty($data['search'])) {
            $qb->andWhere('p.name LIKE :search')
               ->setParameter('search', '%' . $data['search'] . '%');
        }

        $qb->orderBy('p.name', 'ASC');

        return $qb;
    }

    public function getPairs(): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.id', 'p.name')
            ->orderBy('p.name', 'ASC');

        $results = $qb->getQuery()->getResult();

        $pairs = [];
        foreach ($results as $result) {
            $pairs[$result['id']] = $result['name'];
        }

        return $pairs;
    }

    public function findByName(string $name): ?object
    {
        return $this->findOneBy(['name' => $name]);
    }
}
