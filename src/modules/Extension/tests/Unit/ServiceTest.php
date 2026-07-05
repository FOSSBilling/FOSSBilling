<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

use Box\Mod\Extension\Entity\Extension;
use Box\Mod\Extension\Entity\ExtensionMeta;
use Box\Mod\Extension\Repository\ExtensionMetaRepository;
use Box\Mod\Extension\Repository\ExtensionRepository;
use Box\Mod\Extension\Service;

use function Tests\Helpers\container;
use function Tests\Helpers\setEntityId;

class ExtensionPdoMock extends PDO
{
    public function __construct()
    {
    }
}

class ExtensionPdoStatementsMock extends PDOStatement
{
    public function __construct()
    {
    }
}

function extensionCreateEntity(int $id, string $type = 'mod', string $name = 'extensionName', string $status = 'installed', ?string $version = '1'): Extension
{
    $entity = new Extension();
    setEntityId($entity, $id);
    $entity->setType($type);
    $entity->setName($name);
    $entity->setStatus($status);
    $entity->setVersion($version);

    return $entity;
}

function extensionCreateExtensionMetaEntity(int $id, string $extensionName = 'extensionName', string $metaKey = 'config', ?string $metaValue = null): ExtensionMeta
{
    $entity = new ExtensionMeta();
    setEntityId($entity, $id);
    $entity->setExtension($extensionName);
    $entity->setMetaKey($metaKey);
    $entity->setMetaValue($metaValue);

    return $entity;
}

/**
 * Build a partial Mockery EntityManager that returns the right repository mock
 * for each entity class requested.
 */
function extensionBuildEm(?ExtensionRepository $extensionRepository = null, ?ExtensionMetaRepository $metaRepository = null, bool $ignoreMissing = true): Doctrine\ORM\EntityManagerInterface&Mockery\MockInterface
{
    $extensionRepository ??= Mockery::mock(ExtensionRepository::class)->shouldIgnoreMissing();
    $metaRepository ??= Mockery::mock(ExtensionMetaRepository::class)->shouldIgnoreMissing();

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    if ($ignoreMissing) {
        $em->shouldIgnoreMissing();
    }
    $em->shouldReceive('getRepository')->andReturnUsing(static fn (string $class): object => match ($class) {
        Extension::class => $extensionRepository,
        ExtensionMeta::class => $metaRepository,
        default => $metaRepository,
    });

    return $em;
}

test('getDi returns the dependency injection container', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('isCoreModule checks if module is core module', function (): void {
    $service = new Service();
    $coreModules = ['extension', 'cron', 'staff'];
    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn($coreModules);

    $di = container();
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $service->setDi($di);

    $result = $service->isCoreModule('extension');
    expect($result)->toBeBool();
    expect($result)->toBeTrue();

    $result = $service->isCoreModule('Extension');
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('isExtensionActive returns false when module not found', function (): void {
    $service = new Service();
    $coreModules = ['extension', 'cron', 'staff'];
    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn($coreModules);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')
        ->atLeast()
        ->once()
        ->andReturn(false);

    $em = extensionBuildEm($extensionRepository);

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $service->setDi($di);

    $result = $service->isExtensionActive('mod', 'ModDoesNotExists');
    expect($result)->toBeBool();
    expect($result)->toBeFalse();
});

test('removeNotExistingModules removes non-existing modules', function (): void {
    $service = new Service();
    $ext = extensionCreateEntity(1, 'mod', 'extensionName');

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getManifest')->andThrow(new Exception());

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('findByType')
        ->with(Extension::TYPE_MOD)
        ->atLeast()
        ->once()
        ->andReturn([$ext]);

    $em = extensionBuildEm($extensionRepository);
    $em->shouldReceive('remove')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $service->setDi($di);

    $result = $service->removeNotExistingModules();
    expect($result)->toBeInt();
    expect($result > 0)->toBeTrue();
});

test('getSearchQueryBuilder returns a QueryBuilder', function (): void {
    $service = new Service();

    $qb = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $qb->shouldReceive('andWhere')->andReturnSelf();
    $qb->shouldReceive('setParameter')->andReturnSelf();
    $qb->shouldReceive('orderBy')->andReturnSelf();
    $qb->shouldReceive('addOrderBy')->andReturnSelf();

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('getSearchQueryBuilder')
        ->atLeast()
        ->once()
        ->andReturn($qb);

    $em = extensionBuildEm($extensionRepository);

    $di = container();
    $di['em'] = $em;

    $service->setDi($di);

    $result = $service->getExtensionRepository()->getSearchQueryBuilder([]);
    expect($result)->toBeInstanceOf(Doctrine\ORM\QueryBuilder::class);
});

test('getExtensionsList returns filtered extensions list', function (): void {
    $service = new Service();
    $data = [
        'has_settings' => true,
        'active' => true,
    ];

    $ext = extensionCreateEntity(1, 'mod', 'extensionName', 'installed', '1');

    $query = Mockery::mock(Doctrine\ORM\Query::class);
    $query->shouldReceive('getResult')->andReturn([$ext]);

    $qb = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $qb->shouldReceive('andWhere')->andReturnSelf();
    $qb->shouldReceive('setParameter')->andReturnSelf();
    $qb->shouldReceive('getQuery')->andReturn($query);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('getSearchQueryBuilder')->andReturn($qb);
    $extensionRepository->shouldReceive('findByType')->andReturn([]);

    $em = extensionBuildEm($extensionRepository);
    $em->shouldReceive('remove')->andReturnNull();
    $em->shouldReceive('flush')->andReturnNull();

    $coreModules = ['extension', 'cron', 'staff'];
    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn($coreModules);
    $modMock->shouldReceive('getManifest')
        ->atLeast()
        ->once()
        ->andReturn([]);
    $modMock->shouldReceive('hasSettingsPage')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $service->setDi($di);

    $result = $service->getExtensionsList($data);
    expect($result)->toBeArray();
});

test('getExtensionsList returns only installed extensions', function (): void {
    $service = new Service();
    $data = [
        'installed' => true,
    ];

    $ext = extensionCreateEntity(1, 'mod', 'extensionName', 'installed', '1');

    $query = Mockery::mock(Doctrine\ORM\Query::class);
    $query->shouldReceive('getResult')->andReturn([$ext]);

    $qb = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $qb->shouldReceive('andWhere')->andReturnSelf();
    $qb->shouldReceive('setParameter')->andReturnSelf();
    $qb->shouldReceive('getQuery')->andReturn($query);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('getSearchQueryBuilder')->andReturn($qb);
    $extensionRepository->shouldReceive('findByType')->andReturn([]);

    $em = extensionBuildEm($extensionRepository);
    $em->shouldReceive('remove')->andReturnNull();
    $em->shouldReceive('flush')->andReturnNull();

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getManifest')
        ->atLeast()
        ->once()
        ->andReturn([]);
    $modMock->shouldReceive('hasSettingsPage')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $service->setDi($di);

    $result = $service->getExtensionsList($data);
    expect($result)->toBeArray();
});

test('getAdminNavigation returns admin navigation', function (): void {
    $service = new Service();
    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')->once()->andReturn([]);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('findInstalledNamesByType')
        ->once()
        ->with(Extension::TYPE_MOD)
        ->andReturn([]);

    $di = container();
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);
    $di['tools'] = new FOSSBilling\Tools();
    $di['em'] = extensionBuildEm($extensionRepository);

    $service->setDi($di);
    $result = $service->getAdminNavigation(new Model_Admin());
    expect($result)->toBeArray();
});

test('findExtension finds an extension', function (): void {
    $service = new Service();
    $ext = extensionCreateEntity(1);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('findOneByTypeAndName')
        ->atLeast()
        ->once()
        ->andReturn($ext);

    $em = extensionBuildEm($extensionRepository);

    $di = container();
    $di['em'] = $em;

    $service->setDi($di);
    $result = $service->getExtensionRepository()->findOneByTypeAndName('mod', 'id');
    expect($result)->toBeInstanceOf(Extension::class);
});

test('update throws exception for extensions that need manual update', function (): void {
    $service = new Service();
    $ext = extensionCreateEntity(1, 'mod', 'testExtension', 'installed', '2');

    $extensionStub = Mockery::mock(FOSSBilling\ExtensionManager::class);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $di = container();
    $di['extension_manager'] = $extensionStub;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service->setDi($di);

    expect(fn () => $service->update($ext))
        ->toThrow(FOSSBilling\Exception::class, 'Visit the extension directory for more information on updating this extension.');
});

test('activate activates an extension', function (): void {
    $service = new Service();
    $ext = extensionCreateEntity(1, 'mod', 'testExtension', 'deactivated');

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getManifest')
        ->atLeast()
        ->once()
        ->andReturn(['version' => 1]);
    $modMock->shouldReceive('hasAdminController')
        ->atLeast()
        ->once()
        ->andReturn(true);
    $modMock->shouldReceive('hasSettingsPage')
        ->atLeast()
        ->once()
        ->andReturn(true);
    $modMock->shouldReceive('isCore')
        ->atLeast()
        ->once()
        ->andReturn(false);
    $modMock->shouldReceive('install')
        ->atLeast()
        ->once();

    $em = extensionBuildEm();
    $em->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service->setDi($di);
    $result = $service->activate($ext);
    expect($result)->toBeArray();
    expect($result['id'])->toBe('testExtension');
    expect($result['type'])->toBe('mod');
    expect($result['redirect'])->toBeTrue();
    expect($result['has_settings'])->toBeTrue();
});

test('deactivate deactivates an extension', function (): void {
    $service = new Service();
    $ext = extensionCreateEntity(1, 'mod', 'extensionTest', 'installed');

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $em = extensionBuildEm();
    $em->shouldReceive('remove')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service->setDi($di);

    $result = $service->deactivate($ext);
    expect($result)->toBeTrue();
});

test('deactivate throws exception for core modules', function (): void {
    $service = new Service();
    $ext = extensionCreateEntity(1, 'mod', 'extensionTest', 'installed');

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn([$ext->getName()]);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $em = extensionBuildEm();

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service->setDi($di);

    expect(fn (): bool => $service->deactivate($ext))
        ->toThrow(FOSSBilling\Exception::class, 'Core modules are an integral part of the FOSSBilling system and cannot be deactivated.');
});

test('deactivate deactivates hook extension', function (): void {
    $ext = extensionCreateEntity(1, 'hook', 'extensionTest', 'installed');

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $filesystemMock = Mockery::mock(Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('exists')->atLeast()->once()->andReturn(true);
    $filesystemMock->shouldReceive('remove')->atLeast()->once();

    $service = new Service($filesystemMock);

    $em = extensionBuildEm();
    $em->shouldReceive('remove')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service->setDi($di);
    $result = $service->deactivate($ext);
    expect($result)->toBeTrue();
});

test('deactivate deactivates module', function (): void {
    $ext = extensionCreateEntity(1, 'mod', 'extensionTest', 'installed');

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $em = extensionBuildEm();
    $em->shouldReceive('remove')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service = new Service();
    $service->setDi($di);

    $result = $service->deactivate($ext);
    expect($result)->toBeTrue();
});

test('uninstall uninstalls an extension', function (): void {
    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $modMock->shouldReceive('uninstall')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $di = container();

    $filesystemMock = Mockery::mock(Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('exists')
        ->atLeast()
        ->once()
        ->andReturn(true);
    $filesystemMock->shouldReceive('remove')
        ->atLeast()
        ->once();

    $tmpDir = sys_get_temp_dir() . '/fb_test_ext_' . uniqid();
    mkdir($tmpDir, 0o755, true);

    $serviceMock = Mockery::mock(Service::class . '[getExtensionPath]', [$filesystemMock]);
    $serviceMock->shouldReceive('getExtensionPath')
        ->atLeast()
        ->once()
        ->andReturn($tmpDir);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')->andReturn(false);

    $em = extensionBuildEm($extensionRepository);

    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $di['mod_service'] = $di->protect(function ($name) use ($staffService) {
        if ($name === 'Staff') {
            return $staffService;
        }

        return null;
    });

    $serviceMock->setDi($di);

    $result = $serviceMock->uninstall('mod', 'TestExtension');
    expect($result)->toBeTrue();

    if (is_dir($tmpDir)) {
        rmdir($tmpDir);
    }

    $result = $serviceMock->uninstall('mod', 'Branding');
    expect($result)->toBeTrue();
});

test('getExtensionPath rejects unsafe extension IDs', function (string $type, string $id): void {
    $service = new Service();

    expect(fn (): string => $service->getExtensionPath($type, $id))
        ->toThrow(FOSSBilling\InformationException::class, 'Extension ID contains invalid characters.');
})->with([
    [FOSSBilling\ExtensionManager::TYPE_MOD, '..'],
    [FOSSBilling\ExtensionManager::TYPE_THEME, '../admin_default'],
    [FOSSBilling\ExtensionManager::TYPE_TRANSLATION, '/tmp/language'],
    [FOSSBilling\ExtensionManager::TYPE_PG, 'Paypal/../../Adapter'],
]);

test('downloadAndExtract throws exception when download URL is missing', function (): void {
    $service = new Service();
    $extensionMock = Mockery::mock(FOSSBilling\ExtensionManager::class);

    $extensionMock->shouldReceive('getLatestExtensionRelease')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $di = container();
    $di['extension_manager'] = $extensionMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service->setDi($di);

    expect(fn (): bool => $service->downloadAndExtract('mod', 'extensionId'))
        ->toThrow(Exception::class, 'Couldn\'t find a valid download URL for the extension.');
});

test('getInstalledMods returns installed modules', function (): void {
    $service = new Service();

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('findInstalledNamesByType')
        ->once()
        ->with(Extension::TYPE_MOD)
        ->andReturn([]);

    $di = new Pimple\Container();
    $di['filesystem'] = new Symfony\Component\Filesystem\Filesystem();
    $di['em'] = extensionBuildEm($extensionRepository);

    $service->setDi($di);
    $result = $service->getInstalledMods();
    expect($result)->toBe([]);
});

test('activateExistingExtension activates existing extension', function (): void {
    $data = [
        'id' => 'extensionId',
        'type' => 'extensionType',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('activate')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('findOneByTypeAndName')
        ->atLeast()
        ->once()
        ->andReturn(null);

    $em = extensionBuildEm($extensionRepository);
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->activateExistingExtension($data);
    expect($result)->toBeArray();
});

test('activateExistingExtension throws exception on activation failure', function (): void {
    $data = [
        'id' => 'extensionId',
        'type' => 'extensionType',
    ];

    $model = extensionCreateEntity(1);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('activate')
        ->atLeast()
        ->once()
        ->andThrow(new Exception());

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('findOneByTypeAndName')
        ->atLeast()
        ->once()
        ->andReturn($model);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $em = extensionBuildEm($extensionRepository);

    $di = container();
    $di['em'] = $em;
    $di['events_manager'] = $eventMock;

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->activateExistingExtension($data))
        ->toThrow(Exception::class);
});

test('getConfig returns extension config', function (): void {
    $service = new Service();
    $data = [
        'ext' => 'extensionName',
    ];

    $meta = extensionCreateExtensionMetaEntity(1, 'extensionName', 'config', 'encryptedConfig');

    $metaRepo = Mockery::mock(ExtensionMetaRepository::class);
    $metaRepo->shouldReceive('findOneByExtensionAndScope')
        ->atLeast()
        ->once()
        ->andReturn($meta);

    $cryptMock = Mockery::mock(Box_Crypt::class);
    $cryptMock->shouldReceive('decrypt')->atLeast()->once();

    $em = extensionBuildEm(null, $metaRepo);

    $di = container();
    $di['em'] = $em;
    $di['crypt'] = $cryptMock;
    $di['cache'] = new Symfony\Component\Cache\Adapter\ArrayAdapter();

    $service->setDi($di);

    $result = $service->getConfig($data['ext']);
    expect($result)->toBeArray();
});

test('getConfig creates new ExtensionMeta when not found', function (): void {
    $service = new Service();
    $data = [
        'ext' => 'extensionName',
    ];

    $metaRepo = Mockery::mock(ExtensionMetaRepository::class);
    $metaRepo->shouldReceive('findOneByExtensionAndScope')
        ->atLeast()
        ->once()
        ->andReturn(null);

    $em = extensionBuildEm(null, $metaRepo);
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['cache'] = new Symfony\Component\Cache\Adapter\ArrayAdapter();

    $service->setDi($di);
    $result = $service->getConfig($data['ext']);

    expect($result)->toBeArray();
    expect($result)->toBe(['ext' => 'extensionName']);
});

test('setConfig sets extension config', function (): void {
    $data = [
        'ext' => 'extensionName',
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('hasManagePermission')
        ->with('extensionName')
        ->atLeast()
        ->once()
        ->andReturn(null);
    $serviceMock->shouldReceive('getConfig')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);

    $cryptMock = Mockery::mock(Box_Crypt::class);
    $cryptMock->shouldReceive('encrypt')
        ->atLeast()
        ->once()
        ->andReturn('encryptedConfig');

    $metaRepo = Mockery::mock(ExtensionMetaRepository::class);
    $metaRepo->shouldReceive('findOneByExtensionAndScope')
        ->atLeast()
        ->once()
        ->andReturn(null);

    $em = extensionBuildEm(null, $metaRepo);
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['tools'] = $toolsMock;
    $di['crypt'] = $cryptMock;
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['cache'] = new Symfony\Component\Cache\Adapter\ArrayAdapter();

    $serviceMock->setDi($di);
    $result = $serviceMock->setConfig($data);

    expect($result)->toBeTrue();
});

test('hasManagePermission denies access when module does not declare manage_settings', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();

    $staffMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffMock->shouldReceive('hasPermission')
        ->with(null, 'support')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $em = extensionBuildEm($extensionRepository);

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffMock);

    $serviceMock->setDi($di);

    $serviceMock->shouldReceive('getSpecificModulePermissions')
        ->with('support')
        ->andReturn([
            'view' => [],
        ]);

    expect(fn () => $serviceMock->hasManagePermission('mod_support'))
        ->toThrow(new FOSSBilling\InformationException('You do not have permission to perform this action', [], 403));
});

test('hasManagePermission allows access when module declares manage_settings and user has it', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();

    $staffMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffMock->shouldReceive('hasPermission')
        ->with(null, 'email')
        ->atLeast()
        ->once()
        ->andReturn(true);
    $staffMock->shouldReceive('hasPermission')
        ->with(null, 'email', 'manage_settings')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $em = extensionBuildEm($extensionRepository);

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffMock);

    $serviceMock->setDi($di);

    $serviceMock->shouldReceive('getSpecificModulePermissions')
        ->with('email')
        ->andReturn([
            'view' => [],
            'manage_settings' => [],
        ]);

    expect(fn () => $serviceMock->hasManagePermission('mod_email'))
        ->not->toThrow(new FOSSBilling\InformationException('You do not have permission to perform this action', [], 403));
});
