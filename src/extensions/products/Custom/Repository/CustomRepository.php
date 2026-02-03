<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Custom\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class CustomRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        if (!empty($data['search'])) {
            $qb->andWhere('c.clientId = :clientSearch OR c.plugin LIKE :pluginSearch')
               ->setParameter('clientSearch', (int) $data['search'])
               ->setParameter('pluginSearch', '%' . $data['search'] . '%');
        }

        if (!empty($data['client_id'])) {
            $qb->andWhere('c.clientId = :clientId')
               ->setParameter('clientId', (int) $data['client_id']);
        }

        $qb->orderBy('c.id', 'DESC');

        return $qb;
    }

    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }

    public function findByPlugin(string $plugin): array
    {
        return $this->findBy(['plugin' => $plugin]);
    }
}
