<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Product\Repository;

use Box\Mod\Product\Entity\Promo;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class PromoRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p');
        $now = new \DateTimeImmutable();

        if (!empty($data['id'])) {
            $qb->andWhere('p.id = :id')
                ->setParameter('id', (int) $data['id']);
        }

        if (!empty($data['search'])) {
            $qb->andWhere('p.code LIKE :search')
                ->setParameter('search', '%' . $data['search'] . '%');
        }

        switch ($data['status'] ?? null) {
            case 'active':
                $qb->andWhere('p.active = :active')
                    ->andWhere('(p.startAt IS NULL OR p.startAt <= :now)')
                    ->andWhere('(p.endAt IS NULL OR p.endAt >= :now)')
                    ->setParameter('active', true)
                    ->setParameter('now', $now);

                break;

            case 'not-started':
                $qb->andWhere('p.startAt IS NOT NULL')
                    ->andWhere('p.startAt > :now')
                    ->setParameter('now', $now);

                break;

            case 'expired':
                $qb->andWhere('p.endAt IS NOT NULL')
                    ->andWhere('p.endAt < :now')
                    ->setParameter('now', $now);

                break;
        }

        $qb->orderBy('p.id', 'ASC');

        return $qb;
    }

    public function findActiveByCode(string $code): ?Promo
    {
        return $this->findOneBy([
            'code' => $code,
            'active' => true,
        ], [
            'id' => 'ASC',
        ]);
    }

    public function incrementUsageIfAvailable(int $promoId, \DateTimeInterface $updatedAt): int
    {
        return $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE promo SET used = COALESCE(used, 0) + 1, updated_at = ? WHERE id = ? AND (maxuses = 0 OR maxuses IS NULL OR maxuses > COALESCE(used, 0))',
            [$updatedAt->format('Y-m-d H:i:s'), $promoId]
        );
    }

    public function decrementUsage(int $promoId, int $count, \DateTimeInterface $updatedAt): int
    {
        return $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE promo SET used = GREATEST(COALESCE(used, 0) - ?, 0), updated_at = ? WHERE id = ?',
            [$count, $updatedAt->format('Y-m-d H:i:s'), $promoId]
        );
    }

    public function countLinkedOrdersByPromoId(int $promoId): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(id) FROM client_order WHERE promo_id = ?',
            [$promoId]
        );
    }
}
