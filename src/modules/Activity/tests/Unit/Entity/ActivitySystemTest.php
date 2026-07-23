<?php

declare(strict_types=1);

use Box\Mod\Activity\Entity\ActivitySystem;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

test('maps the existing system activity table', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    $metadata = $entityManager->getClassMetadata(ActivitySystem::class);

    expect($metadata->getTableName())->toBe('activity_system')
        ->and($metadata->getColumnNames())->toBe([
            'id', 'priority', 'admin_id', 'client_id', 'message', 'ip', 'created_at',
        ])
        ->and($metadata->table['indexes']['admin_id_idx']['columns'])->toBe(['admin_id'])
        ->and($metadata->table['indexes']['client_id_idx']['columns'])->toBe(['client_id']);
});

test('stores system activity values and initializes its timestamp', function (): void {
    $activity = (new ActivitySystem())
        ->setPriority(5)
        ->setAdminId(2)
        ->setClientId(3)
        ->setMessage('Test event')
        ->setIp('192.0.2.3');

    $activity->onPrePersist();

    expect($activity->getId())->toBeNull()
        ->and($activity->getPriority())->toBe(5)
        ->and($activity->getAdminId())->toBe(2)
        ->and($activity->getClientId())->toBe(3)
        ->and($activity->getMessage())->toBe('Test event')
        ->and($activity->getIp())->toBe('192.0.2.3')
        ->and($activity->getCreatedAt())->toBeInstanceOf(DateTime::class);
});
