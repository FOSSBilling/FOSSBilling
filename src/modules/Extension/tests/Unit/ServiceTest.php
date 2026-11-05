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
use Box\Mod\Extension\Event\AfterAdminActivateExtensionEvent;
use Box\Mod\Extension\Event\AfterAdminExtensionConfigSaveEvent;
use Box\Mod\Extension\Event\AfterExtensionActivatedEvent;
use Box\Mod\Extension\Event\AfterExtensionDeactivatedEvent;
use Box\Mod\Extension\Event\BeforeAdminActivateExtensionEvent;
use Box\Mod\Extension\Event\BeforeAdminExtensionConfigSaveEvent;
use Box\Mod\Extension\Repository\ExtensionMetaRepository;
use Box\Mod\Extension\Repository\ExtensionRepository;
use Box\Mod\Extension\Service;
use Box\Mod\Widgets\Service as WidgetsService;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;
use FOSSBilling\Core\Enums\ExtensionType;
use FOSSBilling\Core\Events\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;
use Symfony\Component\Filesystem\Path;

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

function extensionCreateEntity(int $id, string $type = ExtensionType::Module->value, string $name = 'extensionName', string $status = 'installed', ?string $version = '1'): Extension
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

/**
 * Build an Extension service whose every `mod()` call resolves to a mocked
 * non-core module with the given manifest, for exercising installModule()'s
 * `requires` enforcement through activate(). Entries in `$byName` override
 * the default mock for individual module names.
 *
 * @return array{0: Service, 1: Mockery\MockInterface}
 */
function requiresActivateService(array $manifest, ?ExtensionRepository $extensionRepository = null, array $byName = []): array
{
    $extensionRepository ??= Mockery::mock(ExtensionRepository::class)->shouldIgnoreMissing();

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $modMock->shouldReceive('hasManifest')->andReturnTrue();
    $modMock->shouldReceive('getManifest')->andReturn($manifest);
    $modMock->shouldReceive('hasDeclaredVersion')->andReturn(isset($manifest['version']) && is_string($manifest['version']));
    $modMock->shouldReceive('isCore')->andReturnFalse();
    $modMock->shouldReceive('getCoreModules')->andReturn([]);
    $modMock->shouldReceive('hasAdminController')->andReturnFalse();
    $modMock->shouldReceive('hasSettingsPage')->andReturnFalse();

    $em = extensionBuildEm($extensionRepository);

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $byName[$name] ?? $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service = new Service();
    $service->setDi($di);

    return [$service, $modMock];
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
    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
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
    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
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

test('real extension activation and deactivation invalidate cached module names', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $em = EntityManagerFactory::create($connection);
    (new SchemaTool($em))->createSchema([$em->getClassMetadata(Extension::class)]);

    $extension = (new Extension())
        ->setType(ExtensionType::Module->value)
        ->setName('cookieconsent')
        ->setStatus(Extension::STATUS_DEACTIVATED);
    $em->persist($extension);
    $em->flush();

    $module = Mockery::mock(FOSSBilling\Core\Module::class);
    $module->shouldReceive('getCoreModules')->andReturn([]);
    $module->shouldReceive('hasManifest')->andReturnTrue();
    $module->shouldReceive('getManifest')->andReturn(['version' => '1.0']);
    $module->shouldReceive('isCore')->andReturnFalse();
    $module->shouldReceive('install');
    $module->shouldReceive('hasAdminController')->andReturnFalse();
    $module->shouldReceive('hasSettingsPage')->andReturnFalse();

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')
        ->with('extension', 'manage_extensions')
        ->atLeast()
        ->once();

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $module);
    $di['mod_service'] = $di->protect(fn ($name): Mockery\MockInterface => $staffService);

    $service = new Service();
    $service->setDi($di);

    expect($service->isExtensionActive('mod', 'cookieconsent'))->toBeFalse();

    $service->activate($extension);
    expect($service->isExtensionActive('mod', 'cookieconsent'))->toBeTrue();

    $service->deactivate($extension);
    expect($service->isExtensionActive('mod', 'cookieconsent'))->toBeFalse();

    $connection->insert('extension', [
        'type' => ExtensionType::Module->value,
        'name' => 'cookieconsent',
        'status' => Extension::STATUS_INSTALLED,
    ]);
    $em->clear();

    expect($service->isExtensionActive('mod', 'cookieconsent'))->toBeTrue();
});

test('removeNotExistingModules removes non-existing modules', function (): void {
    $service = new Service();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'extensionName');

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $modMock->shouldReceive('getManifest')->andThrow(new Exception());

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('findByType')
        ->with(ExtensionType::Module->value)
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

    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'extensionName', 'installed', '1');

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
    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
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

    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'extensionName', 'installed', '1');

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

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
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
    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $modMock->shouldReceive('getCoreModules')->once()->andReturn([]);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('findInstalledNamesByType')
        ->once()
        ->with(ExtensionType::Module->value)
        ->andReturn([]);

    $di = container();
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);
    $di['em'] = extensionBuildEm($extensionRepository);

    $service->setDi($di);
    $result = $service->getAdminNavigation(\Tests\Helpers\admin());
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
    $result = $service->getExtensionRepository()->findOneByTypeAndName(ExtensionType::Module->value, 'id');
    expect($result)->toBeInstanceOf(Extension::class);
});

test('update throws exception for extensions that need manual update', function (): void {
    $service = new Service();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'installed', '2');

    $extensionStub = Mockery::mock(FOSSBilling\Core\Remote\ExtensionManager::class);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $di = container();
    $di['extension_manager'] = $extensionStub;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service->setDi($di);

    expect(fn () => $service->update($ext))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Visit the extension directory for more information on updating this extension.');
});

test('activate activates an extension', function (): void {
    $service = new Service();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'deactivated');

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $modMock->shouldReceive('hasManifest')
        ->atLeast()
        ->once()
        ->andReturnTrue();
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

    $activeModules = [];
    $flushes = 0;
    $em = extensionBuildEm();
    $em->shouldReceive('flush')->twice()->andReturnUsing(function () use (&$activeModules, &$flushes): void {
        ++$flushes;
        if ($flushes === 2) {
            $activeModules = ['widgets'];
        }
    });

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);
    $cache = new class {
        public array $deleted = [];

        public function delete(string $key): bool
        {
            $this->deleted[] = $key;

            return true;
        }
    };
    $di['cache'] = $cache;
    $widgets = new WidgetsService();
    $widgets->setDi($di);
    $dispatcher = new EventDispatcher(
        static function () use (&$activeModules): array {
            return $activeModules;
        },
        static fn (string $module): object => $widgets,
    );
    $dispatcher->dispatch(new AfterExtensionActivatedEvent(0, 'theme', 'warmup'));
    $di['event_dispatcher'] = $dispatcher;

    $service->setDi($di);
    $result = $service->activate($ext);
    expect($result)->toBeArray();
    expect($result['id'])->toBe('testExtension');
    expect($result['type'])->toBe(ExtensionType::Module->value);
    expect($result['redirect'])->toBeTrue();
    expect($result['has_settings'])->toBeTrue();
    expect($cache->deleted)->toBe([WidgetsService::CACHE_KEY]);
});

test('activate installs a module whose requirements are satisfied', function (): void {
    [$service, $modMock] = requiresActivateService([
        'id' => 'testExtension',
        'version' => '1.0.0',
        'requires' => ['fossbilling' => '>=0.1.0', 'php' => '>=8.0', 'modules' => []],
    ]);
    $modMock->shouldReceive('install')->once();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'deactivated');

    $result = $service->activate($ext);

    expect($result['id'])->toBe('testExtension');
});

test('activate skips the fossbilling requirement on preview builds', function (): void {
    // The test suite runs against the 0.0.1 development placeholder, which is
    // a preview version, so even an unsatisfiable fossbilling constraint is skipped.
    [$service, $modMock] = requiresActivateService([
        'id' => 'testExtension',
        'version' => '1.0.0',
        'requires' => ['fossbilling' => '>=99.0', 'php' => '>=8.0', 'modules' => []],
    ]);
    $modMock->shouldReceive('install')->once();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'deactivated');

    $result = $service->activate($ext);

    expect($result['id'])->toBe('testExtension');
});

test('activate blocks a module whose php requirement is unmet', function (): void {
    [$service, $modMock] = requiresActivateService([
        'id' => 'testExtension',
        'version' => '1.0.0',
        'requires' => ['fossbilling' => '>=0.1.0', 'php' => '>=99.0', 'modules' => []],
    ]);
    $modMock->shouldReceive('install')->never();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'deactivated');

    expect(fn (): array => $service->activate($ext))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'requires PHP');
});

test('activate blocks a module whose required module is not active', function (): void {
    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')->andReturnFalse();

    [$service, $modMock] = requiresActivateService([
        'id' => 'testExtension',
        'version' => '1.0.0',
        'requires' => ['fossbilling' => '>=0.1.0', 'php' => '>=8.0', 'modules' => ['missingModule' => '>=1.0']],
    ], $extensionRepository);
    $modMock->shouldReceive('install')->never();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'deactivated');

    expect(fn (): array => $service->activate($ext))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'missingModule');
});

test('activate blocks a module whose required module version is unmet', function (): void {
    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')->andReturnTrue();

    [$service, $modMock] = requiresActivateService([
        'id' => 'testExtension',
        'version' => '1.0.0',
        'requires' => ['fossbilling' => '>=0.1.0', 'php' => '>=8.0', 'modules' => ['requiredModule' => '>=2.0']],
    ], $extensionRepository);
    $modMock->shouldReceive('install')->never();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'deactivated');

    expect(fn (): array => $service->activate($ext))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'requiredModule');
});

test('activate installs a module whose required module version is satisfied', function (): void {
    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')->andReturnTrue();

    [$service, $modMock] = requiresActivateService([
        'id' => 'testExtension',
        'version' => '1.0.0',
        'requires' => ['fossbilling' => '>=0.1.0', 'php' => '>=8.0', 'modules' => ['requiredModule' => '>=1.0']],
    ], $extensionRepository);
    $modMock->shouldReceive('install')->once();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'deactivated');

    $result = $service->activate($ext);

    expect($result['id'])->toBe('testExtension');
});

test('activate resolves core dependency versions from the core manifest', function (): void {
    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')->andReturnTrue();

    $coreDepMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $coreDepMock->shouldReceive('getManifest')->andReturn(['version' => '2.0']);
    $coreDepMock->shouldReceive('hasDeclaredVersion')->andReturnTrue();

    [$service, $modMock] = requiresActivateService([
        'id' => 'testExtension',
        'version' => '1.0.0',
        'requires' => ['fossbilling' => '>=0.1.0', 'php' => '>=8.0', 'modules' => ['coreModule' => '>=2.0']],
    ], $extensionRepository, ['coreModule' => $coreDepMock]);
    $modMock->shouldReceive('install')->once();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'deactivated');

    $result = $service->activate($ext);

    expect($result['id'])->toBe('testExtension');
});

test('activate blocks a module with an invalid requirement constraint', function (): void {
    [$service, $modMock] = requiresActivateService([
        'id' => 'testExtension',
        'version' => '1.0.0',
        'requires' => ['fossbilling' => '>=0.1.0', 'php' => '>>1.0', 'modules' => []],
    ]);
    $modMock->shouldReceive('install')->never();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'deactivated');

    expect(fn (): array => $service->activate($ext))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'invalid version requirement');
});

test('activate blocks a module whose requires value is not an object', function (mixed $requires): void {
    [$service, $modMock] = requiresActivateService([
        'id' => 'testExtension',
        'version' => '1.0.0',
        'requires' => $requires,
    ]);
    $modMock->shouldReceive('install')->never();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'deactivated');

    expect(fn (): array => $service->activate($ext))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'invalid module requirements');
})->with(['null' => null, 'string' => '>=1.0', 'int' => 42]);

test('activate parses the fossbilling requirement on preview builds', function (): void {
    // The comparison is skipped on previews, but an invalid constraint must
    // still fail - otherwise previews install what releases reject.
    [$service, $modMock] = requiresActivateService([
        'id' => 'testExtension',
        'version' => '1.0.0',
        'requires' => ['fossbilling' => '>>1.0', 'php' => '>=8.0', 'modules' => []],
    ]);
    $modMock->shouldReceive('install')->never();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'deactivated');

    expect(fn (): array => $service->activate($ext))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'invalid version requirement');
});

test('activate blocks a dependency without an installed version', function (): void {
    // The dependency reports no declared version, so activation must fail
    // instead of skipping the version check.
    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')->andReturnTrue();

    $depMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $depMock->shouldReceive('getManifest')->andReturn(['id' => 'missingVersion', 'version' => '1.0']);
    $depMock->shouldReceive('hasDeclaredVersion')->andReturnFalse();

    [$service, $modMock] = requiresActivateService([
        'id' => 'testExtension',
        'version' => '1.0.0',
        'requires' => ['fossbilling' => '>=0.1.0', 'php' => '>=8.0', 'modules' => ['missingVersion' => '>=1.0']],
    ], $extensionRepository, ['missingVersion' => $depMock]);
    $modMock->shouldReceive('install')->never();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'deactivated');

    expect(fn (): array => $service->activate($ext))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'invalid installed version');
});

test('activate blocks a dependency with a non-string installed version', function (): void {
    // A non-string version (e.g. a bare number) must fail validation instead
    // of being coerced into a version the manifest never declared.
    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')->andReturnTrue();

    $depMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $depMock->shouldReceive('getManifest')->andReturn(['id' => 'intVersion', 'version' => 1]);
    $depMock->shouldReceive('hasDeclaredVersion')->andReturnFalse();

    [$service, $modMock] = requiresActivateService([
        'id' => 'testExtension',
        'version' => '1.0.0',
        'requires' => ['fossbilling' => '>=0.1.0', 'php' => '>=8.0', 'modules' => ['intVersion' => '>=1.0']],
    ], $extensionRepository, ['intVersion' => $depMock]);
    $modMock->shouldReceive('install')->never();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'testExtension', 'deactivated');

    expect(fn (): array => $service->activate($ext))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'invalid installed version');
});

test('version requirements compare against release versions', function (string $version, string $constraint, bool $satisfied): void {
    // Covers the comparison logic with release-style versions, which the
    // activate() tests cannot reach (they run on preview builds where the
    // fossbilling comparison is skipped).
    $service = new Service();
    $call = fn (): mixed => (new ReflectionMethod(Service::class, 'assertConstraintSatisfied'))
        ->invoke($service, $version, $constraint, 'Mismatch', []);

    if ($satisfied) {
        expect($call())->toBeNull();
    } else {
        expect($call)->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Mismatch');
    }
})->with([
    'satisfied newer patch' => ['0.8.9', '>=0.8.0', true],
    'satisfied exact' => ['0.8.9', '>=0.8.9', true],
    'unsatisfied minor' => ['0.8.9', '>=0.9.0', false],
    'unsatisfied caret major' => ['0.7.0', '^0.8.0', false],
]);

test('deactivate deactivates an extension', function (): void {
    $service = new Service();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'widgets', 'installed');

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $activeModules = ['widgets'];
    $em = extensionBuildEm();
    $em->shouldReceive('remove')->atLeast()->once();
    $em->shouldReceive('flush')->once()->andReturnUsing(function () use (&$activeModules): void {
        $activeModules = [];
    });

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);
    $cache = new class {
        public array $deleted = [];

        public function delete(string $key): bool
        {
            $this->deleted[] = $key;

            return true;
        }
    };
    $di['cache'] = $cache;
    $widgets = new WidgetsService();
    $widgets->setDi($di);
    $dispatcher = new EventDispatcher(
        static function () use (&$activeModules): array {
            return $activeModules;
        },
        static fn (string $module): object => $widgets,
    );
    $dispatcher->dispatch(new AfterExtensionDeactivatedEvent(0, 'theme', 'warmup'));
    $di['event_dispatcher'] = $dispatcher;

    $service->setDi($di);

    $result = $service->deactivate($ext);
    expect($result)->toBeTrue();
    expect($cache->deleted)->toBeEmpty();
});

test('deactivate throws exception for core modules', function (): void {
    $service = new Service();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'extensionTest', 'installed');

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
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
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Core modules are an integral part of the FOSSBilling system and cannot be deactivated.');
});

test('deactivate deactivates module', function (): void {
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'extensionTest', 'installed');

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
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

test('deactivate blocks a module required by an installed module', function (): void {
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'baseModule', 'installed');

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $modMock->shouldReceive('getCoreModules')->andReturn([]);

    $depMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $depMock->shouldReceive('getManifest')->andReturn([
        'id' => 'dependentModule',
        'version' => '1.0.0',
        'requires' => ['fossbilling' => '>=0.1.0', 'php' => '>=8.0', 'modules' => ['baseModule' => '>=1.0']],
    ]);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('findInstalledNamesByType')->andReturn(['dependentModule']);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $em = extensionBuildEm($extensionRepository);

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $name === 'dependentModule' ? $depMock : $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service = new Service();
    $service->setDi($di);

    expect(fn (): bool => $service->deactivate($ext))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'dependentModule');
});

test('uninstall uninstalls an extension', function (): void {
    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
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
    $extensionRepository->shouldReceive('clearInstalledNamesCache')->twice();

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

    $result = $serviceMock->uninstall(ExtensionType::Module->value, 'TestExtension');
    expect($result)->toBeTrue();

    if (is_dir($tmpDir)) {
        rmdir($tmpDir);
    }

    $result = $serviceMock->uninstall(ExtensionType::Module->value, 'Branding');
    expect($result)->toBeTrue();
});

test('getExtensionPath accepts the legacy mod type', function (): void {
    $service = new Service();
    $service->setDi(container());

    expect($service->getExtensionPath('mod', 'Cookieconsent'))
        ->toBe(Path::join(PATH_MODS, 'Cookieconsent'));
});

test('getExtensionPath rejects unsafe extension IDs', function (string $type, string $id): void {
    $service = new Service();

    expect(fn (): string => $service->getExtensionPath($type, $id))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Extension ID contains invalid characters.');
})->with([
    [ExtensionType::Module->value, '..'],
    [ExtensionType::Theme->value, '../admin_default'],
    [ExtensionType::Translation->value, '/tmp/language'],
    [ExtensionType::PaymentGateway->value, 'Paypal/../../Adapter'],
]);

test('downloadAndExtract throws exception when download URL is missing', function (): void {
    $service = new Service();
    $extensionMock = Mockery::mock(FOSSBilling\Core\Remote\ExtensionManager::class);

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
        ->with(ExtensionType::Module->value)
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

    $steps = [];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('activate')
        ->once()
        ->andReturnUsing(function () use (&$steps): array {
            $steps[] = 'activate';

            return [];
        });

    $extension = extensionCreateEntity(42, ExtensionType::Module->value, 'extensionId', Extension::STATUS_DEACTIVATED);
    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('findOneByTypeAndName')
        ->once()
        ->with('extensionType', 'extensionId')
        ->andReturn($extension);

    $em = extensionBuildEm($extensionRepository);

    $dispatcher = new SymfonyEventDispatcher();
    $dispatcher->addListener(BeforeAdminActivateExtensionEvent::class, static function (BeforeAdminActivateExtensionEvent $event) use (&$steps): void {
        $steps[] = $event;
    });
    $dispatcher->addListener(AfterAdminActivateExtensionEvent::class, static function (AfterAdminActivateExtensionEvent $event) use (&$steps): void {
        $steps[] = $event;
    });

    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = $dispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->activateExistingExtension($data);
    expect($result)->toBeArray()
        ->and($steps)->toHaveCount(3)
        ->and($steps[0])->toBeInstanceOf(BeforeAdminActivateExtensionEvent::class)
        ->and($steps[0]->extensionRecordId)->toBe(42)
        ->and($steps[0]->extensionType)->toBe(ExtensionType::Module->value)
        ->and($steps[0]->extensionName)->toBe('extensionId')
        ->and($steps[1])->toBe('activate')
        ->and($steps[2])->toBeInstanceOf(AfterAdminActivateExtensionEvent::class)
        ->and($steps[2]->extensionRecordId)->toBe(42);
});

test('activateExistingExtension requires type and id', function (): void {
    $service = new Service();

    expect(fn () => $service->activateExistingExtension([]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
    expect(fn () => $service->activateExistingExtension(['id' => 'extensionId']))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
    expect(fn () => $service->activateExistingExtension(['type' => 'extensionType']))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
});

test('activateExistingExtension accepts zero-like identifiers', function (): void {
    $data = [
        'id' => '0',
        'type' => '0',
    ];

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('findOneByTypeAndName')
        ->once()
        ->with('0', '0')
        ->andReturnNull();

    $em = extensionBuildEm($extensionRepository);
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('activate')
        ->once()
        ->andReturn([]);

    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    expect($serviceMock->activateExistingExtension($data))->toBeArray();
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

    $events = [];
    $dispatcher = new SymfonyEventDispatcher();
    $dispatcher->addListener(BeforeAdminActivateExtensionEvent::class, static function (BeforeAdminActivateExtensionEvent $event) use (&$events): void {
        $events[] = $event;
    });
    $dispatcher->addListener(AfterAdminActivateExtensionEvent::class, static function (AfterAdminActivateExtensionEvent $event) use (&$events): void {
        $events[] = $event;
    });

    $em = extensionBuildEm($extensionRepository);

    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = $dispatcher;

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->activateExistingExtension($data))
        ->toThrow(Exception::class)
        ->and($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(BeforeAdminActivateExtensionEvent::class)
        ->and($events[0]->extensionRecordId)->toBe(1);
});

test('activateExistingExtension rejects module with missing or invalid manifest before persisting', function (string $manifestError): void {
    $service = new Service();

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $modMock->shouldReceive('getManifest')->once()->andThrow(new FOSSBilling\Core\Exception\InformationException($manifestError));

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldNotReceive('findOneByTypeAndName');

    $em = extensionBuildEm($extensionRepository);
    $em->shouldNotReceive('persist');
    $em->shouldNotReceive('flush');

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $service->setDi($di);

    expect(fn () => $service->activateExistingExtension(['type' => 'mod', 'id' => 'nonexistent']))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'its manifest file is missing or invalid');
})->with([
    'missing manifest' => 'Missing manifest file for the nonexistent module.',
    'invalid manifest' => 'Invalid manifest file for the nonexistent module. Please check file syntax and permissions.',
]);

test('activate rejects module with missing manifest', function (): void {
    $service = new Service();
    $ext = extensionCreateEntity(1, ExtensionType::Module->value, 'nonexistent', Extension::STATUS_DEACTIVATED);

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $modMock->shouldReceive('hasManifest')->once()->andReturnFalse();
    $modMock->shouldNotReceive('getManifest');

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')
        ->with('extension', 'manage_extensions')
        ->once();

    $di = container();
    $di['em'] = extensionBuildEm();
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn ($name): Mockery\MockInterface => $staffService);

    $service->setDi($di);

    expect(fn () => $service->activate($ext))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
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

    $cryptMock = Mockery::mock(FOSSBilling\Core\Security\Crypt::class);
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

test('getConfig returns defaults without writing when not found', function (): void {
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
    $em->shouldNotReceive('persist');
    $em->shouldNotReceive('flush');

    $di = container();
    $di['em'] = $em;
    $di['cache'] = new Symfony\Component\Cache\Adapter\ArrayAdapter();

    $service->setDi($di);
    $result = $service->getConfig($data['ext']);

    expect($result)->toBeArray();
    expect($result)->toBe(['ext' => 'extensionName']);
});

test('configuration reads do not create rows and authorized writes preserve public settings', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $em = EntityManagerFactory::create($connection);
    (new SchemaTool($em))->createSchema([
        $em->getClassMetadata(Extension::class),
        $em->getClassMetadata(ExtensionMeta::class),
    ]);

    $installed = (new Extension())
        ->setType(ExtensionType::Module->value)
        ->setName('cookieconsent')
        ->setStatus(Extension::STATUS_INSTALLED);
    $em->persist($installed);
    $em->flush();

    $module = Mockery::mock(FOSSBilling\Core\Module::class);
    $module->shouldReceive('getCoreModules')->andReturn(['index', 'staff']);
    $cache = new Symfony\Component\Cache\Adapter\ArrayAdapter();
    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(static fn (string $name): object => $module);
    $di['cache'] = $cache;
    $di['crypt'] = new FOSSBilling\Core\Security\Crypt();
    $di['event_dispatcher'] = new SymfonyEventDispatcher();
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('hasManagePermission')->with('mod_staff')->twice();
    $service->setDi($di);
    $api = new Box\Mod\Extension\Api\Guest();
    $api->setService($service);

    // Arbitrary identifiers must not populate either persistent store.
    foreach (['probe_001', 'probe_002', 'mod_probe_001', 'mod_mod_staff', 'MOD_staff', 'mod_staff/../probe', str_repeat('x', 255)] as $ext) {
        expect($api->settings(['ext' => $ext]))->toBe([])
            ->and($connection->fetchOne('SELECT COUNT(*) FROM extension_meta'))->toBe(0);
    }
    expect($cache->getValues())->toBe([])
        ->and($connection->fetchOne('SELECT COUNT(*) FROM extension_meta'))->toBe(0);

    // Core, installed, and legacy migration reads retain their empty defaults.
    foreach (['index', 'mod_staff', 'mod_cookieconsent'] as $ext) {
        expect($api->settings(['ext' => $ext]))->toBe([]);
    }
    foreach (['mod_spamchecker', 'probe_003'] as $ext) {
        expect($service->getConfig($ext))->toBe(['ext' => $ext]);
        expect($service->getConfig($ext))->toBe(['ext' => $ext]);
    }
    expect($connection->fetchOne('SELECT COUNT(*) FROM extension_meta'))->toBe(0);

    $data = ['ext' => 'mod_staff', 'public' => ['login_note' => 'Welcome'], 'private_key' => 'secret'];
    expect($service->setConfig($data))->toBeTrue()
        ->and($connection->fetchOne('SELECT COUNT(*) FROM extension_meta'))->toBe(1)
        ->and($service->getConfig('mod_staff'))->toBe($data)
        ->and($api->settings(['ext' => 'mod_staff']))->toBe($data['public']);
    $meta = $em->getRepository(ExtensionMeta::class)->findOneByExtensionAndScope('mod_staff', 'config');
    expect($meta->getMetaValue())->not->toContain('secret');

    $data['public']['login_note'] = 'Updated';
    expect($service->setConfig($data))->toBeTrue()
        ->and($connection->fetchOne('SELECT COUNT(*) FROM extension_meta'))->toBe(1)
        ->and($api->settings(['ext' => 'mod_staff']))->toBe($data['public']);
});

test('setConfig sets extension config', function (): void {
    $data = [
        'ext' => 'extensionName',
        'username' => 'admin',
        'api_key' => 'secret-api-key',
        'password' => 'secret-password',
        'nested' => ['credential' => 'nested-secret'],
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('hasManagePermission')
        ->with('extensionName')
        ->atLeast()
        ->once()
        ->andReturn(null);
    $serviceMock->shouldNotReceive('getConfig');

    $cryptMock = Mockery::mock(FOSSBilling\Core\Security\Crypt::class);
    $encryptedConfig = null;
    $cryptMock->shouldReceive('encrypt')
        ->once()
        ->with(json_encode($data), Mockery::type('string'))
        ->andReturnUsing(function (string $config, string $salt) use (&$encryptedConfig): string {
            $encryptedConfig = $config;

            return 'encryptedConfig';
        });

    $metaRepo = Mockery::mock(ExtensionMetaRepository::class);
    $metaRepo->shouldReceive('findOneByExtensionAndScope')
        ->atLeast()
        ->once()
        ->andReturn(null);

    $em = extensionBuildEm(null, $metaRepo);
    $em->shouldReceive('persist')->atLeast()->once();
    $flushed = false;
    $em->shouldReceive('flush')->once()->andReturnUsing(function () use (&$flushed): void {
        $flushed = true;
    });
    $events = [];
    $dispatcher = new SymfonyEventDispatcher();
    $dispatcher->addListener(BeforeAdminExtensionConfigSaveEvent::class, static function (BeforeAdminExtensionConfigSaveEvent $event) use (&$events): void {
        $events[] = [$event, false];
    });
    $dispatcher->addListener(AfterAdminExtensionConfigSaveEvent::class, static function (AfterAdminExtensionConfigSaveEvent $event) use (&$events, &$flushed): void {
        $events[] = [$event, $flushed];
    });

    $di = container();
    $di['em'] = $em;
    $di['crypt'] = $cryptMock;
    $di['event_dispatcher'] = $dispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['cache'] = new Symfony\Component\Cache\Adapter\ArrayAdapter();

    $serviceMock->setDi($di);
    $result = $serviceMock->setConfig($data);

    expect($result)->toBeTrue()
        ->and($encryptedConfig)->toBe(json_encode($data))
        ->and($events)->toHaveCount(2)
        ->and($events[0][0])->toBeInstanceOf(BeforeAdminExtensionConfigSaveEvent::class)
        ->and($events[0][0]->extensionName)->toBe('extensionName')
        ->and($events[0][0]->configurationKeys)->toBe(['api_key', 'nested', 'password', 'username'])
        ->and($events[0][1])->toBeFalse()
        ->and($events[1][0])->toBeInstanceOf(AfterAdminExtensionConfigSaveEvent::class)
        ->and($events[1][0]->configurationKeys)->toBe(['api_key', 'nested', 'password', 'username'])
        ->and($events[1][1])->toBeTrue();
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

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
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
        ->toThrow(new FOSSBilling\Core\Exception\InformationException('You do not have permission to perform this action', [], 403));
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

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
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
        ->not->toThrow(new FOSSBilling\Core\Exception\InformationException('You do not have permission to perform this action', [], 403));
});

test('hasManagePermission denies configuration of inactive modules without the manage_extensions permission', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();

    $staffMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffMock->shouldReceive('checkPermissionsAndThrowException')
        ->with('extension', 'manage_extensions')
        ->atLeast()
        ->once()
        ->andThrow(new FOSSBilling\Core\Exception\InformationException('You need the "extension.manage_extensions" permission to perform this action', [], 403));

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')
        ->atLeast()
        ->once()
        ->andReturn(false);

    $em = extensionBuildEm($extensionRepository);

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffMock);

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->hasManagePermission('mod_support'))
        ->toThrow(new FOSSBilling\Core\Exception\InformationException('You need the "extension.manage_extensions" permission to perform this action', [], 403));
});

test('hasManagePermission allows configuration of inactive modules with the manage_extensions permission', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();

    $staffMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffMock->shouldReceive('checkPermissionsAndThrowException')
        ->with('extension', 'manage_extensions')
        ->atLeast()
        ->once();

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')
        ->atLeast()
        ->once()
        ->andReturn(false);

    $em = extensionBuildEm($extensionRepository);

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffMock);

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->hasManagePermission('mod_support'))
        ->not->toThrow(new FOSSBilling\Core\Exception\InformationException('You need the "extension.manage_extensions" permission to perform this action', [], 403));
});

test('setConfig denies inactive module configuration without touching storage when the manage_extensions permission is missing', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();

    $staffMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffMock->shouldReceive('checkPermissionsAndThrowException')
        ->with('extension', 'manage_extensions')
        ->atLeast()
        ->once()
        ->andThrow(new FOSSBilling\Core\Exception\InformationException('You need the "extension.manage_extensions" permission to perform this action', [], 403));

    $metaRepository = Mockery::mock(ExtensionMetaRepository::class);
    $metaRepository->shouldNotReceive('findOneByExtensionAndScope');

    $modMock = Mockery::mock(FOSSBilling\Core\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $extensionRepository = Mockery::mock(ExtensionRepository::class);
    $extensionRepository->shouldReceive('existsActiveByTypeAndName')
        ->atLeast()
        ->once()
        ->andReturn(false);

    $em = extensionBuildEm($extensionRepository, $metaRepository, ignoreMissing: false);

    $di = container();
    $di['em'] = $em;
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffMock);

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->setConfig(['ext' => 'mod_support']))
        ->toThrow(new FOSSBilling\Core\Exception\InformationException('You need the "extension.manage_extensions" permission to perform this action', [], 403));
});

test('the typed cron listener refreshes the extension list', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getExtensionsList')->once()->with([])->andReturn([]);

    $service->refreshExtensionsOnCron(new Box\Mod\Cron\Event\BeforeAdminCronRunEvent());
});

/** Run the real download/extraction boundary with a local archive response. */
function extensionArchiveService(PhpZip\ZipFile $zip, string $destination, array &$stagingPaths, bool $safe = true): Service
{
    $filesystem = Mockery::mock(Symfony\Component\Filesystem\Filesystem::class)->makePartial();
    $filesystem->shouldReceive('mkdir')->andReturnUsing(function (string $path, int $mode) use (&$stagingPaths): void {
        $stagingPaths[] = $path;
        (new Symfony\Component\Filesystem\Filesystem())->mkdir($path, $mode);
    });
    if (!$safe) {
        $filesystem->shouldReceive('remove')->once()->andReturnUsing(function (array $paths) use (&$stagingPaths): void {
            $entries = new Symfony\Component\Finder\Finder();
            $entries->in($stagingPaths[0])->ignoreDotFiles(false);
            expect(iterator_count($entries))->toBe(0);
            (new Symfony\Component\Filesystem\Filesystem())->remove($paths);
        });
    }
    $service = Mockery::mock(Service::class, [$filesystem])->makePartial();
    if ($safe) {
        $service->shouldReceive('getExtensionPath')->once()->andReturn($destination);
    } else {
        $service->shouldReceive('getExtensionPath')->never();
    }
    $manager = Mockery::mock(FOSSBilling\Core\Remote\ExtensionManager::class);
    $manager->shouldReceive('getLatestExtensionRelease')->once()->andReturn(['download_url' => 'https://example.test/package.zip']);
    $manager->shouldReceive('isReleaseCompatible')->once()->with(['download_url' => 'https://example.test/package.zip'])->andReturnTrue();
    $staff = Mockery::mock(Box\Mod\Staff\Service::class);
    $staff->shouldReceive('checkPermissionsAndThrowException')->once()->with('extension', 'manage_extensions');
    $di = container();
    $di['em'] = extensionBuildEm();
    $di['extension_manager'] = $manager;
    $di['mod_service'] = $di->protect(fn () => $staff);
    $di['http_client'] = new Symfony\Component\HttpClient\MockHttpClient(new Symfony\Component\HttpClient\Response\MockResponse($zip->outputAsString()));
    $service->setDi($di);

    return $service;
}

test('downloadAndExtract rejects unsafe archives before writing any entry', function (string $name, int $mode): void {
    $zip = new PhpZip\ZipFile();
    $zip->addFromString('valid.php', 'legitimate');
    $zip->addFromString($name, 'malicious');
    $zip->getEntry($name)->setUnixMode($mode);
    $paths = [];
    $destination = Path::join(PATH_CACHE, 'extension-test-' . bin2hex(random_bytes(16)));
    $service = extensionArchiveService($zip, $destination, $paths, false);

    // Rejection must happen before destination resolution or a single extraction write.
    try {
        expect(fn () => $service->downloadAndExtract('mod', 'example'))
            ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'The extension archive contains an unsafe file path or file type');
        expect($paths)->toHaveCount(1)
            ->and(is_dir($paths[0]))->toBeFalse()
            ->and(is_dir($destination))->toBeFalse();
    } finally {
        $zip->close();
        (new Symfony\Component\Filesystem\Filesystem())->remove([...$paths, $destination]);
    }
})->with([
    ['..\\..\\outside.php', 0o100644],
    ['nested\\..\\..\\outside.php', 0o100644],
    ['../outside.php', 0o100644],
    ['.. .\\outside.php', 0o100644],
    ['.../outside.php', 0o100644],
    ['C:\\outside.php', 0o100644],
    ['nested/C:outside.php', 0o100644],
    ["nested/null\0.php", 0o100644],
    ['link', 0o120777],
    ['pipe', 0o010644],
]);

test('downloadAndExtract preserves ordinary package files and directories', function (): void {
    $zip = new PhpZip\ZipFile();
    $zip->addEmptyDir('nested');
    $files = ['manifest.json' => '{}', 'nested/my file v1.2.txt' => 'content', '.hidden' => 'dotfile', 'nested/123' => 'numeric', 'nested/é.txt' => 'unicode'];
    foreach ($files as $name => $content) {
        $zip->addFromString((string) $name, $content);
    }
    $paths = [];
    $destination = Path::join(PATH_CACHE, 'extension-test-' . bin2hex(random_bytes(16)));
    $service = extensionArchiveService($zip, $destination, $paths);

    try {
        expect($service->downloadAndExtract('mod', 'example'))->toBeTrue();
        foreach ($files as $name => $content) {
            expect(file_get_contents(Path::join($destination, (string) $name)))->toBe($content);
        }
        expect(is_dir($destination . '/nested'))->toBeTrue();
        if (DIRECTORY_SEPARATOR !== '\\') {
            expect(fileperms($destination) & 0o777)->toBe(0o755);
        }
    } finally {
        $zip->close();
        (new Symfony\Component\Filesystem\Filesystem())->remove([...$paths, $destination]);
    }
});

test('downloadAndExtract rejects an excluded FOSSBilling version before downloading', function (): void {
    $branch = FOSSBilling\Core\System\Config::getProperty('update_branch', 'release');
    FOSSBilling\Core\System\Config::setProperty('update_branch', 'release', false);

    try {
        $requests = [];
        $di = container();
        $di['cache'] = new Symfony\Component\Cache\Adapter\ArrayAdapter();
        $di['http_client'] = new Symfony\Component\HttpClient\MockHttpClient(function ($method, $url) use (&$requests) {
            $requests[] = $url;
            expect($url)->toBe('https://api.fossbilling.net/v2/extensions/example');

            return new Symfony\Component\HttpClient\Response\MockResponse(json_encode(['result' => [
                'id' => 'example', 'name' => 'Example', 'author' => ['name' => 'Author'],
                'releases' => [['tag' => '1.0.0', 'download_url' => 'https://example.test/archive.zip', 'requires' => ['fossbilling' => '>=0.0.0 !=' . FOSSBilling\Core\System\Version::VERSION]]],
            ]], JSON_THROW_ON_ERROR));
        });
        $manager = new FOSSBilling\Core\Remote\ExtensionManager();
        $manager->setDi($di);
        $di['extension_manager'] = $manager;
        $staff = Mockery::mock(Box\Mod\Staff\Service::class);
        $staff->shouldReceive('checkPermissionsAndThrowException')->once();
        $di['mod_service'] = $di->protect(fn () => $staff);
        $service = new Service();
        $service->setDi($di);
        expect(fn () => $service->downloadAndExtract('module', 'example'))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'This extension is not compatible with your version of FOSSBilling.');
        expect($requests)->toHaveCount(1);
    } finally {
        FOSSBilling\Core\System\Config::setProperty('update_branch', $branch, false);
    }
});

test('downloadAndExtract checks the selected release without fetching latest twice', function (): void {
    $branch = FOSSBilling\Core\System\Config::getProperty('update_branch', 'release');
    FOSSBilling\Core\System\Config::setProperty('update_branch', 'release', false);

    try {
        $selected = ['download_url' => 'https://example.test/selected.zip', 'requires' => ['fossbilling' => '!=' . FOSSBilling\Core\System\Version::VERSION]];
        $replacement = ['download_url' => 'https://example.test/replacement.zip', 'requires' => ['fossbilling' => '*']];
        $manager = Mockery::mock(FOSSBilling\Core\Remote\ExtensionManager::class)->makePartial();
        $manager->shouldReceive('getLatestExtensionRelease')->once()->with('example')->andReturn($selected, $replacement);
        $manager->shouldReceive('isExtensionCompatible')->never();
        $staff = Mockery::mock(Box\Mod\Staff\Service::class);
        $staff->shouldReceive('checkPermissionsAndThrowException')->once();
        $filesystem = Mockery::mock(Symfony\Component\Filesystem\Filesystem::class);
        $filesystem->shouldReceive('mkdir')->never();
        $di = container();
        $di['extension_manager'] = $manager;
        $di['mod_service'] = $di->protect(fn () => $staff);
        $service = new Service($filesystem);
        $service->setDi($di);
        expect(fn () => $service->downloadAndExtract('module', 'example'))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'This extension is not compatible with your version of FOSSBilling.');
    } finally {
        FOSSBilling\Core\System\Config::setProperty('update_branch', $branch, false);
    }
});
