<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Currency\Repository\CurrencyRepository;
use Box\Mod\Currency\Service;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Path;

function currencyRepositoryEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(dirname(__DIR__, 3), 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

test('get search query builder orders by code by default', function (): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->once()->with('c.code', 'ASC')->andReturn($queryBuilder);

    $repository = Mockery::mock(CurrencyRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->once()->with('c')->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder([]))->toBe($queryBuilder);
});

test('sorts currency search query', function (array $data, string $expectedOrder, string $expectedDirection, ?string $expectedTieBreakerDirection): void {
    $queryBuilder = Mockery::mock(QueryBuilder::class);
    $queryBuilder->shouldReceive('orderBy')->once()->with($expectedOrder, $expectedDirection)->andReturn($queryBuilder);
    if ($expectedTieBreakerDirection !== null) {
        $queryBuilder->shouldReceive('addOrderBy')->once()->with('c.id', $expectedTieBreakerDirection)->andReturn($queryBuilder);
    } else {
        $queryBuilder->shouldReceive('addOrderBy')->never();
    }

    $repository = Mockery::mock(CurrencyRepository::class)->makePartial();
    $repository->shouldReceive('createQueryBuilder')->once()->with('c')->andReturn($queryBuilder);

    expect($repository->getSearchQueryBuilder($data))->toBe($queryBuilder);
})->with([
    'code ascending' => [['sort' => 'code'], 'c.code', 'ASC', 'ASC'],
    'code descending' => [['sort' => 'code', 'direction' => 'DESC'], 'c.code', 'DESC', 'DESC'],
    'conversion rate' => [['sort' => 'conversion_rate', 'direction' => 'desc'], 'c.conversionRate', 'DESC', 'DESC'],
    'created at' => [['sort' => 'created_at'], 'c.createdAt', 'ASC', 'ASC'],
    'updated at' => [['sort' => 'updated_at', 'direction' => 'DESC'], 'c.updatedAt', 'DESC', 'DESC'],
    'id' => [['sort' => 'id'], 'c.id', 'ASC', null],
    'invalid sort falls back to default' => [['sort' => 'c.code; DROP TABLE currency'], 'c.code', 'ASC', null],
    'invalid direction falls back to ascending' => [['sort' => 'code', 'direction' => 'sideways'], 'c.code', 'ASC', 'ASC'],
]);

test('Currency lookups are shared between services and invalidated by writes', function (): void {
    $entityManager = currencyRepositoryEntityManager();
    (new SchemaTool($entityManager))->createSchema([
        $entityManager->getClassMetadata(Currency::class),
    ]);

    $usd = (new Currency('USD'))->setIsDefault(true);
    $gbp = new Currency('GBP');
    $entityManager->persist($usd);
    $entityManager->persist($gbp);
    $entityManager->flush();

    $di = new Pimple\Container();
    $di['em'] = $entityManager;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['event_dispatcher'] = new EventDispatcher();

    $firstService = new Service();
    $firstService->setDi($di);
    $secondService = new Service();
    $secondService->setDi($di);

    $firstRepository = $firstService->getCurrencyRepository();
    $secondRepository = $secondService->getCurrencyRepository();

    expect($firstRepository->findDefault())->toBe($usd)
        ->and($secondRepository->findDefault())->toBe($usd)
        ->and($firstRepository->findOneByCode('GBP'))->toBe($gbp)
        ->and($secondRepository->findOneByCode('GBP'))->toBe($gbp)
        ->and($firstRepository->findOneByCode('CAD'))->toBeNull();

    $firstService->createCurrency('CAD', 1.2);
    expect($secondRepository->findOneByCode('CAD'))->toBeInstanceOf(Currency::class);

    $firstService->removeCurrency('CAD');
    expect($secondRepository->findOneByCode('CAD'))->toBeNull();

    $secondService->setAsDefault($gbp);
    expect($firstRepository->findDefault()?->getCode())->toBe('GBP')
        ->and($firstRepository->findOneByCode('USD')?->isDefault())->toBeFalse();
});

test('does not retain currency lookups observed in a transaction that is rolled back', function (): void {
    $entityManager = currencyRepositoryEntityManager();
    (new SchemaTool($entityManager))->createSchema([
        $entityManager->getClassMetadata(Currency::class),
    ]);

    $usd = (new Currency('USD'))->setIsDefault(true);
    $gbp = new Currency('GBP');
    $entityManager->persist($usd);
    $entityManager->persist($gbp);
    $entityManager->flush();

    $repository = $entityManager->getRepository(Currency::class);
    expect($repository->findOneByCode('CAD'))->toBeNull()
        ->and($repository->findDefault()?->getCode())->toBe('USD');

    $connection = $entityManager->getConnection();
    $connection->beginTransaction();

    $cad = new Currency('CAD');
    $entityManager->persist($cad);
    $usd->setIsDefault(false);
    $gbp->setIsDefault(true);
    $entityManager->flush();

    expect($repository->findOneByCode('CAD'))->toBe($cad)
        ->and($repository->findDefault()?->getCode())->toBe('GBP');

    $connection->rollBack();

    expect($repository->findOneByCode('CAD'))->toBeNull()
        ->and($repository->findDefault()?->getCode())->toBe('USD');
});
