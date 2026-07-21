<?php

declare(strict_types=1);

use Box\Mod\System\Entity\Setting;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

test('maps the existing setting table and unique parameter constraint', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    $metadata = $entityManager->getClassMetadata(Setting::class);

    expect($metadata->getTableName())->toBe('setting')
        ->and($metadata->getColumnNames())->toBe([
            'id', 'param', 'value', 'public', 'category', 'hash', 'created_at', 'updated_at',
        ])
        ->and($metadata->getFieldMapping('param')->unique)->toBeTrue();
});

test('stores setting values and manages timestamps', function (): void {
    $createdAt = new DateTime('2026-01-01 12:00:00');
    $setting = (new Setting())
        ->setParam('company_name')
        ->setValue('Example Ltd')
        ->setPublic(true)
        ->setCategory('company')
        ->setHash('configuration');
    $setting->setCreatedAt($createdAt);

    $setting->onPrePersist();

    expect($setting->getParam())->toBe('company_name')
        ->and($setting->getValue())->toBe('Example Ltd')
        ->and($setting->isPublic())->toBeTrue()
        ->and($setting->getCategory())->toBe('company')
        ->and($setting->getHash())->toBe('configuration')
        ->and($setting->getCreatedAt())->toBe($createdAt)
        ->and($setting->getUpdatedAt())->toBeInstanceOf(DateTime::class);
});
