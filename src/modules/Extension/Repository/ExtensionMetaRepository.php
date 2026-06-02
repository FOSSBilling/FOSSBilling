<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Extension\Repository;

use Box\Mod\Extension\Entity\ExtensionMeta;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class ExtensionMetaRepository extends EntityRepository
{
    public function createQueryBuilderForExtension(string $extension, string $alias = 'em'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->andWhere(sprintf('%s.extension = :extension', $alias))
            ->setParameter('extension', $extension);
    }

    public function findOneByExtensionAndId(string $extension, int $id): ?ExtensionMeta
    {
        return $this->findOneBy([
            'extension' => $extension,
            'id' => $id,
        ]);
    }

    public function findOneByExtensionAndScope(string $extension, ?string $metaKey = null, ?string $relType = null, ?string $relId = null): ?ExtensionMeta
    {
        $result = $this->findByExtensionAndScope($extension, $metaKey, $relType, $relId, ['id' => 'ASC'], 1);

        return $result[0] ?? null;
    }

    public function findByExtensionAndScope(string $extension, ?string $metaKey = null, ?string $relType = null, ?string $relId = null, array $orderBy = [], ?int $limit = null): array
    {
        $qb = $this->createQueryBuilderForExtension($extension);
        $this->applyScope($qb, 'em', $metaKey, $relType, $relId);

        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy(sprintf('em.%s', $field), $direction);
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function deleteByExtensionAndScope(string $extension, ?string $metaKey = null, ?string $relType = null, ?string $relId = null): int
    {
        $qb = $this->createQueryBuilder('em')
            ->delete()
            ->andWhere('em.extension = :extension')
            ->setParameter('extension', $extension);

        $this->applyScope($qb, 'em', $metaKey, $relType, $relId);

        return $qb->getQuery()->execute();
    }

    private function applyScope(QueryBuilder $qb, string $alias, ?string $metaKey, ?string $relType, ?string $relId): void
    {
        if ($metaKey !== null) {
            $qb->andWhere(sprintf('%s.metaKey = :metaKey', $alias))
                ->setParameter('metaKey', $metaKey);
        }

        if ($relType !== null) {
            $qb->andWhere(sprintf('%s.relType = :relType', $alias))
                ->setParameter('relType', $relType);
        }

        if ($relId !== null) {
            $qb->andWhere(sprintf('%s.relId = :relId', $alias))
                ->setParameter('relId', $relId);
        }
    }
}
