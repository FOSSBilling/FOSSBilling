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
use FOSSBilling\ProductType\Domain\Entity\Tld;

class TldRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t');

        if (!empty($data['search'])) {
            $qb->andWhere('t.tld LIKE :search')
               ->setParameter('search', '%' . $data['search'] . '%');
        }

        if (isset($data['active'])) {
            $qb->andWhere('t.active = :active')
               ->setParameter('active', (bool) $data['active']);
        }

        if (!empty($data['registrar_id'])) {
            $qb->andWhere('t.registrar = :registrarId')
               ->setParameter('registrarId', (int) $data['registrar_id']);
        }

        $qb->orderBy('t.tld', 'ASC');

        return $qb;
    }

    public function findOneByTld(string $tld): ?Tld
    {
        return $this->findOneBy(['tld' => $tld]);
    }

    public function getAvailableTlds(): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t.tld', 't.priceRegistration', 't.priceRenew', 't.priceTransfer')
            ->where('t.active = :active')
            ->setParameter('active', true)
            ->orderBy('t.tld', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function findByRegistrarId(int $registrarId): array
    {
        return $this->findBy(['registrar' => $registrarId]);
    }
}
