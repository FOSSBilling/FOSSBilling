<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Massmailer\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class MassmailerMessageRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('m');

        $status = $data['status'] ?? null;
        if ($status !== null && $status !== '') {
            $qb->andWhere('m.status = :status')
                ->setParameter('status', $status);
        }

        $search = $data['search'] ?? null;
        if ($search !== null && $search !== '') {
            $qb->andWhere('m.subject LIKE :search OR m.content LIKE :search OR m.fromEmail LIKE :search OR m.fromName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('m.createdAt', 'DESC');

        return $qb;
    }
}
