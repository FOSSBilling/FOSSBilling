<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Extension\Entity\ExtensionMeta;
use Box\Mod\Extension\Repository\ExtensionMetaRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Doctrine\EntityManagerFactory;

function extensionMetaRepoCreateRepository(): ExtensionMetaRepository
{
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $classMeta = Mockery::mock(Doctrine\ORM\Mapping\ClassMetadata::class);
    $classMeta->name = ExtensionMeta::class;
    $classMeta->shouldReceive('getName')->andReturn(ExtensionMeta::class);
    $em->shouldReceive('getClassMetadata')->with(ExtensionMeta::class)->andReturn($classMeta);

    $qb = new QueryBuilder($em);
    $em->shouldReceive('createQueryBuilder')->andReturn($qb);

    return new ExtensionMetaRepository($em, $classMeta);
}

test('createQueryBuilderForExtension sets the extension parameter', function (): void {
    $repo = extensionMetaRepoCreateRepository();
    $qb = $repo->createQueryBuilderForExtension('mod_email');

    expect($qb)->toBeInstanceOf(QueryBuilder::class);
    expect($qb->getParameter('extension')->getValue())->toBe('mod_email');
});

test('findOneByExtensionAndId delegates to findOneBy', function (): void {
    $repo = Mockery::mock(ExtensionMetaRepository::class)->makePartial();
    $repo->shouldReceive('findOneBy')
        ->once()
        ->with(['extension' => 'mod_email', 'id' => 5])
        ->andReturn(null);

    expect($repo->findOneByExtensionAndId('mod_email', 5))->toBeNull();
});

test('findOneByExtensionAndScope returns the first match', function (): void {
    $meta = new ExtensionMeta();

    $repo = Mockery::mock(ExtensionMetaRepository::class)->makePartial();
    $repo->shouldReceive('findByExtensionAndScope')
        ->once()
        ->with('mod_email', 'config', null, null, ['id' => 'ASC'], 1)
        ->andReturn([$meta]);

    expect($repo->findOneByExtensionAndScope('mod_email', 'config'))->toBe($meta);
});

test('findOneByExtensionAndScope returns null on empty result', function (): void {
    $repo = Mockery::mock(ExtensionMetaRepository::class)->makePartial();
    $repo->shouldReceive('findByExtensionAndScope')
        ->once()
        ->andReturn([]);

    expect($repo->findOneByExtensionAndScope('mod_email', 'config'))->toBeNull();
});

test('scoped lookups observe direct SQL inserts and deletes without ORM events', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);
    (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(ExtensionMeta::class)]);
    $repository = $entityManager->getRepository(ExtensionMeta::class);

    expect($repository->findOneByExtensionAndScope('mod_cron', 'config'))->toBeNull();

    $connection->insert('extension_meta', ['extension' => 'mod_cron', 'meta_key' => 'config', 'meta_value' => '{}']);
    expect($repository->findOneByExtensionAndScope('mod_cron', 'config')?->getMetaValue())->toBe('{}');

    $connection->delete('extension_meta', ['extension' => 'mod_cron', 'meta_key' => 'config']);
    expect($repository->findOneByExtensionAndScope('mod_cron', 'config'))->toBeNull();
});
