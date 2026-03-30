<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Email\Repository;

use Box\Mod\Email\Entity\EmailQueue;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class EmailQueueRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('q')
            ->orderBy('q.updatedAt', 'DESC');

        if (!empty($data['search'])) {
            $search = '%' . $data['search'] . '%';
            $qb->andWhere(
                '(COALESCE(q.recipient, \'\') LIKE :search
                OR COALESCE(q.subject, \'\') LIKE :search
                OR COALESCE(q.content, \'\') LIKE :search
                OR COALESCE(q.toName, \'\') LIKE :search)'
            )->setParameter('search', $search);
        }

        return $qb;
    }

    /**
     * @return EmailQueue[]
     */
    public function findUnsent(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('q')
            ->where('q.status = :status')
            ->setParameter('status', EmailQueue::STATUS_UNSENT)
            ->orderBy('q.createdAt', 'ASC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }
}
