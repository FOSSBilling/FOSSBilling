<?php

declare(strict_types=1);

use Box\Mod\Activity\Entity\ActivityAdminHistory;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

test('maps the existing admin activity history table', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    $metadata = $entityManager->getClassMetadata(ActivityAdminHistory::class);

    expect($metadata->getTableName())->toBe('activity_admin_history')
        ->and($metadata->getColumnNames())->toBe(['id', 'admin_id', 'ip', 'created_at'])
        ->and($metadata->table['indexes']['admin_id_idx']['columns'])->toBe(['admin_id']);
});

test('preserves an explicitly provided timestamp', function (): void {
    $createdAt = new DateTime('2026-01-01 12:00:00');
    $history = new ActivityAdminHistory();
    $history->setCreatedAt($createdAt);

    $history->onPrePersist();

    expect($history->getCreatedAt())->toBe($createdAt);
});

test('initializes the timestamp when it is unset', function (): void {
    $history = new ActivityAdminHistory();

    $history->onPrePersist();

    expect($history->getCreatedAt())->toBeInstanceOf(DateTime::class);
});
