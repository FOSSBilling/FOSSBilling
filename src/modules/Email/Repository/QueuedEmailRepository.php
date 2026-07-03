<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Email\Repository;

use Box\Mod\Email\Entity\QueuedEmail;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class QueuedEmailRepository extends EntityRepository
{
    /**
     * Build a QueryBuilder for the email queue listing (admin UI).
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('q')
            ->orderBy('q.priority', 'DESC')
            ->addOrderBy('q.id', 'ASC');

        if (!empty($data['status'])) {
            $qb->andWhere('q.status = :status')
                ->setParameter('status', $data['status']);
        }

        if (!empty($data['client_id'])) {
            $qb->andWhere('q.clientId = :client_id')
                ->setParameter('client_id', (int) $data['client_id']);
        }

        return $qb;
    }

    /**
     * Return the batch of pending queue items the cron should attempt to send.
     *
     * Items are ordered by priority (highest first) and by ID (FIFO within a
     * priority bucket). Includes legacy `unsent`, `pending`, and `failed`
     * status so that transient failures and pre-migration rows get attempted.
     *
     * @return QueuedEmail[]
     */
    public function findDueBatch(int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('q')
            ->andWhere('q.status IN (:statuses)')
            ->setParameter('statuses', [QueuedEmail::STATUS_UNSENT, QueuedEmail::STATUS_PENDING, QueuedEmail::STATUS_FAILED])
            ->orderBy('q.priority', 'DESC')
            ->addOrderBy('q.id', 'ASC');

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }
}
