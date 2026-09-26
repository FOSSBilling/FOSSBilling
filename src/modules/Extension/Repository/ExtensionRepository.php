<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Extension\Repository;

use Box\Mod\Extension\Entity\Extension;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

class ExtensionRepository extends EntityRepository
{
    /** @var array<string, string[]> */
    private array $installedNamesByTypeCache = [];

    /** @var array<string, array<string, bool>> */
    private array $activeByTypeAndNameCache = [];

    private bool $extensionWritesScheduled = false;

    private ?Connection $connection = null;

    /** @param ClassMetadata<Extension> $class */
    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->connection = $em->getConnection();

        $em->getEventManager()->addEventListener(
            [Events::onFlush, Events::postFlush, Events::onClear],
            $this,
        );
    }

    /**
     * Build a QueryBuilder for filtering installed extensions.
     *
     * Accepted keys in `$data`:
     *  - `type`   (string)  exact match on `type`
     *  - `status` (string)  exact match on `status`
     *  - `search` (string)  LIKE on `name`
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e');

        if (!empty($data['type'])) {
            $qb->andWhere('e.type = :type')
                ->setParameter('type', $data['type']);
        }

        if (!empty($data['status'])) {
            $qb->andWhere('e.status = :status')
                ->setParameter('status', $data['status']);
        }

        if (!empty($data['search'])) {
            $qb->andWhere('e.name LIKE :search')
                ->setParameter('search', '%' . $data['search'] . '%');
        }

        $qb->orderBy('e.type', 'ASC')
            ->addOrderBy('e.status', 'DESC')
            ->addOrderBy('e.id', 'ASC');

        return $qb;
    }

    /**
     * Find a single extension by `(type, name)`.
     */
    public function findOneByTypeAndName(string $type, string $name): ?Extension
    {
        return $this->findOneBy(['type' => $type, 'name' => $name]);
    }

    /**
     * @return Extension[]
     */
    public function findByType(string $type): array
    {
        return $this->findBy(['type' => $type]);
    }

    /**
     * Return all extensions of a given type that are currently installed.
     *
     * @return Extension[]
     */
    public function findInstalledByType(string $type): array
    {
        return $this->findBy(['type' => $type, 'status' => Extension::STATUS_INSTALLED]);
    }

    /**
     * @return string[]
     */
    public function findInstalledNamesByType(string $type): array
    {
        $useCache = $this->canUseRequestCache();
        if ($useCache && isset($this->installedNamesByTypeCache[$type])) {
            return $this->installedNamesByTypeCache[$type];
        }

        $names = array_map(
            static fn (Extension $extension): string => (string) $extension->getName(),
            $this->findInstalledByType($type),
        );
        if ($useCache) {
            $this->installedNamesByTypeCache[$type] = $names;
        }

        return $names;
    }

    /**
     * Check whether an extension with the given type and name exists and is
     * currently marked as installed.
     */
    public function existsActiveByTypeAndName(string $type, string $name): bool
    {
        $useCache = $this->canUseRequestCache();
        if ($useCache && array_key_exists($name, $this->activeByTypeAndNameCache[$type] ?? [])) {
            return $this->activeByTypeAndNameCache[$type][$name];
        }

        if ($useCache && $name !== '' && in_array($name, $this->findInstalledNamesByType($type), true)) {
            return true;
        }

        // Let the database resolve case-insensitive matches absent from the cached list.
        $active = $this->findOneBy([
            'type' => $type,
            'name' => $name,
            'status' => Extension::STATUS_INSTALLED,
        ]) !== null;
        if ($useCache) {
            $this->activeByTypeAndNameCache[$type][$name] = $active;
        }

        return $active;
    }

    public function clearInstalledNamesCache(): void
    {
        $this->installedNamesByTypeCache = [];
        $this->activeByTypeAndNameCache = [];
    }

    private function canUseRequestCache(): bool
    {
        if ($this->connection?->isTransactionActive()) {
            // An outer transaction can roll back without an ORM invalidation event.
            $this->clearInstalledNamesCache();

            return false;
        }

        return true;
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ([
            $unitOfWork->getScheduledEntityInsertions(),
            $unitOfWork->getScheduledEntityUpdates(),
            $unitOfWork->getScheduledEntityDeletions(),
        ] as $entities) {
            foreach ($entities as $entity) {
                if ($entity instanceof Extension) {
                    $this->clearInstalledNamesCache();
                    $this->extensionWritesScheduled = true;

                    return;
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->extensionWritesScheduled) {
            return;
        }

        $this->clearInstalledNamesCache();
        $this->extensionWritesScheduled = false;
    }

    public function onClear(OnClearEventArgs $args): void
    {
        $this->clearInstalledNamesCache();
        $this->extensionWritesScheduled = false;
    }
}
