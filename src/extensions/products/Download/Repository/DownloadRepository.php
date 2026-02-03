<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Download\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class DownloadRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d');

        if (!empty($data['search'])) {
            $qb->andWhere('d.filename LIKE :search OR d.clientId = :clientSearch')
               ->setParameter('search', '%' . $data['search'] . '%')
               ->setParameter('clientSearch', (int) $data['search']);
        }

        if (!empty($data['client_id'])) {
            $qb->andWhere('d.clientId = :clientId')
               ->setParameter('clientId', (int) $data['client_id']);
        }

        $qb->orderBy('d.id', 'DESC');

        return $qb;
    }

    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }

    public function findByFilename(string $filename): ?object
    {
        return $this->findOneBy(['filename' => $filename]);
    }
}
