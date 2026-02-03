<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Domain\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use FOSSBilling\ProductType\Domain\Entity\Domain;

class DomainRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d');

        if (!empty($data['search'])) {
            $qb->andWhere('d.sld LIKE :search OR d.tld LIKE :search')
               ->setParameter('search', '%' . $data['search'] . '%');
        }

        if (!empty($data['client_id'])) {
            $qb->andWhere('d.clientId = :clientId')
               ->setParameter('clientId', (int) $data['client_id']);
        }

        if (!empty($data['action'])) {
            $qb->andWhere('d.action = :action')
               ->setParameter('action', $data['action']);
        }

        $qb->orderBy('d.id', 'DESC');

        return $qb;
    }

    public function findOneByOrderId(int $orderId): ?Domain
    {
        return null;
    }

    public function findByClientId(int $clientId): array
    {
        return $this->findBy(['clientId' => $clientId]);
    }

    public function findBySldAndTld(string $sld, string $tld): ?Domain
    {
        return $this->findOneBy(['sld' => $sld, 'tld' => $tld]);
    }

    public function getSearchQueryBuilderForAdminList(array $data = []): QueryBuilder
    {
        return $this->getSearchQueryBuilder($data);
    }
}
