<?php

namespace Box\Mod\Extension;

class PdoMock extends \PDO
{
    public function __construct()
    {
    }
}
class PdoStatmentsMock extends \PDOStatement
{
    public function __construct()
    {
    }
}

class ServiceTest extends \BBTestCase
{
    /**
     * @var Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new Service();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testisCoreModule(): void
    {
        $coreModules = ['extension', 'cron', 'staff'];
        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn($coreModules);

        $di = new \Pimple\Container();
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->isCoreModule('extension');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testisExtensionActiveModNotFound(): void
    {
        $coreModules = ['extension', 'cron', 'staff'];
        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn($coreModules);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->isExtensionActive('mod', 'ModDoesNotExists');
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testremoveNotExistingModules(): void
    {
        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());
        $model->name = 'extensionName';

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')->willThrowException(new \Exception());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$model]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->removeNotExistingModules();
        $this->assertIsInt($result);
        $this->assertTrue($result > 0);
    }

    public static function searchQueryData(): array
    {
        return [
            [[], 'SELECT * FROM extension', []],
            [['type' => 'mod'], 'AND type = :type', [':type' => 'mod']],
            [['search' => 'FindUp'], 'AND name LIKE :search', [':search' => 'FindUp']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('searchQueryData')]
    public function testgetSearchQuery(array $data, string $expectedStr, array $expectedParams): void
    {
        $di = new \Pimple\Container();

        $this->service->setDi($di);
        [$sql, $params] = $this->service->getSearchQuery($data);

        $this->assertIsString($sql);
        $this->assertIsArray($params);

        $this->assertTrue(str_contains($sql, $expectedStr), $sql);
        $this->assertTrue(array_diff_key($params, $expectedParams) == []);
    }

    public function testgetExtensionsList(): void
    {
        $data = [
            'has_settings' => true,
            'active' => true,
        ];

        $model['manifest'] = '{"J":5,"0":"N"}';
        $model['type'] = 'mod';
        $model['status'] = 'installed';
        $model['name'] = 'extensionName';
        $model['version'] = '1';

        $modelFind = new \Model_Extension();
        $modelFind->loadBean(new \DummyBean());
        $modelFind->name = 'extensionName';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([$model]);

        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$modelFind]);

        $coreModules = ['extension', 'cron', 'staff'];
        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn($coreModules);
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')
            ->willReturn([]);
        $modMock->expects($this->atLeastOnce())
            ->method('hasSettingsPage')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->getExtensionsList($data);
        $this->assertIsArray($result);
    }

    public function testgetExtensionsListOnlyInstalled(): void
    {
        $data = [
            'installed' => true,
        ];

        $model['manifest'] = '{"J":5,"0":"N"}';
        $model['type'] = 'mod';
        $model['status'] = 'installed';
        $model['name'] = 'extensionName';
        $model['version'] = '1';

        $modelFind = new \Model_Extension();
        $modelFind->loadBean(new \DummyBean());
        $modelFind->name = 'extensionName';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([$model]);

        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$modelFind]);

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')
            ->willReturn([]);
        $modMock->expects($this->atLeastOnce())
            ->method('hasSettingsPage')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->getExtensionsList($data);
        $this->assertIsArray($result);
    }

    public function testgetAdminNavigation(): void
    {
        $extensionServiceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getConfig'])->getMock();
        $extensionServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $staffServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $staffServiceMock->expects($this->atLeastOnce())
            ->method('hasPermission')
            ->willReturn(true);

        $pdoStatment = $this->getMockBuilder('\\' . PdoStatmentsMock::class)->getMock();
        $pdoStatment->expects($this->atLeastOnce())
            ->method('execute');
        $pdoStatment->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->willReturn([]);

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatment);

        $link = 'extension';

        $urlMock = $this->getMockBuilder('Box_Url')->getMock();
        $urlMock->expects($this->atLeastOnce())
            ->method('adminLink')
            ->willReturn('http://fossbilling.org/index.php?_url=/' . $link);
        $di['url'] = $urlMock;

        $di = new \Pimple\Container();
        $di['mod'] = $di->protect(function ($name) use ($di) {
            $mod = new \Box_Mod($name);
            $mod->setDi($di);

            return $mod;
        });
        $di['tools'] = new \FOSSBilling\Tools();
        $di['mod_service'] = $di->protect(function ($mod) use ($extensionServiceMock, $staffServiceMock) {
            if ($mod == 'staff') {
                return $staffServiceMock;
            } else {
                return $extensionServiceMock;
            }
        });
        $di['pdo'] = $pdoMock;
        $di['url'] = $urlMock;

        $this->service->setDi($di);
        $result = $this->service->getAdminNavigation(new \Model_Admin());

        $this->assertIsArray($result);
    }

    public function testfindExtension(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_Extension());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->findExtension('mod', 'id');
        $this->assertInstanceOf('\Model_Extension', $result);
    }

    public function testupdate(): void
    {
        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());
        $model->type = 'mod';
        $model->name = 'testExtension';
        $model->version = '2';

        $extensionMock = $this->getMockBuilder('\\' . \FOSSBilling\ExtensionManager::class)->getMock();

        $staffService = $this->getMockBuilder(\Box\Mod\Staff\Service::class)->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = new \Pimple\Container();
        $di['extension_manager'] = $extensionMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(252);
        $this->expectExceptionMessage('Visit the extension directory for more information on updating this extension.');
        $this->service->update($model);
    }

    public function testactivate(): void
    {
        $ext = new \Model_Extension();
        $ext->loadBean(new \DummyBean());
        $ext->type = 'mod';
        $ext->name = 'testExtension';

        $expectedResult = [
            'id' => $ext->name,
            'type' => $ext->type,
            'redirect' => true,
            'has_settings' => true,
        ];

        $staffService = $this->getMockBuilder(\Box\Mod\Staff\Service::class)->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')
            ->willReturn(['version' => 1]);

        $modMock->expects($this->atLeastOnce())
            ->method('hasAdminController')
            ->willReturn(true);

        $modMock->expects($this->atLeastOnce())
            ->method('hasSettingsPage')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);
        $result = $this->service->activate($ext);
        $this->assertIsArray($result);
        $this->assertEquals($expectedResult, $result);
    }

    public function testdeactivate(): void
    {
        $ext = new \Model_Extension();
        $ext->loadBean(new \DummyBean());
        $ext->type = 'mod';
        $ext->name = 'extensionTest';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn([]);

        $staffService = $this->getMockBuilder(\Box\Mod\Staff\Service::class)->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);

        $result = $this->service->deactivate($ext);
        $this->assertTrue($result);
    }

    public function testdeactivateCoreModuleException(): void
    {
        $ext = new \Model_Extension();
        $ext->loadBean(new \DummyBean());
        $ext->type = 'mod';
        $ext->name = 'extensionTest';

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn([$ext->name]);

        $staffService = $this->getMockBuilder(\Box\Mod\Staff\Service::class)->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = new \Pimple\Container();
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Core modules are an integral part of the FOSSBilling system and cannot be deactivated.');
        $this->service->deactivate($ext);
    }

    public function testdeactivateExtensionHook(): void
    {
        $ext = new \Model_Extension();
        $ext->loadBean(new \DummyBean());
        $ext->type = 'hook';
        $ext->name = 'extensionTest';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $staffService = $this->getMockBuilder(\Box\Mod\Staff\Service::class)->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);
        $result = $this->service->deactivate($ext);
        $this->assertTrue($result);
    }

    public function testuninstall(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn([]);

        $modMock->expects($this->atLeastOnce())
            ->method('uninstall')
            ->willReturn(true);

        $staffService = $this->getMockBuilder(\Box\Mod\Staff\Service::class)->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);

        $result = $this->service->uninstall('mod', 'Branding');
        $this->assertTrue($result);
    }

    public function testdownloadAndExtractDownloadUrlMissing(): void
    {
        $extensionMock = $this->getMockBuilder(\FOSSBilling\ExtensionManager::class)->getMock();

        $extensionMock->expects($this->atLeastOnce())
            ->method('getLatestExtensionRelease')
            ->willReturn([]);

        $staffService = $this->getMockBuilder(\Box\Mod\Staff\Service::class)->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = new \Pimple\Container();
        $di['extension_manager'] = $extensionMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Couldn\'t find a valid download URL for the extension.');
        $this->service->downloadAndExtract('mod', 'extensionId');
    }

    public function testgetInstalledMods(): void
    {
        $pdoStatment = $this->getMockBuilder(PdoStatmentsMock::class)->getMock();
        $pdoStatment->expects($this->atLeastOnce())
            ->method('execute');
        $pdoStatment->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->willReturn([]);

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatment);

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;

        $this->service->setDi($di);
        $result = $this->service->getInstalledMods();
        $this->assertEquals([], $result);
    }

    public function testactivateExistingExtension(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['findExtension', 'activate'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->willReturnOnConsecutiveCalls(null, $model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('activate')
            ->willReturn([]);

        $dbMock = $this->getMockBuilder(\Box_Database::class)->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $eventMock = $this->getMockBuilder(\Box_EventManager::class)->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->activateExistingExtension($data);
        $this->assertIsArray($result);
    }

    public function testactivateException(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['findExtension', 'activate'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('activate')
            ->will($this->throwException(new \Exception()));

        $eventMock = $this->getMockBuilder(\Box_EventManager::class)->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;

        $serviceMock->setDi($di);

        $this->expectException(\Exception::class);
        $serviceMock->activateExistingExtension($data);
    }

    public function testgetConfig(): void
    {
        $data = [
            'ext' => 'extensionName',
        ];

        $model = new \Model_ExtensionMeta();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder(\Box_Database::class)->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $cryptMock = $this->getMockBuilder(\Box_Crypt::class)->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('decrypt');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['crypt'] = $cryptMock;
        $di['cache'] = new \Symfony\Component\Cache\Adapter\ArrayAdapter();

        $this->service->setDi($di);

        $result = $this->service->getConfig($data['ext']);
        $this->assertIsArray($result);
    }

    public function testgetConfigExtensionMetaNotFound(): void
    {
        $data = [
            'ext' => 'extensionName',
        ];

        $model = new \Model_ExtensionMeta();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder(\Box_Database::class)->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['cache'] = new \Symfony\Component\Cache\Adapter\ArrayAdapter();

        $this->service->setDi($di);
        $result = $this->service->getConfig($data['ext']);

        $this->assertIsArray($result);
        $this->assertEquals(['ext' => 'extensionName'], $result);
    }

    public function testsetConfig(): void
    {
        $data = [
            'ext' => 'extensionName',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getConfig', 'getCoreAndActiveModulesAndPermissions'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $toolsMock = $this->getMockBuilder(\FOSSBilling\Tools::class)->getMock();

        $cryptMock = $this->getMockBuilder(\Box_Crypt::class)->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt')
            ->willReturn('encryptedConfig');

        $model = new \Model_ExtensionMeta();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder(\Box_Database::class)->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec')
            ->willReturn([]);

        $eventMock = $this->getMockBuilder(\Box_EventManager::class)->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $staffMock = $this->getMockBuilder(\Box\Mod\Staff\Service::class)->getMock();

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())->method('getCoreModules')->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['tools'] = $toolsMock;
        $di['crypt'] = $cryptMock;
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffMock);
        $di['cache'] = new \Symfony\Component\Cache\Adapter\ArrayAdapter();

        $serviceMock->setDi($di);
        $result = $serviceMock->setConfig($data);

        $this->assertTrue($result);
    }
}
