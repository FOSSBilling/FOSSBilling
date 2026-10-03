<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Extension\Entity\Extension;
use Box\Mod\Extension\Repository\ExtensionRepository;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Doctrine\EntityManagerFactory;

function extensionRepoCreateRepository(): ExtensionRepository
{
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getEventManager')->andReturn(new EventManager());
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('isTransactionActive')->andReturnFalse();
    $em->shouldReceive('getConnection')->andReturn($connection);
    $classMeta = Mockery::mock(ClassMetadata::class);
    $classMeta->name = Extension::class;
    $classMeta->shouldReceive('getName')->andReturn(Extension::class);
    $em->shouldReceive('getClassMetadata')->with(Extension::class)->andReturn($classMeta);

    $qb = new QueryBuilder($em);
    $em->shouldReceive('createQueryBuilder')->andReturn($qb);

    return new ExtensionRepository($em, $classMeta);
}

beforeEach(function (): void {
    $this->entityManager = EntityManagerFactory::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
});

test('getSearchQueryBuilder applies status, type and search filters', function (): void {
    $repo = extensionRepoCreateRepository();
    $qb = $repo->getSearchQueryBuilder([
        'type' => 'mod',
        'status' => 'installed',
        'search' => 'sample',
    ]);

    expect($qb)->toBeInstanceOf(QueryBuilder::class);

    $dqlParts = $qb->getDQLParts();
    expect($dqlParts['where']->count())->toBe(3);
});

test('getSearchQueryBuilder orders by type, status, id', function (): void {
    $repo = extensionRepoCreateRepository();
    $qb = $repo->getSearchQueryBuilder([]);

    $orderBy = $qb->getDQLPart('orderBy');
    expect(count($orderBy))->toBe(3);
});

test('findOneByTypeAndName delegates to findOneBy', function (): void {
    $repo = Mockery::mock(ExtensionRepository::class, [$this->entityManager, $this->entityManager->getClassMetadata(Extension::class)])->makePartial();
    $repo->shouldReceive('findOneBy')
        ->once()
        ->with(['type' => 'mod', 'name' => 'sample'])
        ->andReturn(null);

    expect($repo->findOneByTypeAndName('mod', 'sample'))->toBeNull();
});

test('findByType delegates to findBy', function (): void {
    $repo = Mockery::mock(ExtensionRepository::class, [$this->entityManager, $this->entityManager->getClassMetadata(Extension::class)])->makePartial();
    $repo->shouldReceive('findBy')
        ->once()
        ->with(['type' => 'mod'])
        ->andReturn([]);

    expect($repo->findByType('mod'))->toBe([]);
});

test('findInstalledByType returns installed extensions', function (): void {
    $repo = Mockery::mock(ExtensionRepository::class, [$this->entityManager, $this->entityManager->getClassMetadata(Extension::class)])->makePartial();
    $repo->shouldReceive('findBy')
        ->once()
        ->with(['type' => 'mod', 'status' => Extension::STATUS_INSTALLED])
        ->andReturn([]);

    expect($repo->findInstalledByType('mod'))->toBe([]);
});

test('findInstalledNamesByType returns names for installed extensions', function (): void {
    $extension = new Extension();
    $extension->setName('sample');

    $repo = Mockery::mock(ExtensionRepository::class, [$this->entityManager, $this->entityManager->getClassMetadata(Extension::class)])->makePartial();
    $repo->shouldReceive('findInstalledByType')
        ->once()
        ->with('mod')
        ->andReturn([$extension]);

    expect($repo->findInstalledNamesByType('mod'))->toBe(['sample']);
});

test('active checks and installed-module lists share one cached lookup per type', function (): void {
    $extension = new Extension();
    $extension->setName('sample');

    $repo = Mockery::mock(ExtensionRepository::class, [$this->entityManager, $this->entityManager->getClassMetadata(Extension::class)])->makePartial();
    $repo->shouldReceive('findInstalledByType')
        ->once()
        ->with('mod')
        ->andReturn([$extension]);
    $repo->shouldReceive('findOneBy')
        ->once()
        ->with(['type' => 'mod', 'name' => 'missing', 'status' => Extension::STATUS_INSTALLED])
        ->andReturnNull();

    expect($repo->existsActiveByTypeAndName('mod', 'sample'))->toBeTrue()
        ->and($repo->existsActiveByTypeAndName('mod', 'missing'))->toBeFalse()
        ->and($repo->findInstalledNamesByType('mod'))->toBe(['sample']);
});

test('active checks preserve database name matching and cache fallback results', function (): void {
    $extension = new Extension();
    $extension->setName('CookieConsent');

    $repo = Mockery::mock(ExtensionRepository::class, [$this->entityManager, $this->entityManager->getClassMetadata(Extension::class)])->makePartial();
    $repo->shouldReceive('findInstalledByType')
        ->once()
        ->with('mod')
        ->andReturn([$extension]);
    $repo->shouldReceive('findOneBy')
        ->once()
        ->with(['type' => 'mod', 'name' => 'cookieconsent', 'status' => Extension::STATUS_INSTALLED])
        ->andReturn($extension);

    expect($repo->existsActiveByTypeAndName('mod', 'cookieconsent'))->toBeTrue()
        ->and($repo->existsActiveByTypeAndName('mod', 'cookieconsent'))->toBeTrue();
});

test('extension writes invalidate names while unrelated flushes preserve them', function (): void {
    $eventManager = new EventManager();
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getEventManager')->andReturn($eventManager);
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('isTransactionActive')->andReturnFalse();
    $em->shouldReceive('getConnection')->andReturn($connection);

    $classMeta = Mockery::mock(ClassMetadata::class);
    $classMeta->name = Extension::class;
    $classMeta->shouldReceive('getName')->andReturn(Extension::class);

    $repo = Mockery::mock(ExtensionRepository::class, [$em, $classMeta])->makePartial();
    $extension = new Extension();
    $extension->setName('sample');
    $repo->shouldReceive('findInstalledByType')->twice()->with('mod')->andReturn([$extension], []);

    $unitOfWork = Mockery::mock(Doctrine\ORM\UnitOfWork::class);
    $unitOfWork->shouldReceive('getScheduledEntityInsertions')->andReturn([]);
    $unitOfWork->shouldReceive('getScheduledEntityUpdates')->andReturn([new stdClass()], []);
    $unitOfWork->shouldReceive('getScheduledEntityDeletions')->andReturn([], [$extension]);
    $em->shouldReceive('getUnitOfWork')->andReturn($unitOfWork);

    expect($repo->findInstalledNamesByType('mod'))->toBe(['sample']);

    $eventManager->dispatchEvent(Events::onFlush, new OnFlushEventArgs($em));
    $eventManager->dispatchEvent(Events::postFlush, new PostFlushEventArgs($em));
    expect($repo->findInstalledNamesByType('mod'))->toBe(['sample']);

    $eventManager->dispatchEvent(Events::onFlush, new OnFlushEventArgs($em));
    $eventManager->dispatchEvent(Events::postFlush, new PostFlushEventArgs($em));
    expect($repo->findInstalledNamesByType('mod'))->toBe([]);
});

test('active checks preserve database collation and null-name semantics', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);
    $connection->executeStatement(<<<'SQL'
        CREATE TABLE extension (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            type VARCHAR(255) DEFAULT NULL,
            name VARCHAR(255) COLLATE NOCASE DEFAULT NULL,
            status VARCHAR(100) DEFAULT NULL,
            version VARCHAR(100) DEFAULT NULL,
            CONSTRAINT type_name UNIQUE (type, name)
        )
        SQL);

    $repository = $entityManager->getRepository(Extension::class);
    $nullNameExtension = (new Extension())
        ->setType('mod')
        ->setStatus(Extension::STATUS_INSTALLED);
    $extension = (new Extension())
        ->setType('mod')
        ->setName('CookieConsent')
        ->setStatus(Extension::STATUS_INSTALLED);
    $entityManager->persist($nullNameExtension);
    $entityManager->persist($extension);
    $entityManager->flush();

    expect($repository->existsActiveByTypeAndName('mod', ''))->toBeFalse()
        ->and($repository->existsActiveByTypeAndName('mod', 'cookieconsent'))->toBeTrue()
        ->and($repository->existsActiveByTypeAndName('mod', 'cookieconsent'))->toBeTrue();
});

test('does not memoize installed extension reads inside a transaction that rolls back', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);
    (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(Extension::class)]);
    $repository = $entityManager->getRepository(Extension::class);

    $committed = (new Extension())
        ->setType('mod')
        ->setName('committed')
        ->setStatus(Extension::STATUS_INSTALLED);
    $entityManager->persist($committed);
    $entityManager->flush();

    expect($repository->findInstalledNamesByType('mod'))->toBe(['committed'])
        ->and($repository->existsActiveByTypeAndName('mod', 'committed'))->toBeTrue()
        ->and($repository->existsActiveByTypeAndName('mod', 'temporary'))->toBeFalse();

    $connection->beginTransaction();
    $connection->executeStatement("DELETE FROM extension WHERE type = 'mod' AND name = 'committed'");
    $connection->executeStatement(
        'INSERT INTO extension (type, name, status, version) VALUES (:type, :name, :status, :version)',
        ['type' => 'mod', 'name' => 'temporary', 'status' => Extension::STATUS_INSTALLED, 'version' => null],
    );

    expect($repository->findInstalledNamesByType('mod'))->toBe(['temporary'])
        ->and($repository->existsActiveByTypeAndName('mod', 'committed'))->toBeFalse()
        ->and($repository->existsActiveByTypeAndName('mod', 'temporary'))->toBeTrue();

    $connection->rollBack();

    expect($repository->findInstalledNamesByType('mod'))->toBe(['committed'])
        ->and($repository->existsActiveByTypeAndName('mod', 'committed'))->toBeTrue()
        ->and($repository->existsActiveByTypeAndName('mod', 'temporary'))->toBeFalse();
});
