<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Serviceapikey\Entity\ServiceApiKey;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

test('maps service_apikey table without changing columns', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $meta = $entityManager->getClassMetadata(ServiceApiKey::class);

    expect($meta->getTableName())->toBe('service_apikey')
        ->and($meta->getColumnNames())->toBe([
            'id', 'client_id', 'api_key', 'config', 'created_at', 'updated_at',
        ])
        ->and($meta->getFieldMapping('clientId')['nullable'])->toBeTrue()
        ->and($meta->getFieldMapping('apiKey')['nullable'])->toBeTrue()
        ->and($meta->getFieldMapping('config')['nullable'])->toBeTrue();
});

test('service api key getters and setters round-trip values', function (): void {
    $entity = new ServiceApiKey();

    $entity->setClientId(42)
        ->setApiKey('TEST-KEY-123')
        ->setConfig('{"length":32}');

    expect($entity->getClientId())->toBe(42)
        ->and($entity->getApiKey())->toBe('TEST-KEY-123')
        ->and($entity->getConfig())->toBe('{"length":32}')
        ->and($entity->getId())->toBeNull();
});

test('service api key timestamp lifecycle sets created and updated on persist', function (): void {
    $entity = new ServiceApiKey();
    $entity->onPrePersist();

    expect($entity->getCreatedAt())->not->toBeNull()
        ->and($entity->getUpdatedAt())->not->toBeNull()
        ->and($entity->getCreatedAt())->toEqual($entity->getUpdatedAt());
});

test('service api key timestamp lifecycle bumps updated on update', function (): void {
    $entity = new ServiceApiKey();
    $entity->onPrePersist();

    $originalUpdated = $entity->getUpdatedAt();
    usleep(1000);
    $entity->onPreUpdate();

    expect($entity->getUpdatedAt())->not->toEqual($originalUpdated)
        ->and($entity->getCreatedAt())->not->toBeNull();
});
