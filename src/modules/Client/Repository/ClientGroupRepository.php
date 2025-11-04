<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Repository;

use Box\Mod\Client\Entity\ClientGroup;
use Doctrine\ORM\EntityRepository;

/**
 * Repository for ClientGroup entity.
 *
 * @extends EntityRepository<ClientGroup>
 */
class ClientGroupRepository extends EntityRepository
{
    /**
     * Get client group pairs for dropdowns (id => title).
     *
     * @return array<int, string>
     */
    public function getPairs(): array
    {
        $qb = $this->createQueryBuilder('cg')
            ->select('cg.id', 'cg.title')
            ->orderBy('cg.title', 'ASC');

        $results = $qb->getQuery()->getResult();

        $pairs = [];
        foreach ($results as $row) {
            $pairs[$row['id']] = $row['title'];
        }

        return $pairs;
    }

    /**
     * Find group by title.
     *
     * @param string $title Group title
     */
    public function findOneByTitle(string $title): ?ClientGroup
    {
        return $this->findOneBy(['title' => $title]);
    }
}
