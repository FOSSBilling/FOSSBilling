<?php

declare(strict_types=1);

use Box\Mod\Activity\Entity\ActivityAdminHistory;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

test('maps the existing admin activity history table', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    $metadata = $entityManager->getClassMetadata(ActivityAdminHistory::class);

    expect($metadata->getTableName())->toBe('activity_admin_history')
        ->and($metadata->getColumnNames())->toBe(['id', 'admin_id', 'ip', 'created_at'])
        ->and($metadata->table['indexes']['admin_id_idx']['columns'])->toBe(['admin_id']);
});

test('stores admin activity history and initializes its timestamp', function (): void {
    $createdAt = new DateTime('2026-01-01 12:00:00');
    $history = (new ActivityAdminHistory())
        ->setAdminId(2)
        ->setIp('192.0.2.1')
        ->setCreatedAt($createdAt);

    $history->onPrePersist();

    expect($history->getId())->toBeNull()
        ->and($history->getAdminId())->toBe(2)
        ->and($history->getIp())->toBe('192.0.2.1')
        ->and($history->getCreatedAt())->toBe($createdAt);
});
