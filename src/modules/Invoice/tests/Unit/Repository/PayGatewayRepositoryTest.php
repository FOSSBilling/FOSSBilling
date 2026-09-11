<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\PayGateway;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function payGatewayEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

test('getSearchQueryBuilder orders by gateway ascending by default', function (): void {
    $repository = payGatewayEntityManager()->getRepository(PayGateway::class);

    $dql = $repository->getSearchQueryBuilder([])->getDQL();

    expect($dql)->toBe('SELECT pg FROM ' . PayGateway::class . ' pg ORDER BY pg.gateway ASC');
});

test('getSearchQueryBuilder applies the search filter on name and gateway', function (): void {
    $repository = payGatewayEntityManager()->getRepository(PayGateway::class);

    $dql = $repository->getSearchQueryBuilder(['search' => 'stripe'])->getDQL();

    expect($dql)->toContain('(pg.name LIKE :search OR pg.gateway LIKE :search)')
        ->and($dql)->toContain('ORDER BY pg.gateway ASC');
});

test('getSearchQueryBuilder applies enabled, allow_single, allow_recurrent and test_mode filters', function (): void {
    $repository = payGatewayEntityManager()->getRepository(PayGateway::class);

    $dql = $repository->getSearchQueryBuilder([
        'enabled' => 1,
        'allow_single' => 1,
        'allow_recurrent' => 1,
        'test_mode' => 0,
    ])->getDQL();

    expect($dql)->toContain('pg.enabled = :enabled')
        ->and($dql)->toContain('pg.allowSingle = :allow_single')
        ->and($dql)->toContain('pg.allowRecurrent = :allow_recurrent')
        ->and($dql)->toContain('pg.testMode = :test_mode');
});

test('boolean filters normalize falsey string representations to false', function (): void {
    $repository = payGatewayEntityManager()->getRepository(PayGateway::class);
    $filters = ['enabled', 'allow_single', 'allow_recurrent', 'test_mode'];

    foreach ($filters as $filter) {
        foreach (['false', 'off', '0', false, 0] as $falsey) {
            $qb = $repository->getSearchQueryBuilder([$filter => $falsey]);

            expect($qb->getParameter($filter)->getValue())->toBeFalse();
        }
    }
});

test('boolean filters normalize truthy representations to true', function (): void {
    $repository = payGatewayEntityManager()->getRepository(PayGateway::class);
    $filters = ['enabled', 'allow_single', 'allow_recurrent', 'test_mode'];

    foreach ($filters as $filter) {
        foreach (['true', 'on', '1', true, 1] as $truthy) {
            $qb = $repository->getSearchQueryBuilder([$filter => $truthy]);

            expect($qb->getParameter($filter)->getValue())->toBeTrue();
        }
    }
});
