<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support\Repository;

use Box\Mod\Support\Entity\Helpdesk;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class HelpdeskRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('h')
            ->orderBy('h.id', 'DESC');

        if (isset($data['search']) && trim((string) $data['search']) !== '') {
            $search = '%' . mb_strtolower(trim((string) $data['search'])) . '%';
            $qb->andWhere('(LOWER(h.name) LIKE :search OR LOWER(h.email) LIKE :search OR LOWER(h.signature) LIKE :search)')
                ->setParameter('search', $search);
        }

        return $qb;
    }

    /**
     * @return array<int, string|null>
     */
    public function getPairs(): array
    {
        $rows = $this->createQueryBuilder('h')
            ->select('h.id, h.name')
            ->orderBy('h.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $pairs = [];
        foreach ($rows as $row) {
            $pairs[(int) $row['id']] = $row['name'];
        }

        return $pairs;
    }

    /**
     * @param list<int> $ids
     *
     * @return Helpdesk[]
     */
    public function findByIds(array $ids): array
    {
        return $ids === [] ? [] : $this->findBy(['id' => $ids]);
    }

    public function countTickets(int $helpdeskId): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(id) FROM support_ticket WHERE support_helpdesk_id = :helpdesk_id',
            ['helpdesk_id' => $helpdeskId]
        );
    }

    public function getDefault(): Helpdesk
    {
        $helpdesk = $this->findOneBy([], ['id' => 'ASC']);
        if ($helpdesk instanceof Helpdesk) {
            return $helpdesk;
        }

        $helpdesk = (new Helpdesk())
            ->setName('General')
            ->setCloseAfter(24)
            ->setCanReopen(false);

        $this->getEntityManager()->persist($helpdesk);
        $this->getEntityManager()->flush();

        return $helpdesk;
    }
}
