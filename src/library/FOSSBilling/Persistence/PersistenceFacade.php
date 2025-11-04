<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use FOSSBilling\Exception\Domain\EntityNotFound;

/**
 * Centralized persistence layer for Doctrine operations.
 *
 * Prevents direct EntityManager usage across the codebase and provides
 * type-safe helpers for common operations.
 */
class PersistenceFacade
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Find entity by ID or throw exception.
     *
     * @template T of object
     *
     * @param class-string<T> $entityClass The entity class name
     * @param mixed           $id          The entity ID
     *
     * @return T The found entity
     *
     * @throws EntityNotFound When entity is not found
     */
    public function findOrFail(string $entityClass, mixed $id): object
    {
        $entity = $this->em->find($entityClass, $id);

        if (!$entity) {
            throw new EntityNotFound($entityClass, $id);
        }

        return $entity;
    }

    /**
     * Get repository for entity.
     *
     * @template T of object
     *
     * @param class-string<T> $entityClass The entity class name
     *
     * @return \Doctrine\ORM\EntityRepository<T> The repository instance
     */
    public function getRepository(string $entityClass): object
    {
        return $this->em->getRepository($entityClass);
    }

    /**
     * Execute operation in transaction.
     *
     * Wraps the callable in a transaction with automatic rollback on exception.
     *
     * @template T
     *
     * @param callable(): T $fn The function to execute in transaction
     *
     * @return T The result of the callable
     *
     * @throws \Throwable Any exception thrown by the callable (after rollback)
     */
    public function transactional(callable $fn): mixed
    {
        return $this->em->wrapInTransaction($fn);
    }

    /**
     * Persist entity (does not flush).
     *
     * Marks the entity for insertion on next flush.
     *
     * @param object $entity The entity to persist
     */
    public function persist(object $entity): void
    {
        $this->em->persist($entity);
    }

    /**
     * Remove entity (does not flush).
     *
     * Marks the entity for deletion on next flush.
     *
     * @param object $entity The entity to remove
     */
    public function remove(object $entity): void
    {
        $this->em->remove($entity);
    }

    /**
     * Flush changes to database.
     *
     * Executes all pending insert, update, and delete operations.
     */
    public function flush(): void
    {
        $this->em->flush();
    }

    /**
     * Clear entity manager.
     *
     * Detaches all entities from the entity manager.
     */
    public function clear(): void
    {
        $this->em->clear();
    }
}
