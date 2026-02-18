<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

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

test('getDi returns the dependency injection container', function (): void {
    $service = new Box\Mod\Extension\Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('isCoreModule checks if module is core module', function (): void {
    $service = new Box\Mod\Extension\Service();
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
});

test('isExtensionActive returns false when module not found', function (): void {
    $service = new Box\Mod\Extension\Service();
    $coreModules = ['extension', 'cron', 'staff'];
    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn($coreModules);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()
        ->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $service->setDi($di);

    $result = $service->isExtensionActive('mod', 'ModDoesNotExists');
    expect($result)->toBeBool();
    expect($result)->toBeFalse();
});

test('removeNotExistingModules removes non-existing modules', function (): void {
    $service = new Box\Mod\Extension\Service();
    $model = new Model_Extension();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->name = 'extensionName';

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getManifest')->andThrow(new Exception());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()
        ->once()
        ->andReturn([$model]);
    $dbMock->shouldReceive('trash')
        ->atLeast()
        ->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $service->setDi($di);

    $result = $service->removeNotExistingModules();
    expect($result)->toBeInt();
    expect($result > 0)->toBeTrue();
});

// Data provider for search query tests
$searchQueryData = [
    [[], 'SELECT * FROM extension', []],
    [['type' => 'mod'], 'AND type = :type', [':type' => 'mod']],
    [['search' => 'FindUp'], 'AND name LIKE :search', [':search' => 'FindUp']],
];

test('getSearchQuery returns correct SQL and params', function (array $data, string $expectedStr, array $expectedParams) {
    $service = new Box\Mod\Extension\Service();
    $di = container();

    $service->setDi($di);
    [$sql, $params] = $service->getSearchQuery($data);

    expect($sql)->toBeString();
    expect($params)->toBeArray();

    expect(str_contains($sql, $expectedStr))->toBeTrue();
    expect([])->toBe(array_diff_key($params, $expectedParams));
})->with($searchQueryData);

test('getExtensionsList returns filtered extensions list', function (): void {
    $service = new Box\Mod\Extension\Service();
    $data = [
        'has_settings' => true,
        'active' => true,
    ];

    $model['manifest'] = '{"J":5,"0":"N"}';
    $model['type'] = 'mod';
    $model['status'] = 'installed';
    $model['name'] = 'extensionName';
    $model['version'] = '1';

    $modelFind = new Model_Extension();
    $modelFind->loadBean(new Tests\Helpers\DummyBean());
    $modelFind->name = 'extensionName';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()
        ->once()
        ->andReturn([$model]);

    $dbMock->shouldReceive('find')
        ->atLeast()
        ->once()
        ->andReturn([$modelFind]);

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
    $di['db'] = $dbMock;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $service->setDi($di);

    $result = $service->getExtensionsList($data);
    expect($result)->toBeArray();
});

test('getExtensionsList returns only installed extensions', function (): void {
    $service = new Box\Mod\Extension\Service();
    $data = [
        'installed' => true,
    ];

    $model['manifest'] = '{"J":5,"0":"N"}';
    $model['type'] = 'mod';
    $model['status'] = 'installed';
    $model['name'] = 'extensionName';
    $model['version'] = '1';

    $modelFind = new Model_Extension();
    $modelFind->loadBean(new Tests\Helpers\DummyBean());
    $modelFind->name = 'extensionName';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()
        ->once()
        ->andReturn([$model]);

    $dbMock->shouldReceive('find')
        ->atLeast()
        ->once()
        ->andReturn([$modelFind]);

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
    $di['db'] = $dbMock;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $service->setDi($di);

    $result = $service->getExtensionsList($data);
    expect($result)->toBeArray();
});

test('getAdminNavigation returns admin navigation', function (): void {
    $service = new Box\Mod\Extension\Service();
    $extensionServiceMock = Mockery::mock(Box\Mod\Extension\Service::class)->makePartial();
    $extensionServiceMock->shouldAllowMockingProtectedMethods();
    $extensionServiceMock->shouldReceive('getConfig')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffServiceMock->shouldReceive('hasPermission')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $dbalMock = new class {
        public function createQueryBuilder(): object
        {
            return new class {
                public function select($field)
                {
                    return $this;
                }

                public function from($table)
                {
                    return $this;
                }

                public function where($cond)
                {
                    return $this;
                }

                public function andWhere($cond)
                {
                    return $this;
                }

                public function setParameter($key, $val)
                {
                    return $this;
                }

                public function executeQuery()
                {
                    return $this;
                }

                public function fetchFirstColumn(): array
                {
                    return [];
                }
            };
        }
    };

    $link = 'extension';

    $urlMock = Mockery::mock('Box_Url');
    $urlMock->shouldReceive('adminLink')
        ->atLeast()
        ->once()
        ->andReturn('http://fossbilling.org/index.php?_url=/' . $link);

    $di = container();
    $di['mod'] = $di->protect(function ($name) use ($di) {
        $mod = new FOSSBilling\Module($name);
        $mod->setDi($di);

        return $mod;
    });
    $di['tools'] = new FOSSBilling\Tools();
    $di['mod_service'] = $di->protect(function ($mod) use ($extensionServiceMock, $staffServiceMock) {
        if ($mod == 'staff') {
            return $staffServiceMock;
        }

        return $extensionServiceMock;
    });
    $di['url'] = $urlMock;
    $di['dbal'] = $dbalMock;

    $service->setDi($di);
    $result = $service->getAdminNavigation(new Model_Admin());
    expect($result)->toBeArray();
});

test('findExtension finds an extension', function (): void {
    $service = new Box\Mod\Extension\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn(new Model_Extension());

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->findExtension('mod', 'id');
    expect($result)->toBeInstanceOf('\Model_Extension');
});

test('update throws exception for extensions that need manual update', function (): void {
    $service = new Box\Mod\Extension\Service();
    $model = new Model_Extension();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->type = 'mod';
    $model->name = 'testExtension';
    $model->version = '2';

    $extensionStub = Mockery::mock(FOSSBilling\ExtensionManager::class);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $di = container();
    $di['extension_manager'] = $extensionStub;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service->setDi($di);

    expect(fn () => $service->update($model))
        ->toThrow(FOSSBilling\Exception::class, 'Visit the extension directory for more information on updating this extension.');
});

test('activate activates an extension', function (): void {
    $service = new Box\Mod\Extension\Service();
    $ext = new Model_Extension();
    $ext->loadBean(new Tests\Helpers\DummyBean());
    $ext->type = 'mod';
    $ext->name = 'testExtension';

    $expectedResult = [
        'id' => $ext->name,
        'type' => $ext->type,
        'redirect' => true,
        'has_settings' => true,
    ];

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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()
        ->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service->setDi($di);
    $result = $service->activate($ext);
    expect($result)->toBeArray();
    expect($result)->toBe($expectedResult);
});

test('deactivate deactivates an extension', function (): void {
    $service = new Box\Mod\Extension\Service();
    $ext = new Model_Extension();
    $ext->loadBean(new Tests\Helpers\DummyBean());
    $ext->type = 'mod';
    $ext->name = 'extensionTest';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')->atLeast()->once();

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service->setDi($di);

    $result = $service->deactivate($ext);
    expect($result)->toBeTrue();
});

test('deactivate throws exception for core modules', function (): void {
    $service = new Box\Mod\Extension\Service();
    $ext = new Model_Extension();
    $ext->loadBean(new Tests\Helpers\DummyBean());
    $ext->type = 'mod';
    $ext->name = 'extensionTest';

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn([$ext->name]);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $di = container();
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service->setDi($di);

    expect(fn () => $service->deactivate($ext))
        ->toThrow(FOSSBilling\Exception::class, 'Core modules are an integral part of the FOSSBilling system and cannot be deactivated.');
});

test('deactivate deactivates hook extension', function (): void {
    $service = new Box\Mod\Extension\Service();
    $ext = new Model_Extension();
    $ext->loadBean(new Tests\Helpers\DummyBean());
    $ext->type = 'hook';
    $ext->name = 'extensionTest';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')->atLeast()->once();

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $filesystemMock = Mockery::mock(Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('exists')->atLeast()->once()->andReturn(true);
    $filesystemMock->shouldReceive('remove')->atLeast()->once();

    $service = new Box\Mod\Extension\Service($filesystemMock);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service->setDi($di);
    $result = $service->deactivate($ext);
    expect($result)->toBeTrue();
});

test('deactivate deactivates module', function (): void {
    $service = new Box\Mod\Extension\Service();
    $ext = new Model_Extension();
    $ext->loadBean(new Tests\Helpers\DummyBean());
    $ext->type = 'mod';
    $ext->name = 'extensionTest';

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod'] = $di->protect(fn ($name): Mockery\MockInterface => $modMock);

    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffService);

    $service->setDi($di);

    $result = $service->deactivate($ext);
    expect($result)->toBeTrue();
});

test('uninstall uninstalls an extension', function (): void {
    $service = new Box\Mod\Extension\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')->andReturn(null);

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

    // Create the service with filesystem in constructor, then make it a partial mock
    $serviceMock = Mockery::mock(Box\Mod\Extension\Service::class . '[getExtensionPath]', [$filesystemMock]);
    $serviceMock->shouldReceive('getExtensionPath')
        ->atLeast()
        ->once()
        ->andReturn($tmpDir);

    $di['db'] = $dbMock;
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

test('downloadAndExtract throws exception when download URL is missing', function (): void {
    $service = new Box\Mod\Extension\Service();
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

    expect(fn () => $service->downloadAndExtract('mod', 'extensionId'))
        ->toThrow(Exception::class, 'Couldn\'t find a valid download URL for the extension.');
});

test('getInstalledMods returns installed modules', function (): void {
    $service = new Box\Mod\Extension\Service();
    $dbalMock = new class {
        public function createQueryBuilder(): object
        {
            return new class {
                public function select($field)
                {
                    return $this;
                }

                public function from($table)
                {
                    return $this;
                }

                public function where($cond)
                {
                    return $this;
                }

                public function andWhere($cond)
                {
                    return $this;
                }

                public function setParameter($key, $val)
                {
                    return $this;
                }

                public function executeQuery()
                {
                    return $this;
                }

                public function fetchFirstColumn(): array
                {
                    return [];
                }
            };
        }
    };

    $di = new Pimple\Container();
    $di['dbal'] = $dbalMock;

    $service->setDi($di);
    $result = $service->getInstalledMods();
    expect($result)->toBe([]);
});

test('activateExistingExtension activates existing extension', function (): void {
    $service = new Box\Mod\Extension\Service();
    $data = [
        'id' => 'extensionId',
        'type' => 'extensionType',
    ];

    $model = new Model_Extension();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Box\Mod\Extension\Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('findExtension')
        ->atLeast()
        ->once()
        ->andReturnUsing(function () use ($model) {
            static $callCount = 0;
            ++$callCount;

            return $callCount === 1 ? null : $model;
        });
    $serviceMock->shouldReceive('activate')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('dispense')
        ->atLeast()
        ->once()
        ->andReturn($model);
    $dbMock->shouldReceive('store')
        ->atLeast()
        ->once()
        ->andReturn(1);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->activateExistingExtension($data);
    expect($result)->toBeArray();
});

test('activateExistingExtension throws exception on activation failure', function (): void {
    $service = new Box\Mod\Extension\Service();
    $data = [
        'id' => 'extensionId',
        'type' => 'extensionType',
    ];

    $model = new Model_Extension();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Box\Mod\Extension\Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('findExtension')
        ->atLeast()
        ->once()
        ->andReturn($model);
    $serviceMock->shouldReceive('activate')
        ->atLeast()
        ->once()
        ->andThrow(new Exception());

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventMock;

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->activateExistingExtension($data))
        ->toThrow(Exception::class);
});

test('getConfig returns extension config', function (): void {
    $service = new Box\Mod\Extension\Service();
    $data = [
        'ext' => 'extensionName',
    ];

    $model = new Model_ExtensionMeta();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn($model);

    $cryptMock = Mockery::mock(Box_Crypt::class);
    $cryptMock->shouldReceive('decrypt')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['crypt'] = $cryptMock;
    $di['cache'] = new Symfony\Component\Cache\Adapter\ArrayAdapter();

    $service->setDi($di);

    $result = $service->getConfig($data['ext']);
    expect($result)->toBeArray();
});

test('getConfig creates new ExtensionMeta when not found', function (): void {
    $service = new Box\Mod\Extension\Service();
    $data = [
        'ext' => 'extensionName',
    ];

    $model = new Model_ExtensionMeta();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn(null);
    $dbMock->shouldReceive('dispense')
        ->atLeast()
        ->once()
        ->andReturn($model);
    $dbMock->shouldReceive('store')
        ->atLeast()
        ->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['cache'] = new Symfony\Component\Cache\Adapter\ArrayAdapter();

    $service->setDi($di);
    $result = $service->getConfig($data['ext']);

    expect($result)->toBeArray();
    expect($result)->toBe(['ext' => 'extensionName']);
});

test('setConfig sets extension config', function (): void {
    $service = new Box\Mod\Extension\Service();
    $data = [
        'ext' => 'extensionName',
    ];

    $serviceMock = Mockery::mock(Box\Mod\Extension\Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
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

    $model = new Model_ExtensionMeta();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('exec')
        ->atLeast()
        ->once()
        ->andReturn([]);
    $dbMock->shouldReceive('getCell')
        ->atLeast()
        ->once()
        ->andReturn(1);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $staffMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffMock->shouldReceive('hasPermission')->atLeast()->once()->andReturn(true);

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getCoreModules')->atLeast()->once()->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $di['tools'] = $toolsMock;
    $di['crypt'] = $cryptMock;
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffMock);
    $di['cache'] = new Symfony\Component\Cache\Adapter\ArrayAdapter();

    $serviceMock->setDi($di);
    $result = $serviceMock->setConfig($data);

    expect($result)->toBeTrue();
});
