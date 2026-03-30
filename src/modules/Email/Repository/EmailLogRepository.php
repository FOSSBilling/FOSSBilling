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

use Box\Mod\Email\Entity\EmailLog;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class EmailLogRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.id', 'DESC');

        if (!empty($data['search'])) {
            $search = '%' . $data['search'] . '%';
            $qb->andWhere(
                '(COALESCE(l.sender, \'\') LIKE :search
                OR COALESCE(l.recipients, \'\') LIKE :search
                OR COALESCE(l.subject, \'\') LIKE :search
                OR COALESCE(l.contentText, \'\') LIKE :search
                OR COALESCE(l.contentHtml, \'\') LIKE :search)'
            )->setParameter('search', $search);
        }

        if (array_key_exists('client_id', $data) && $data['client_id'] !== null && $data['client_id'] !== '') {
            $qb->andWhere('l.clientId = :clientId')
                ->setParameter('clientId', (int) $data['client_id']);
        }

        return $qb;
    }

    public function findOneForClientById(int $clientId, int $id): ?EmailLog
    {
        return $this->findOneBy([
            'id' => $id,
            'clientId' => $clientId,
        ]);
    }

    public function deleteByClientId(int $clientId): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.clientId = :clientId')
            ->setParameter('clientId', $clientId)
            ->getQuery()
            ->execute();
    }

    public function deleteOlderThan(\DateTimeInterface $cutoff): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt <= :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
