<?php

declare(strict_types=1);

namespace Box\Mod\Extension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;

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

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;
    protected $filesystemMock;

    public function setUp(): void
    {
        $this->filesystemMock = $this->createMock(Filesystem::class);
        $this->service = new Service($this->filesystemMock);
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testIsCoreModule(): void
    {
        $coreModules = ['extension', 'cron', 'staff'];
        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn($coreModules);

        $di = $this->getDi();
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->isCoreModule('extension');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testIsExtensionActiveModNotFound(): void
    {
        $coreModules = ['extension', 'cron', 'staff'];
        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn($coreModules);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->isExtensionActive('mod', 'ModDoesNotExists');
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testRemoveNotExistingModules(): void
    {
        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());
        $model->name = 'extensionName';

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')->willThrowException(new \Exception());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$model]);

        $di = $this->getDi();
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

    #[DataProvider('searchQueryData')]
    public function testGetSearchQuery(array $data, string $expectedStr, array $expectedParams): void
    {
        $di = $this->getDi();

        $this->service->setDi($di);
        [$sql, $params] = $this->service->getSearchQuery($data);

        $this->assertIsString($sql);
        $this->assertIsArray($params);

        $this->assertTrue(str_contains($sql, $expectedStr), $sql);
        $this->assertEquals([], array_diff_key($params, $expectedParams));
    }

    public function testGetExtensionsList(): void
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

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([$model]);

        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$modelFind]);

        $coreModules = ['extension', 'cron', 'staff'];
        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn($coreModules);
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')
            ->willReturn([]);
        $modMock->expects($this->atLeastOnce())
            ->method('hasSettingsPage')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->getExtensionsList($data);
        $this->assertIsArray($result);
    }

    public function testGetExtensionsListOnlyInstalled(): void
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

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([$model]);

        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$modelFind]);

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')
            ->willReturn([]);
        $modMock->expects($this->atLeastOnce())
            ->method('hasSettingsPage')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);

        $result = $this->service->getExtensionsList($data);
        $this->assertIsArray($result);
    }

    public function testGetAdminNavigation(): void
    {
        $extensionServiceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getConfig'])->getMock();
        $extensionServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->atLeastOnce())
            ->method('hasPermission')
            ->willReturn(true);

        $pdoStatment = $this->createMock(PdoStatmentsMock::class);
        $pdoStatment->expects($this->atLeastOnce())
            ->method('execute');
        $pdoStatment->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->willReturn([]);

        $pdoMock = $this->createMock(PdoMock::class);
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatment);

        $link = 'extension';

        $urlMock = $this->createMock('Box_Url');
        $urlMock->expects($this->atLeastOnce())
            ->method('adminLink')
            ->willReturn('http://fossbilling.org/index.php?_url=/' . $link);
        $di['url'] = $urlMock;

        $di = $this->getDi();
        $di['mod'] = $di->protect(function ($name) use ($di) {
            $mod = new \FOSSBilling\Module($name);
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

    public function testFindExtension(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_Extension());

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->findExtension('mod', 'id');
        $this->assertInstanceOf('\Model_Extension', $result);
    }

    public function testUpdate(): void
    {
        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());
        $model->type = 'mod';
        $model->name = 'testExtension';
        $model->version = '2';

        $extensionMock = $this->createMock(\FOSSBilling\ExtensionManager::class);

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();
        $di['extension_manager'] = $extensionMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(252);
        $this->expectExceptionMessage('Visit the extension directory for more information on updating this extension.');
        $this->service->update($model);
    }

    public function testActivate(): void
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

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')
            ->willReturn(['version' => 1]);

        $modMock->expects($this->atLeastOnce())
            ->method('hasAdminController')
            ->willReturn(true);

        $modMock->expects($this->atLeastOnce())
            ->method('hasSettingsPage')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);
        $result = $this->service->activate($ext);
        $this->assertIsArray($result);
        $this->assertEquals($expectedResult, $result);
    }

    public function testDeactivate(): void
    {
        $ext = new \Model_Extension();
        $ext->loadBean(new \DummyBean());
        $ext->type = 'mod';
        $ext->name = 'extensionTest';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn([]);

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);

        $result = $this->service->deactivate($ext);
        $this->assertTrue($result);
    }

    public function testDeactivateCoreModuleException(): void
    {
        $ext = new \Model_Extension();
        $ext->loadBean(new \DummyBean());
        $ext->type = 'mod';
        $ext->name = 'extensionTest';

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn([$ext->name]);

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Core modules are an integral part of the FOSSBilling system and cannot be deactivated.');
        $this->service->deactivate($ext);
    }

<<<<<<<<< Temporary merge branch 1
    {
        $ext = new \Model_Extension();
        $ext->loadBean(new \DummyBean());
        $ext->type = 'hook';
        $ext->name = 'extensionTest';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);
        $result = $this->service->deactivate($ext);
        $this->assertTrue($result);
    }

    public function testDeactivateUninstallException(): void
    {
        $ext = new \Model_Extension();
        $ext->loadBean(new \DummyBean());
        $ext->type = 'mod';
        $ext->name = 'extensionTest';

        $exceptionMessage = 'testException';

        $modMock = $this->getMockBuilder('\' . \FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn([]);

        $modMock->expects($this->atLeastOnce())
            ->method('uninstall')
            ->willThrowException(new \FOSSBilling\Exception($exceptionMessage));

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->service->deactivate($ext);
    }
=========
>>>>>>>>> Temporary merge branch 2
    public function testDeactivateExtensionHook(): void
    {
        $ext = new \Model_Extension();
        $ext->loadBean(new \DummyBean());
        $ext->type = 'hook';
        $ext->name = 'extensionTest';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);
        $result = $this->service->deactivate($ext);
        $this->assertTrue($result);
    }

    public function testUninstall(): void
    {
        $dbMock = $this->createMock('\Box_Database');

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->willReturn([]);

        $modMock->expects($this->atLeastOnce())
            ->method('uninstall')
            ->willReturn(true);

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();

        // Only mock getExtensionPath to return our temp dir, let other methods work normally
        $serviceMock = $this->getMockBuilder(\Box\Mod\Extension\Service::class)
            ->onlyMethods(['getExtensionPath'])
            ->setConstructorArgs([$this->filesystemMock])
            ->getMock();

        // Create temp directory that actually exists for the first test
        $tmpDir = sys_get_temp_dir() . '/fb_test_ext_' . uniqid();
        mkdir($tmpDir, 0755, true);

        // Configure getExtensionPath to return the temp directory
        $serviceMock->expects($this->atLeastOnce())
            ->method('getExtensionPath')
            ->willReturn($tmpDir);

        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['mod'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $di['mod_service'] = $di->protect(function ($name) use ($staffService) {
            if ($name === 'Staff') {
                return $staffService;
            }

            return null;
        });

        $serviceMock->setDi($di);

        // Set up filesystem mock to return true for exists() before calling uninstall
        $this->filesystemMock->expects($this->atLeastOnce())
            ->method('exists')
            ->willReturn(true);

        $result = $serviceMock->uninstall('mod', 'TestExtension');
        $this->assertTrue($result);

        // Clean up temp directory
        if (is_dir($tmpDir)) {
            rmdir($tmpDir);
        }

        $result = $serviceMock->uninstall('mod', 'Branding');
        $this->assertTrue($result);
    }

    public function testDownloadAndExtractDownloadUrlMissing(): void
    {
        $extensionMock = $this->createMock(\FOSSBilling\ExtensionManager::class);

        $extensionMock->expects($this->atLeastOnce())
            ->method('getLatestExtensionRelease')
            ->willReturn([]);

        $staffService = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException');

        $di = $this->getDi();
        $di['extension_manager'] = $extensionMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffService);

        $this->service->setDi($di);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Couldn\'t find a valid download URL for the extension.');
        $this->service->downloadAndExtract('mod', 'extensionId');
    }

    public function testGetInstalledMods(): void
    {
        $pdoStatment = $this->createMock(PdoStatmentsMock::class);
        $pdoStatment->expects($this->atLeastOnce())
            ->method('execute');
        $pdoStatment->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->willReturn([]);

        $pdoMock = $this->createMock(PdoMock::class);
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatment);

        $di = $this->getDi();
        $di['pdo'] = $pdoMock;

        $this->service->setDi($di);
        $result = $this->service->getInstalledMods();
        $this->assertSame([], $result);
    }

    public function testActivateExistingExtension(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['findExtension', 'activate'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->willReturnOnConsecutiveCalls(null, $model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('activate')
            ->willReturn([]);

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $eventMock = $this->createMock(\Box_EventManager::class);
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->activateExistingExtension($data);
        $this->assertIsArray($result);
    }

    public function testActivateException(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['findExtension', 'activate'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('activate')
            ->will($this->throwException(new \Exception()));

        $eventMock = $this->createMock(\Box_EventManager::class);
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;

        $serviceMock->setDi($di);

        $this->expectException(\Exception::class);
        $serviceMock->activateExistingExtension($data);
    }

    public function testGetConfig(): void
    {
        $data = [
            'ext' => 'extensionName',
        ];

        $model = new \Model_ExtensionMeta();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $cryptMock = $this->createMock(\Box_Crypt::class);
        $cryptMock->expects($this->atLeastOnce())
            ->method('decrypt');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['crypt'] = $cryptMock;
        $di['cache'] = new \Symfony\Component\Cache\Adapter\ArrayAdapter();

        $this->service->setDi($di);

        $result = $this->service->getConfig($data['ext']);
        $this->assertIsArray($result);
    }

    public function testGetConfigExtensionMetaNotFound(): void
    {
        $data = [
            'ext' => 'extensionName',
        ];

        $model = new \Model_ExtensionMeta();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['cache'] = new \Symfony\Component\Cache\Adapter\ArrayAdapter();

        $this->service->setDi($di);
        $result = $this->service->getConfig($data['ext']);

        $this->assertIsArray($result);
        $this->assertEquals(['ext' => 'extensionName'], $result);
    }

    public function testSetConfig(): void
    {
        $data = [
            'ext' => 'extensionName',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getConfig', 'getCoreAndActiveModulesAndPermissions'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);

        $cryptMock = $this->createMock(\Box_Crypt::class);
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt')
            ->willReturn('encryptedConfig');

        $model = new \Model_ExtensionMeta();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->atLeastOnce())
            ->method('exec')
            ->willReturn([]);

        $eventMock = $this->createMock(\Box_EventManager::class);
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $staffMock = $this->createMock(\Box\Mod\Staff\Service::class);

        $modMock = $this->getMockBuilder(\FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())->method('getCoreModules')->willReturn([]);

        $di = $this->getDi();
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
