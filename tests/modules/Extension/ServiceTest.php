<?php


namespace Box\Mod\Extension;

use \Symfony\Component\HttpClient\MockHttpClient;

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
     * @var \Box\Mod\Extension\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Extension\Service();
    }

    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testisCoreModule()
    {
        $coreModules = array('extension', 'cron', 'staff');
        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->will($this->returnValue($coreModules));

        $di = new \Pimple\Container();
        $di['mod'] = $di->protect(fn($name) => $modMock);

        $this->service->setDi($di);

        $result = $this->service->isCoreModule('extension');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testisExtensionActiveModNotFound()
    {
        $coreModules = array('extension', 'cron', 'staff');
        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->will($this->returnValue($coreModules));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(null));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn($name) => $modMock);

        $this->service->setDi($di);

        $result = $this->service->isExtensionActive('mod', 'ModDoesNotExists');
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testremoveNotExistingModules()
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
            ->will($this->returnValue(array($model)));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn($name) => $modMock);

        $this->service->setDi($di);

        $result = $this->service->removeNotExistingModules();
        $this->assertIsInt($result);
        $this->assertTrue($result > 0);
    }

    public static function searchQueryData()
    {
        return array(
            array(array(), 'SELECT * FROM extension', array()),
            array(array('type' => 'mod'), 'AND type = :type', array(':type' => 'mod')),
            array(array('search' => 'FindUp'), 'AND name LIKE :search', array(':search' => 'FindUp')),

        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('searchQueryData')]
    public function testgetSearchQuery($data, $expectedStr, $expectedParams)
    {
        $di = new \Pimple\Container();

        $this->service->setDi($di);
        [$sql, $params] = $this->service->getSearchQuery($data);

        $this->assertIsString($sql);
        $this->assertIsArray($params);

        $this->assertTrue(str_contains($sql, $expectedStr), $sql);
        $this->assertTrue(array_diff_key($params, $expectedParams) == array());
    }


    public function testgetExtensionsList()
    {
        $data = array(
            'has_settings' => true,
            'active' => true,
        );

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
            ->will($this->returnValue(array($model)));

        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($modelFind)));

        $coreModules = array('extension', 'cron', 'staff');
        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->will($this->returnValue($coreModules));
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')
            ->will($this->returnValue(array()));
        $modMock->expects($this->atLeastOnce())
            ->method('hasSettingsPage')
            ->will($this->returnValue(true));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn($name) => $modMock);

        $this->service->setDi($di);

        $result = $this->service->getExtensionsList($data);
        $this->assertIsArray($result);
    }

    public function testgetExtensionsListOnlyInstalled()
    {
        $data = array(
            'installed' => true,
        );

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
            ->will($this->returnValue(array($model)));

        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($modelFind)));

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')
            ->will($this->returnValue(array()));
        $modMock->expects($this->atLeastOnce())
            ->method('hasSettingsPage')
            ->will($this->returnValue(true));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn($name) => $modMock);

        $this->service->setDi($di);

        $result = $this->service->getExtensionsList($data);
        $this->assertIsArray($result);
    }

    public function testgetAdminNavigation()
    {
        $extensionServiceMock = $this->getMockBuilder(\Box\Mod\Extension\Service::class)->onlyMethods(['getConfig'])->getMock();
        $extensionServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array()));

        $staffServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $staffServiceMock->expects($this->atLeastOnce())
            ->method('hasPermission')
            ->will($this->returnValue(true));

        $pdoStatment = $this->getMockBuilder('\\' . \Box\Mod\Extension\PdoStatmentsMock::class)->getMock();
        $pdoStatment->expects($this->atLeastOnce())
            ->method('execute');
        $pdoStatment->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->will($this->returnValue(array()));

        $pdoMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatment));

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


    public function testfindExtension()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(new \Model_Extension()));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->findExtension('mod', 'id');
        $this->assertInstanceOf('\Model_Extension', $result);
    }

    public function testupdate()
    {
        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());
        $model->type = 'mod';
        $model->name = 'testExtension';
        $model->version = '2';

        $extensionMock = $this->getMockBuilder('\\' . \FOSSBilling\ExtensionManager::class)->getMock();

        $staffService = $this->getMockBuilder('Box\Mod\Staff\Service')->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException')->will($this->returnValue(true));

        $di = new \Pimple\Container();
        $di['extension_manager'] = $extensionMock;
        $di['mod_service'] = $di->protect(function () use ($staffService) {
            return $staffService;
        });

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(252);
        $this->expectExceptionMessage('Visit the extension directory for more information on updating this extension.');
        $this->service->update($model);
    }

    public function testactivate()
    {
        $ext = new \Model_Extension();
        $ext->loadBean(new \DummyBean());
        $ext->type = 'mod';
        $ext->name = 'testExtension';

        $expectedResult = array(
            'id'        => $ext->name,
            'type'      => $ext->type,
            'redirect'  => true,
            'has_settings'  => true,
        );

        $staffService = $this->getMockBuilder('Box\Mod\Staff\Service')->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException')->will($this->returnValue(true));

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getManifest')
            ->will($this->returnValue(array('version' => 1)));

        $modMock->expects($this->atLeastOnce())
            ->method('hasAdminController')
            ->will($this->returnValue(true));

        $modMock->expects($this->atLeastOnce())
            ->method('hasSettingsPage')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(1));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn($name) => $modMock);
        $di['mod_service'] = $di->protect(function () use ($staffService) {
            return $staffService;
        });

        $this->service->setDi($di);
        $result = $this->service->activate($ext);
        $this->assertIsArray($result);
        $this->assertEquals($expectedResult, $result);
    }

    public function testdeactivate()
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
            ->will($this->returnValue(array()));

        $modMock->expects($this->atLeastOnce())
            ->method('uninstall')
            ->will($this->returnValue(true));

        $staffService = $this->getMockBuilder('Box\Mod\Staff\Service')->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException')->will($this->returnValue(true));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn($name) => $modMock);

        $di['mod_service'] = $di->protect(function () use ($staffService) {
            return $staffService;
        });

        $this->service->setDi($di);

        $result = $this->service->deactivate($ext);
        $this->assertTrue($result);
    }

    public function testdeactivateCoreModuleException()
    {
        $ext = new \Model_Extension();
        $ext->loadBean(new \DummyBean());
        $ext->type = 'mod';
        $ext->name = 'extensionTest';

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->will($this->returnValue(array($ext->name)));

        $staffService = $this->getMockBuilder('Box\Mod\Staff\Service')->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException')->will($this->returnValue(true));

        $di = new \Pimple\Container();
        $di['mod'] = $di->protect(fn($name) => $modMock);

        $di['mod_service'] = $di->protect(function () use ($staffService) {
            return $staffService;
        });

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('FOSSBilling core modules can not be managed');
        $this->service->deactivate($ext);
    }

    public function testdeactivateUninstallException()
    {
        $ext = new \Model_Extension();
        $ext->loadBean(new \DummyBean());
        $ext->type = 'mod';
        $ext->name = 'extensionTest';

        $exceptionMessage = 'testException';

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->will($this->returnValue(array()));

        $modMock->expects($this->atLeastOnce())
            ->method('uninstall')
            ->willThrowException(new \FOSSBilling\Exception($exceptionMessage));

        $staffService = $this->getMockBuilder('Box\Mod\Staff\Service')->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException')->will($this->returnValue(true));

        $di = new \Pimple\Container();
        $di['mod'] = $di->protect(fn($name) => $modMock);

        $di['mod_service'] = $di->protect(function () use ($staffService) {
            return $staffService;
        });

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->service->deactivate($ext);
    }

    public function testdeactivateExtensionHook()
    {
        $ext = new \Model_Extension();
        $ext->loadBean(new \DummyBean());
        $ext->type = 'hook';
        $ext->name = 'extensionTest';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $staffService = $this->getMockBuilder('Box\Mod\Staff\Service')->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException')->will($this->returnValue(true));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($staffService) {
            return $staffService;
        });

        $this->service->setDi($di);
        $result = $this->service->deactivate($ext);
        $this->assertTrue($result);
    }

    public function testuninstall()
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
            ->will($this->returnValue(array()));

        $modMock->expects($this->atLeastOnce())
            ->method('uninstall')
            ->will($this->returnValue(true));

        $staffService = $this->getMockBuilder('Box\Mod\Staff\Service')->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException')->will($this->returnValue(true));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn($name) => $modMock);

        $di['mod_service'] = $di->protect(function () use ($staffService) {
            return $staffService;
        });

        $this->service->setDi($di);

        $result = $this->service->deactivate($ext);
        $this->assertTrue($result);
    }

    public function testdownloadAndExtractDownloadUrlMissing()
    {
        $extensionMock = $this->getMockBuilder(\FOSSBilling\ExtensionManager::class)->getMock();

        $extensionMock->expects($this->atLeastOnce())
            ->method('getLatestExtensionRelease')
            ->will($this->returnValue(array()));

        $staffService = $this->getMockBuilder('Box\Mod\Staff\Service')->getMock();
        $staffService->expects($this->atLeastOnce())->method('checkPermissionsAndThrowException')->will($this->returnValue(true));

        $di = new \Pimple\Container();
        $di['extension_manager'] = $extensionMock;
        $di['mod_service'] = $di->protect(function () use ($staffService) {
            return $staffService;
        });

        $this->service->setDi($di);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Coudn\'t find a valid download URL for the extension.');
        $this->service->downloadAndExtract('mod', 'extensionId');
    }

    public function testgetInstalledMods()
    {
        $pdoStatment = $this->getMockBuilder(\Box\Mod\Extension\PdoStatmentsMock::class)->getMock();
        $pdoStatment->expects($this->atLeastOnce())
            ->method('execute');
        $pdoStatment->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->will($this->returnValue(array()));


        $pdoMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatment));


        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;

        $this->service->setDi($di);
        $result = $this->service->getInstalledMods();
        $this->assertEquals(array(), $result);
    }

    public function testactivateExistingExtension()
    {
        $data = array(
            'id' => 'extensionId',
            'type' => 'extensionType',
        );

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)
            ->onlyMethods(array('findExtension', 'activate'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->will($this->onConsecutiveCalls(null, $model));
        $serviceMock->expects($this->atLeastOnce())
            ->method('activate')
            ->will($this->returnValue(array()));

        $dbMock = $this->getMockBuilder(\Box_Database::class)->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($model));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(1));

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

    public function testactivateException()
    {
        $data = array(
            'id' => 'extensionId',
            'type' => 'extensionType',
        );

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)
            ->onlyMethods(array('findExtension', 'activate'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->will($this->returnValue($model));
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

    public function testgetConfig()
    {
        $data = array(
            'ext' => 'extensionName',
        );

        $model = new \Model_ExtensionMeta();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder(\Box_Database::class)->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $cryptMock = $this->getMockBuilder(\Box_Crypt::class)->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('decrypt');

        $toolsMock = $this->getMockBuilder(\FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->will($this->returnValue(array()));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['crypt'] = $cryptMock;
        $di['tools'] = $toolsMock;
        $di['config'] = array('salt' => '');
        $di['cache'] = new \Symfony\Component\Cache\Adapter\ArrayAdapter();

        $this->service->setDi($di);

        $result = $this->service->getConfig($data['ext']);
        $this->assertIsArray($result);
    }

    public function testgetConfigExtensionMetaNotFound()
    {
        $data = array(
            'ext' => 'extensionName',
        );

        $model = new \Model_ExtensionMeta();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder(\Box_Database::class)->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($model));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(1));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['cache'] = new \Symfony\Component\Cache\Adapter\ArrayAdapter();

        $this->service->setDi($di);
        $result = $this->service->getConfig($data['ext']);

        $this->assertIsArray($result);
        $this->assertEquals(array(), $result);
    }

    public function testsetConfig()
    {
        $data = array(
            'ext' => 'extensionName',
        );

        $serviceMock = $this->getMockBuilder(\Box\Mod\Extension\Service::class)->onlyMethods(['getConfig', 'getCoreAndActiveModulesAndPermissions'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array()));

        $toolsMock = $this->getMockBuilder(\FOSSBilling\Tools::class)->getMock();

        $cryptMock = $this->getMockBuilder(\Box_Crypt::class)->getMock();
        $cryptMock->expects($this->atLeastOnce())
            ->method('encrypt')
            ->will($this->returnValue('encryptedConfig'));

        $model = new \Model_ExtensionMeta();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder(\Box_Database::class)->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec')
            ->will($this->returnValue(array()));

        $eventMock = $this->getMockBuilder(\Box_EventManager::class)->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $staffMock = $this->getMockBuilder('Box\Mod\Staff\Service')->getMock();

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())->method('getCoreModules')->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['tools'] = $toolsMock;
        $di['crypt'] = $cryptMock;
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['config'] = array('salt' => '');
        $di['mod'] = $di->protect(function () use ($modMock) {
            return $modMock;
        });
        $di['mod_service'] = $di->protect(function () use ($staffMock) {
            return $staffMock;
        });
        $di['cache'] = new \Symfony\Component\Cache\Adapter\ArrayAdapter();

        $serviceMock->setDi($di);
        $result = $serviceMock->setConfig($data);

        $this->assertTrue($result);
    }
}
