<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Security\Repository;

use Box\Mod\Security\Entity\Block;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class BlockRepository extends EntityRepository
{
    /**
     * Find an active post by its slug
     * @param string $slug
     * @return Block|null
     */
    public function findByAddress(string $slug): ?Block
    {
        return $this->findOneBy([
            'slug'   => $slug,
            'status' => Block::STATUS_ACTIVE,
        ]);
    }

    /**
     * Delete blocks by a list of IDs in one go.
     *
     * @param int[] $ids
     * @return int Number of affected rows
     */
    public function deleteByIds(array $ids): int
    {
        return $this->createQueryBuilder('p')
            ->delete()
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
