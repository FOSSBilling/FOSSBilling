<?php

declare(strict_types=1);

use Box\Mod\Activity\Entity\ActivityClientHistory;
use Box\Mod\Activity\Repository\ActivityClientHistoryRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

test('deletes client activity history by client id', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    $metadata = $entityManager->getClassMetadata(ActivityClientHistory::class);
    (new SchemaTool($entityManager))->createSchema([$metadata]);

    $entityManager->persist((new ActivityClientHistory())->setClientId(3));
    $entityManager->persist((new ActivityClientHistory())->setClientId(3));
    $entityManager->persist((new ActivityClientHistory())->setClientId(4));
    $entityManager->flush();

    $repository = $entityManager->getRepository(ActivityClientHistory::class);

    expect($repository)->toBeInstanceOf(ActivityClientHistoryRepository::class)
        ->and($repository->deleteByClientId(3))->toBe(2)
        ->and($repository->count([]))->toBe(1);
});
