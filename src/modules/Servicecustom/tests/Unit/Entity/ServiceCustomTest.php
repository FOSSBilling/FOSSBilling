<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Servicecustom\Entity\ServiceCustom;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

test('maps service_custom table without changing columns', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $meta = $entityManager->getClassMetadata(ServiceCustom::class);

    expect($meta->getTableName())->toBe('service_custom')
        ->and($meta->getColumnNames())->toBe([
            'id', 'client_id', 'plugin', 'plugin_config', 'f1', 'f2', 'f3', 'f4', 'f5',
            'f6', 'f7', 'f8', 'f9', 'f10', 'config', 'created_at', 'updated_at',
        ])
        ->and($meta->getFieldMapping('clientId')['nullable'])->toBeTrue()
        ->and($meta->getFieldMapping('plugin')['nullable'])->toBeTrue()
        ->and($meta->getFieldMapping('pluginConfig')['nullable'])->toBeTrue()
        ->and($meta->getFieldMapping('f1')['nullable'])->toBeTrue()
        ->and($meta->getFieldMapping('f10')['nullable'])->toBeTrue()
        ->and($meta->getFieldMapping('config')['nullable'])->toBeTrue();
});

test('service custom getters and setters round-trip values', function (): void {
    $entity = new ServiceCustom();

    $entity->setClientId(10)
        ->setPlugin('MyPlugin')
        ->setPluginConfig('{"key":"val"}')
        ->setF1('field1')
        ->setF10('field10')
        ->setConfig('{"a":1}');

    expect($entity->getClientId())->toBe(10)
        ->and($entity->getPlugin())->toBe('MyPlugin')
        ->and($entity->getPluginConfig())->toBe('{"key":"val"}')
        ->and($entity->getF1())->toBe('field1')
        ->and($entity->getF10())->toBe('field10')
        ->and($entity->getConfig())->toBe('{"a":1}')
        ->and($entity->getId())->toBeNull();
});
