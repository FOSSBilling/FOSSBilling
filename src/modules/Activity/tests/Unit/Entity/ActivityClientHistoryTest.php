<?php

declare(strict_types=1);

use Box\Mod\Activity\Entity\ActivityClientHistory;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

test('maps the existing client activity history table', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    $metadata = $entityManager->getClassMetadata(ActivityClientHistory::class);

    expect($metadata->getTableName())->toBe('activity_client_history')
        ->and($metadata->getColumnNames())->toBe(['id', 'client_id', 'ip', 'created_at'])
        ->and($metadata->table['indexes']['client_id_idx']['columns'])->toBe(['client_id']);
});

test('stores client activity history and initializes its timestamp', function (): void {
    $history = (new ActivityClientHistory())
        ->setClientId(3)
        ->setIp('192.0.2.2');

    $history->onPrePersist();

    expect($history->getId())->toBeNull()
        ->and($history->getClientId())->toBe(3)
        ->and($history->getIp())->toBe('192.0.2.2')
        ->and($history->getCreatedAt())->toBeInstanceOf(DateTime::class);
});
