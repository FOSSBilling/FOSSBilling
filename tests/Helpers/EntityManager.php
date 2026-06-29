<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Force the value of an entity's `id` property via reflection.
 *
 * Doctrine entities have private `id` properties; tests need to set them
 * directly to simulate a persisted row without going through the database.
 */
function setEntityId(object $entity, int $id): void
{
    $reflection = new \ReflectionProperty($entity, 'id');
    $reflection->setValue($entity, $id);
}

/**
 * Build a configured EntityManager double for unit tests.
 *
 * The returned object:
 *  - Routes `getRepository($className)` to a configured repository mock
 *  - Optionally auto-assigns the `id` of a persisted entity (see $persistedEntity)
 *  - Tracks `flush()` invocations on the public `flushCalls` property
 *  - Records persisted and removed entities on public arrays
 *  - Tolerates any other method call (returns null), so it can stand in for
 *    {@see EntityManagerInterface} in tests that exercise code paths which
 *    touch unrelated EM methods
 *
 * For richer expectations (sequences, return values, error injection), use
 * {@see entityManagerMockery()} instead.
 *
 * @param array<string, object> $repositories    class-name => repository mock
 * @param object|null           $persistedEntity if set, the first `persist()` call
 *                                               for an entity of the same class
 *                                               mutates its `id` to match this one
 *
 * @return object{
 *     repositories: array<string, object>,
 *     persisted:    array<int, object>,
 *     removed:      array<int, object>,
 *     flushCalls:   int,
 *     getRepository(string): object,
 *     persist(object): void,
 *     remove(object): void,
 *     flush(): void,
 * }
 */
function entityManagerMock(array $repositories = [], ?object $persistedEntity = null): object
{
    $persistedClass = $persistedEntity?->getId() !== null ? $persistedEntity::class : null;
    $persistedId = $persistedEntity?->getId() !== null ? (int) $persistedEntity->getId() : null;

    return new class($repositories, $persistedClass, $persistedId) {
        /** @var array<int, object> */
        public array $persisted = [];

        /** @var array<int, object> */
        public array $removed = [];

        public int $flushCalls = 0;

        private bool $persistedAssigned = false;

        /**
         * @param array<string, object> $repositories
         */
        public function __construct(
            public readonly array $repositories,
            private readonly ?string $persistedClass,
            private readonly ?int $persistedId,
        ) {
        }

        public function getRepository(string $className): object
        {
            if (!isset($this->repositories[$className])) {
                throw new \RuntimeException(sprintf('No repository configured for %s in entityManagerMock()', $className));
            }

            return $this->repositories[$className];
        }

        public function persist(object $entity): void
        {
            $this->persisted[] = $entity;

            if (!$this->persistedAssigned && $this->persistedClass !== null && $entity::class === $this->persistedClass && $this->persistedId !== null) {
                setEntityId($entity, $this->persistedId);
                $this->persistedAssigned = true;
            }
        }

        public function remove(object $entity): void
        {
            $this->removed[] = $entity;
        }

        public function flush(): void
        {
            ++$this->flushCalls;
        }

        /**
         * Magic fallthrough so the double can stand in for EntityManagerInterface
         * even when the service-under-test calls methods we don't care about.
         */
        public function __call(string $name, array $arguments): mixed
        {
            return null;
        }
    };
}

/**
 * Convenience wrapper: returns a partial Mockery mock of EntityManagerInterface
 * with the given repositories wired up, but tolerant of any other call.
 *
 * @param array<string, object> $repositories class-name => repository mock
 */
function entityManagerMockery(array $repositories = []): EntityManagerInterface&\Mockery\MockInterface
{
    /** @var EntityManagerInterface&\Mockery\MockInterface $em */
    $em = \Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();

    foreach ($repositories as $className => $repository) {
        $em->shouldReceive('getRepository')->with($className)->andReturn($repository);
    }

    return $em;
}
