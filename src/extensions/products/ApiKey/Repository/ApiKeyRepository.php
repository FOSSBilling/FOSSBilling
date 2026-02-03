<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\ApiKey\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class ApiKeyRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('k');

        if (!empty($data['search'])) {
            $qb->andWhere('k.apiKey LIKE :search OR k.clientId = :clientSearch')
               ->setParameter('search', '%' . $data['search'] . '%')
               ->setParameter('clientSearch', (int) $data['search']);
        }

        if (!empty($data['client_id'])) {
            $qb->andWhere('k.clientId = :clientId')
               ->setParameter('clientId', (int) $data['client_id']);
        }

        $qb->orderBy('k.id', 'DESC');

        return $qb;
    }

    public function findOneByApiKey(string $apiKey): ?object
    {
        return $this->findOneBy(['apiKey' => $apiKey]);
    }

    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }
}
