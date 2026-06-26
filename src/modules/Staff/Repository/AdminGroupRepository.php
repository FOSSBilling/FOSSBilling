<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Staff\Repository;

use Box\Mod\Staff\Entity\AdminGroup;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class AdminGroupRepository extends EntityRepository
{
    public function findSuperAdministratorGroup(): ?AdminGroup
    {
        return $this->findOneBy(['systemName' => AdminGroup::SYSTEM_SUPER_ADMIN]);
    }

    public function findById(int $id): ?AdminGroup
    {
        return $this->find($id);
    }

    /**
     * @return array<int, string|null>
     */
    public function getPairs(): array
    {
        $rows = $this->createQueryBuilder('g')
            ->select('g.id, g.name')
            ->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $pairs = [];
        foreach ($rows as $row) {
            $pairs[(int) $row['id']] = $row['name'];
        }

        return $pairs;
    }

    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('g');

        if (!empty($data['search'])) {
            $qb->andWhere('g.name LIKE :search')
                ->setParameter('search', '%' . $data['search'] . '%');
        }

        return $qb->orderBy('g.id', 'ASC');
    }
}
