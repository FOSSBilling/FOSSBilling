<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Tax;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

test('maps tax table without changing columns', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $meta = $entityManager->getClassMetadata(Tax::class);

    expect($meta->getTableName())->toBe('tax')
        ->and($meta->getColumnNames())->toBe([
            'id', 'level', 'name', 'country', 'state', 'taxrate',
            'created_at', 'updated_at',
        ])
        ->and($meta->getFieldMapping('level')->nullable)->toBeTrue()
        ->and($meta->getFieldMapping('name')->nullable)->toBeTrue()
        ->and($meta->getFieldMapping('country')->nullable)->toBeTrue()
        ->and($meta->getFieldMapping('state')->nullable)->toBeTrue()
        ->and($meta->getFieldMapping('taxrate')->nullable)->toBeTrue()
        ->and($meta->getFieldMapping('taxrate')->type)->toBe('string');
});

test('tax getters and setters round-trip values', function (): void {
    $entity = new Tax();

    $entity->setLevel(2);
    $entity->setName('VAT');
    $entity->setCountry('US');
    $entity->setState('CA');
    $entity->setTaxrate('0.21');

    expect($entity->getLevel())->toBe(2)
        ->and($entity->getName())->toBe('VAT')
        ->and($entity->getCountry())->toBe('US')
        ->and($entity->getState())->toBe('CA')
        ->and($entity->getTaxrate())->toBe('0.21')
        ->and($entity->getId())->toBeNull();
});

test('tax toApiArray matches the legacy RedBeanPHP toArray keys', function (): void {
    $entity = new Tax();
    $entity->setId(5);
    $entity->setLevel(1);
    $entity->setName('VAT');
    $entity->setCountry('US');
    $entity->setState('CA');
    $entity->setTaxrate('8.25');
    $entity->setCreatedAt(new DateTime('2026-01-02 03:04:05'));
    $entity->setUpdatedAt(new DateTime('2026-01-02 03:04:05'));

    expect($entity->toApiArray())->toBe([
        'id' => 5,
        'level' => 1,
        'name' => 'VAT',
        'country' => 'US',
        'state' => 'CA',
        'taxrate' => '8.25',
        'created_at' => '2026-01-02 03:04:05',
        'updated_at' => '2026-01-02 03:04:05',
    ]);
});
