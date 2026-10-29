<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Servicelicense\Entity\ServiceLicense;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

test('maps service_license table without changing columns', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $meta = $entityManager->getClassMetadata(ServiceLicense::class);

    expect($meta->getTableName())->toBe('service_license')
        ->and($meta->getColumnNames())->toBe([
            'id', 'client_id', 'license_key', 'validate_ip', 'validate_host',
            'validate_path', 'validate_version', 'ips', 'hosts', 'paths',
            'versions', 'config', 'plugin', 'checked_at', 'pinged_at',
            'created_at', 'updated_at',
        ])
        ->and($meta->getFieldMapping('licenseKey')['unique'])->toBeTrue()
        ->and($meta->getFieldMapping('clientId')['nullable'])->toBeTrue()
        ->and($meta->getFieldMapping('licenseKey')['nullable'])->toBeTrue()
        ->and($meta->getFieldMapping('ips')['nullable'])->toBeTrue()
        ->and($meta->getFieldMapping('hosts')['nullable'])->toBeTrue()
        ->and($meta->getFieldMapping('paths')['nullable'])->toBeTrue()
        ->and($meta->getFieldMapping('versions')['nullable'])->toBeTrue()
        ->and($meta->getFieldMapping('plugin')['nullable'])->toBeTrue();
});

test('service license getters and setters round-trip values', function (): void {
    $entity = new ServiceLicense();

    $entity->setClientId(7)
        ->setLicenseKey('LIC-123-ABC')
        ->setValidateIp(true)
        ->setValidateHost(false)
        ->setValidatePath(true)
        ->setValidateVersion(false)
        ->setIps('["127.0.0.1"]')
        ->setHosts('["example.com"]')
        ->setPaths('["/var/www"]')
        ->setVersions('["1.0"]')
        ->setConfig('{"key":"val"}')
        ->setPlugin('Simple');

    expect($entity->getClientId())->toBe(7)
        ->and($entity->getLicenseKey())->toBe('LIC-123-ABC')
        ->and($entity->isValidateIp())->toBeTrue()
        ->and($entity->isValidateHost())->toBeFalse()
        ->and($entity->isValidatePath())->toBeTrue()
        ->and($entity->isValidateVersion())->toBeFalse()
        ->and($entity->getIps())->toBe('["127.0.0.1"]')
        ->and($entity->getPlugin())->toBe('Simple')
        ->and($entity->getId())->toBeNull();
});

test('getAllowedIps returns decoded json array', function (): void {
    $entity = new ServiceLicense();
    $entity->setIps('["127.0.0.1","192.168.1.1"]');

    expect($entity->getAllowedIps())->toBe(['127.0.0.1', '192.168.1.1']);
});

test('getAllowedIps returns empty array for null ips', function (): void {
    $entity = new ServiceLicense();

    expect($entity->getAllowedIps())->toBe([]);
});

test('getAllowedHosts returns decoded json array', function (): void {
    $entity = new ServiceLicense();
    $entity->setHosts('["example.com","test.com"]');

    expect($entity->getAllowedHosts())->toBe(['example.com', 'test.com']);
});

test('getAllowedPaths returns decoded json array', function (): void {
    $entity = new ServiceLicense();
    $entity->setPaths('["/path/a","/path/b"]');

    expect($entity->getAllowedPaths())->toBe(['/path/a', '/path/b']);
});

test('getAllowedVersions returns decoded json array', function (): void {
    $entity = new ServiceLicense();
    $entity->setVersions('["1.0","2.0"]');

    expect($entity->getAllowedVersions())->toBe(['1.0', '2.0']);
});
