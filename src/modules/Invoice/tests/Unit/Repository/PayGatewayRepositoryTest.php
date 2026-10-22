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
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Doctrine\SchemaSynchronizer;
use Symfony\Component\Filesystem\Path;

function payGatewayEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

test('custom gateway image persists independently of adapter credentials', function (): void {
    $em = payGatewayEntityManager();
    (new SchemaTool($em))->createSchema([$em->getClassMetadata(PayGateway::class)]);
    $gateway = (new PayGateway())->setGateway('Custom')->setConfig('{"api_key":"secret"}')->setCustomLogo(str_repeat('a', 64) . '.png');
    $em->persist($gateway);
    $em->flush();
    $id = $gateway->getId();
    $em->clear();

    $stored = $em->find(PayGateway::class, $id);
    expect($stored->getCustomLogo())->toBe(str_repeat('a', 64) . '.png')
        ->and($stored->getConfig())->toBe('{"api_key":"secret"}');
});

test('schema synchronization adds custom images to existing gateways', function (): void {
    $em = payGatewayEntityManager();
    (new SchemaTool($em))->createSchema([$em->getClassMetadata(PayGateway::class)]);
    $em->getConnection()->executeStatement('ALTER TABLE pay_gateway DROP COLUMN custom_logo');
    $em->getConnection()->insert('pay_gateway', ['name' => 'Existing', 'enabled' => 1, 'allow_single' => 1, 'allow_recurrent' => 0, 'test_mode' => 0]);

    SchemaSynchronizer::syncEntities($em, [PayGateway::class]);

    $row = $em->getConnection()->fetchAssociative('SELECT name, custom_logo FROM pay_gateway');
    expect($row)->toBe(['name' => 'Existing', 'custom_logo' => null]);
});

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

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrderBy, bool $expectsTieBreak): void {
    $dql = payGatewayEntityManager()->getRepository(PayGateway::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain($expectedOrderBy);
    if ($expectsTieBreak) {
        expect($dql)->toContain(', pg.id');
    } else {
        expect($dql)->not->toContain(', pg.id');
    }
})->with([
    'id ascending' => [['sort' => 'id'], 'ORDER BY pg.id ASC', false],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'ORDER BY pg.id DESC', false],
    'title' => [['sort' => 'title'], 'ORDER BY pg.name ASC, pg.id ASC', true],
    'code' => [['sort' => 'code', 'direction' => 'desc'], 'ORDER BY pg.gateway DESC, pg.id DESC', true],
    'invalid sort falls back to default' => [['sort' => 'pg.gateway; DROP TABLE pay_gateway'], 'ORDER BY pg.gateway ASC', false],
    'invalid direction falls back to ascending' => [['sort' => 'title', 'direction' => 'sideways'], 'ORDER BY pg.name ASC, pg.id ASC', true],
]);
