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
use Doctrine\ORM\QueryBuilder;

function extensionRepoCreateRepository(): ExtensionRepository
{
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $classMeta = Mockery::mock(Doctrine\ORM\Mapping\ClassMetadata::class);
    $classMeta->name = Extension::class;
    $classMeta->shouldReceive('getName')->andReturn(Extension::class);
    $em->shouldReceive('getClassMetadata')->with(Extension::class)->andReturn($classMeta);

    $qb = new QueryBuilder($em);
    $em->shouldReceive('createQueryBuilder')->andReturn($qb);

    return new ExtensionRepository($em, $classMeta);
}

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
    $repo = Mockery::mock(ExtensionRepository::class)->makePartial();
    $repo->shouldReceive('findOneBy')
        ->once()
        ->with(['type' => 'mod', 'name' => 'sample'])
        ->andReturn(null);

    expect($repo->findOneByTypeAndName('mod', 'sample'))->toBeNull();
});

test('findByType delegates to findBy', function (): void {
    $repo = Mockery::mock(ExtensionRepository::class)->makePartial();
    $repo->shouldReceive('findBy')
        ->once()
        ->with(['type' => 'mod'])
        ->andReturn([]);

    expect($repo->findByType('mod'))->toBe([]);
});

test('findInstalledByType returns installed extensions', function (): void {
    $repo = Mockery::mock(ExtensionRepository::class)->makePartial();
    $repo->shouldReceive('findBy')
        ->once()
        ->with(['type' => 'mod', 'status' => Extension::STATUS_INSTALLED])
        ->andReturn([]);

    expect($repo->findInstalledByType('mod'))->toBe([]);
});

test('findInstalledNamesByType returns names for installed extensions', function (): void {
    $extension = new Extension();
    $extension->setName('sample');

    $repo = Mockery::mock(ExtensionRepository::class)->makePartial();
    $repo->shouldReceive('findInstalledByType')
        ->once()
        ->with('mod')
        ->andReturn([$extension]);

    expect($repo->findInstalledNamesByType('mod'))->toBe(['sample']);
});

test('existsActiveByTypeAndName returns true when found', function (): void {
    $repo = Mockery::mock(ExtensionRepository::class)->makePartial();
    $entity = new Extension();
    $repo->shouldReceive('findOneBy')
        ->once()
        ->with(['type' => 'mod', 'name' => 'sample', 'status' => Extension::STATUS_INSTALLED])
        ->andReturn($entity);

    expect($repo->existsActiveByTypeAndName('mod', 'sample'))->toBeTrue();
});

test('existsActiveByTypeAndName returns false when not found', function (): void {
    $repo = Mockery::mock(ExtensionRepository::class)->makePartial();
    $repo->shouldReceive('findOneBy')
        ->once()
        ->andReturn(null);

    expect($repo->existsActiveByTypeAndName('mod', 'sample'))->toBeFalse();
});
