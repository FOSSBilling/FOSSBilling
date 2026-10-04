<?php

declare(strict_types=1);

use Box\Mod\System\Entity\Setting;
use Box\Mod\System\Repository\SettingRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Doctrine\EntityManagerFactory;

beforeEach(function (): void {
    $this->entityManager = EntityManagerFactory::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
});

test('finds a setting by parameter', function (): void {
    $setting = new Setting();
    $repository = Mockery::mock(SettingRepository::class, [$this->entityManager, $this->entityManager->getClassMetadata(Setting::class)])->makePartial();
    $repository->shouldReceive('findOneBy')
        ->once()
        ->with(['param' => 'company_name'])
        ->andReturn($setting);

    expect($repository->findOneByParam('company_name'))->toBe($setting);
});

test('returns null when a setting parameter does not exist', function (): void {
    $repository = Mockery::mock(SettingRepository::class, [$this->entityManager, $this->entityManager->getClassMetadata(Setting::class)])->makePartial();
    $repository->shouldReceive('findOneBy')
        ->once()
        ->with(['param' => 'missing'])
        ->andReturn(null);

    expect($repository->findOneByParam('missing'))->toBeNull();
});

test('finds a public setting by parameter', function (): void {
    $setting = new Setting();
    $repository = Mockery::mock(SettingRepository::class, [$this->entityManager, $this->entityManager->getClassMetadata(Setting::class)])->makePartial();
    $repository->shouldReceive('findOneBy')
        ->once()
        ->with(['param' => 'company_name', 'public' => true])
        ->andReturn($setting);

    expect($repository->findOnePublicByParam('company_name'))->toBe($setting);
});

test('returns null when a setting is missing or not public', function (): void {
    $repository = Mockery::mock(SettingRepository::class, [$this->entityManager, $this->entityManager->getClassMetadata(Setting::class)])->makePartial();
    $repository->shouldReceive('findOneBy')
        ->once()
        ->with(['param' => 'hidden_param', 'public' => true])
        ->andReturn(null);

    expect($repository->findOnePublicByParam('hidden_param'))->toBeNull();
});

test('finds settings by a list of parameters', function (): void {
    $settings = [new Setting(), new Setting()];
    $repository = Mockery::mock(SettingRepository::class, [$this->entityManager, $this->entityManager->getClassMetadata(Setting::class)])->makePartial();
    $repository->shouldReceive('findBy')
        ->once()
        ->with(['param' => ['company_name', 'company_email']])
        ->andReturn($settings);

    expect($repository->findByParams(['company_name', 'company_email']))->toBe($settings);
});

test('batch lookup preserves database collation matches', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);
    $connection->executeStatement(<<<'SQL'
        CREATE TABLE setting (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            param VARCHAR(255) COLLATE NOCASE DEFAULT NULL,
            value CLOB DEFAULT NULL,
            public BOOLEAN DEFAULT NULL,
            category VARCHAR(255) DEFAULT NULL,
            hash VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT NULL,
            updated_at DATETIME DEFAULT NULL
        )
        SQL);
    $connection->executeStatement("INSERT INTO setting (param, value) VALUES ('company', 'Example Company')");
    $repository = $entityManager->getRepository(Setting::class);

    $settings = $repository->findByParams(['Company']);
    expect($settings)->toHaveCount(1)
        ->and($settings[0]->getParam())->toBe('company')
        ->and($repository->findByParams(['Company']))->toBe($settings);
});

test('reuses settings and invalidates after ORM writes and clear', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);
    $schemaTool = new SchemaTool($entityManager);
    $schemaTool->createSchema([$entityManager->getClassMetadata(Setting::class)]);
    $repository = $entityManager->getRepository(Setting::class);

    $privateSetting = (new Setting())
        ->setParam('company_email')
        ->setValue('old@example.test');
    $entityManager->persist($privateSetting);
    $entityManager->flush();

    expect($repository->findOneByParam('company_email')?->getValue())->toBe('old@example.test')
        ->and($repository->findOnePublicByParam('company_email'))->toBeNull();

    $privateSetting->setPublic(true);
    $entityManager->flush();
    expect($repository->findOnePublicByParam('company_email'))->toBe($privateSetting);

    expect($repository->findOneByParam('new_option'))->toBeNull();
    $newSetting = (new Setting())->setParam('new_option')->setValue('created');
    $entityManager->persist($newSetting);
    $entityManager->flush();
    expect($repository->findOneByParam('new_option'))->toBe($newSetting);

    $entityManager->remove($newSetting);
    $entityManager->flush();
    expect($repository->findOneByParam('new_option'))->toBeNull();

    $entityManager->clear();
    $connection->executeStatement('UPDATE setting SET value = ? WHERE param = ?', ['fresh@example.test', 'company_email']);
    $reloadedSetting = $repository->findOneByParam('company_email');
    expect($reloadedSetting?->getValue())->toBe('fresh@example.test')
        ->and($entityManager->contains($reloadedSetting))->toBeTrue();
});

test('does not memoize setting reads inside a transaction that rolls back', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);
    (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(Setting::class)]);
    $repository = $entityManager->getRepository(Setting::class);
    $existing = (new Setting())->setParam('rollback_existing')->setValue('committed');
    $entityManager->persist($existing);
    $entityManager->flush();

    expect($repository->findOneByParam('rollback_existing'))->toBeInstanceOf(Setting::class)
        ->and($repository->findOneByParam('rollback_missing'))->toBeNull();

    $connection->beginTransaction();
    $connection->executeStatement("DELETE FROM setting WHERE param = 'rollback_existing'");
    $connection->executeStatement("INSERT INTO setting (param, value) VALUES ('rollback_missing', 'temporary')");

    expect($repository->findOneByParam('rollback_existing'))->toBeNull();
    $uncommitted = $repository->findOneByParam('rollback_missing');
    expect($uncommitted?->getValue())->toBe('temporary');
    if ($uncommitted instanceof Setting) {
        $entityManager->detach($uncommitted);
    }

    $connection->rollBack();
    expect($repository->findOneByParam('rollback_existing')?->getValue())->toBe('committed')
        ->and($repository->findOneByParam('rollback_missing'))->toBeNull();
});
