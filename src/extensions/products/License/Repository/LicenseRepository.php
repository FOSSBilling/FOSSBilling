<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\License\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class LicenseRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l');

        if (!empty($data['search'])) {
            $qb->andWhere('l.licenseKey LIKE :search OR l.clientId = :clientSearch')
               ->setParameter('search', '%' . $data['search'] . '%')
               ->setParameter('clientSearch', (int) $data['search']);
        }

        if (!empty($data['client_id'])) {
            $qb->andWhere('l.clientId = :clientId')
               ->setParameter('clientId', (int) $data['client_id']);
        }

        $qb->orderBy('l.id', 'DESC');

        return $qb;
    }

    public function findOneByLicenseKey(string $licenseKey): ?object
    {
        return $this->findOneBy(['licenseKey' => $licenseKey]);
    }

    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }
}
