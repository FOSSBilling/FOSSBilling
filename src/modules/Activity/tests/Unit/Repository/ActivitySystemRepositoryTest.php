<?php

declare(strict_types=1);

use Box\Mod\Activity\Entity\ActivitySystem;
use Box\Mod\Activity\Repository\ActivitySystemRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Filesystem\Path;

test('deletes system activity by client id', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    $metadata = $entityManager->getClassMetadata(ActivitySystem::class);
    (new SchemaTool($entityManager))->createSchema([$metadata]);

    $entityManager->persist((new ActivitySystem())->setClientId(3)->setMessage('First'));
    $entityManager->persist((new ActivitySystem())->setClientId(3)->setMessage('Second'));
    $entityManager->persist((new ActivitySystem())->setClientId(4)->setMessage('Keep'));
    $entityManager->flush();

    $repository = $entityManager->getRepository(ActivitySystem::class);

    expect($repository)->toBeInstanceOf(ActivitySystemRepository::class)
        ->and($repository->deleteByClientId(3))->toBe(2)
        ->and($repository->count([]))->toBe(1)
        ->and($repository->count(['clientId' => 3]))->toBe(0)
        ->and($repository->count(['clientId' => 4]))->toBe(1);
});
