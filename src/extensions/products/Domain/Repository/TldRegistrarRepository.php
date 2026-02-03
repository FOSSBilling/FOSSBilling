<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Domain\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class TldRegistrarRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r');

        if (!empty($data['search'])) {
            $qb->andWhere('r.name LIKE :search OR r.registrar LIKE :search')
               ->setParameter('search', '%' . $data['search'] . '%');
        }

        $qb->orderBy('r.name', 'ASC');

        return $qb;
    }

    public function getPairs(): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.id', 'r.name')
            ->orderBy('r.name', 'ASC');

        $results = $qb->getQuery()->getResult();

        $pairs = [];
        foreach ($results as $result) {
            $pairs[$result['id']] = $result['name'];
        }

        return $pairs;
    }

    public function findOneByName(string $name): ?object
    {
        return $this->findOneBy(['name' => $name]);
    }
}
