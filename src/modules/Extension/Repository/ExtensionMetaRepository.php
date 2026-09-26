<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Extension\Repository;

use Box\Mod\Extension\Entity\ExtensionMeta;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

class ExtensionMetaRepository extends EntityRepository
{
    /** @var array<string, ExtensionMeta|null> */
    private array $extensionMetaByScopeCache = [];

    private bool $extensionMetaWritesScheduled = false;

    private ?Connection $connection = null;

    /** @param ClassMetadata<ExtensionMeta> $class */
    public function __construct(EntityManagerInterface $entityManager, ClassMetadata $class)
    {
        parent::__construct($entityManager, $class);
        $this->connection = $entityManager->getConnection();
        $entityManager->getEventManager()->addEventListener(
            [Events::onClear, Events::onFlush, Events::postFlush],
            $this,
        );
    }

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
        $cacheKey = serialize([$extension, $metaKey, $relType, $relId]);
        $useCache = $this->canUseLookupCache();
        if ($useCache && array_key_exists($cacheKey, $this->extensionMetaByScopeCache)) {
            return $this->extensionMetaByScopeCache[$cacheKey];
        }

        $results = $this->findByExtensionAndScope($extension, $metaKey, $relType, $relId, ['id' => 'ASC'], 1);
        $meta = $results[0] ?? null;

        if ($useCache) {
            $this->extensionMetaByScopeCache[$cacheKey] = $meta;
        }

        return $meta;
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
        // DQL deletes bypass ORM lifecycle events.
        $this->clearQueryCache();
        $qb = $this->createQueryBuilder('em')
            ->delete()
            ->andWhere('em.extension = :extension')
            ->setParameter('extension', $extension);

        $this->applyScope($qb, 'em', $metaKey, $relType, $relId);

        return $qb->getQuery()->execute();
    }

    public function onClear(OnClearEventArgs $event): void
    {
        if ($event->getObjectManager() === $this->getEntityManager()) {
            $this->clearQueryCache();
            $this->extensionMetaWritesScheduled = false;
        }
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        if ($event->getObjectManager() !== $this->getEntityManager()) {
            return;
        }

        $unitOfWork = $this->getEntityManager()->getUnitOfWork();
        foreach ([
            $unitOfWork->getScheduledEntityInsertions(),
            $unitOfWork->getScheduledEntityUpdates(),
            $unitOfWork->getScheduledEntityDeletions(),
        ] as $entities) {
            foreach ($entities as $entity) {
                if ($entity instanceof ExtensionMeta) {
                    $this->clearQueryCache();
                    $this->extensionMetaWritesScheduled = true;

                    return;
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if ($event->getObjectManager() === $this->getEntityManager() && $this->extensionMetaWritesScheduled) {
            $this->clearQueryCache();
            $this->extensionMetaWritesScheduled = false;
        }
    }

    private function clearQueryCache(): void
    {
        $this->extensionMetaByScopeCache = [];
    }

    private function canUseLookupCache(): bool
    {
        if ($this->connection?->isTransactionActive()) {
            // An outer transaction can roll back without an ORM invalidation event.
            $this->clearQueryCache();

            return false;
        }

        return true;
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
