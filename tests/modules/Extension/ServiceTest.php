<?php


namespace Box\Mod\Extension;

class PdoMock extends \PDO
{
    public function __construct (){}
}
class PdoStatmentsMock extends \PDOStatement
{
    public function __construct (){}
}

class ServiceTest extends \BBTestCase {

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
        $di = new \Box_Di();
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

        $di = new \Box_Di();
        $di['mod'] = $di->protect(function ($name) use($modMock) { return $modMock;});
        $this->service->setDi($di);

        $result = $this->service->isCoreModule('extension');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testisExtensionActiveModAndCoreModule()
    {
        $coreModules = array('extension', 'cron', 'staff');
        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getCoreModules')
            ->will($this->returnValue($coreModules));

        $di = new \Box_Di();
        $di['mod'] = $di->protect(function ($name) use($modMock) { return $modMock;});
        $this->service->setDi($di);

        $result = $this->service->isExtensionActive('mod', 'extension');
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

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(function ($name) use($modMock) { return $modMock;});
        $this->service->setDi($di);

        $result = $this->service->isExtensionActive('mod', 'ModDoesNotExists');
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testonBeforeAdminCronRun()
    {
        //@TODO later create test
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

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(function ($name) use($modMock) { return $modMock;});
        $this->service->setDi($di);

        $result = $this->service->removeNotExistingModules();
        $this->assertIsInt($result);
        $this->assertTrue($result > 0);
    }

    public function searchQueryData()
    {
        return array(
            array(array(), 'SELECT * FROM extension', array()),
            array(array('type' => 'mod'), 'AND type = :type', array(':type' => 'mod')),
            array(array('search' => 'FindUp'), 'AND name LIKE :search', array(':search' => 'FindUp')),

        );
    }

    /**
     * @dataProvider searchQueryData
     */
    public function testgetSearchQuery($data, $expectedStr, $expectedParams)
    {
        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);
        list($sql, $params) = $this->service->getSearchQuery($data);

        $this->assertIsString($sql);
        $this->assertIsArray($params);

        $this->assertTrue(strpos($sql, $expectedStr) !== false, $sql);
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

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(function ($name) use($modMock) { return $modMock;});
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
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

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(function ($name) use($modMock) { return $modMock;});
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $result = $this->service->getExtensionsList($data);
        $this->assertIsArray($result);

    }

    public function testgetAdminNavigation()
    {
        $staffServiceMock = $this->getMockBuilder('\Box\Mod\Staff\Service')->getMock();
        $staffServiceMock->expects($this->atLeastOnce())
            ->method('hasPermission')
            ->will($this->returnValue(true));


        $pdoStatment = $this->getMockBuilder('\Box\Mod\Extension\PdoStatmentsMock')->getMock();
        $pdoStatment->expects($this->atLeastOnce())
            ->method('execute');
        $pdoStatment->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->will($this->returnValue(array()));


        $pdoMock = $this->getMockBuilder('\Box\Mod\Extension\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatment));

        $link = 'extension';

        $urlMock = $this->getMockBuilder('Box_Url')->getMock();
        $urlMock->expects($this->atLeastOnce())
            ->method('adminLink')
            ->willReturn('http://fossbilling.org/index.php?_url=/' . $link);
        $di['url'] = $urlMock;

        $di = new \Box_Di();
        $di['mod'] = $di->protect(function ($name) use($di) {
            $mod = new \Box_Mod($name);
            $mod->setDi($di);
            return $mod;
        });
        $di['tools'] = new \Box_Tools();
        $di['mod_service'] = $di->protect(function () use ($staffServiceMock) {return $staffServiceMock; });
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

        $di = new \Box_Di();
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
        $newversion = '3';

        $extensionMock = $this->getMockBuilder('\Box_Extension')->getMock();
        $extensionMock->expects($this->atLeastOnce())
            ->method('getLatestExtensionVersion')
            ->will($this->returnValue($newversion));

        $extensionMock->expects($this->atLeastOnce())
            ->method('getExtension')
            ->will($this->returnValue('string'));

        $di = new \Box_Di();
        $di['extension'] = $extensionMock;

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(252);
        $this->expectExceptionMessage('Visit the extension store for more information on updating this extension.');
        $this->service->update($model);
    }

    public function testupdateExtensionInformationException()
    {
        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());
        $model->type = 'mod';
        $model->name = 'testExtension';
        $model->version = '2';
        $newversion = '3';

        $extensionMock = $this->getMockBuilder('\Box_Extension')->getMock();
        $extensionMock->expects($this->atLeastOnce())
            ->method('getLatestExtensionVersion')
            ->will($this->returnValue($newversion));

        $extensionMock->expects($this->atLeastOnce())
            ->method('getExtension')
            ->will($this->returnValue(''));

        $di = new \Box_Di();
        $di['extension'] = $extensionMock;

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(744);
        $this->expectExceptionMessage(sprintf('Could not retrieve %s information', $model->name));
        $this->service->update($model);
    }

    public function testupdateNoNeedToUpdateException()
    {
        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());
        $model->type = 'mod';
        $model->name = 'testExtension';
        $model->version = '1';
        $newversion = $model->version;

        $extensionMock = $this->getMockBuilder('\Box_Extension')->getMock();
        $extensionMock->expects($this->atLeastOnce())
            ->method('getLatestExtensionVersion')
            ->will($this->returnValue($newversion));

        $di = new \Box_Di();
        $di['extension'] = $extensionMock;

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf('Latest %s version installed. No need to update', $model->name), 785);
        $this->service->update($model);
    }

    public function testupdateLastestVersionException()
    {
        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());
        $model->type = 'mod';
        $model->name = 'testExtension';

        $extensionMock = $this->getMockBuilder('\Box_Extension')->getMock();
        $extensionMock->expects($this->atLeastOnce())
            ->method('getLatestExtensionVersion')
            ->will($this->returnValue(''));

        $di = new \Box_Di();
        $di['extension'] = $extensionMock;

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(745);
        $this->expectExceptionMessage(sprintf('Could not retrieve version information for extension %s', $model->name));
        $this->service->update($model);

    }

    public function testupdateInvalidType()
    {
        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $result = $this->service->update($model);
        $this->assertEquals(array(), $result);
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

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(function ($name) use($modMock) { return $modMock;});

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

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(function ($name) use($modMock) { return $modMock;});

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

        $di = new \Box_Di();
        $di['mod'] = $di->protect(function ($name) use($modMock) { return $modMock;});

        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
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
            ->willThrowException(new \Box_Exception($exceptionMessage));

        $di = new \Box_Di();
        $di['mod'] = $di->protect(function ($name) use($modMock) { return $modMock;});

        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
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

        $di = new \Box_Di();
        $di['db'] = $dbMock;

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

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(function ($name) use($modMock) { return $modMock;});

        $this->service->setDi($di);

        $result = $this->service->deactivate($ext);
        $this->assertTrue($result);
       }

    public function testdownloadAndExtract()
    {
        $extensionMock = $this->getMockBuilder(\Box_Extension::class)->getMock();

        $extensionMock->expects($this->atLeastOnce())
            ->method('getExtension')
            ->will($this->returnValue(array('download_url' => 'www.boxbillig.com')));

        $guzzleMock = $this->getMockBuilder(\GuzzleHttp\Client::class)->getMock();
        $guzzleMock->expects($this->atLeastOnce())
            ->method('request');

        $toolsMock = $this->getMockBuilder(\Box_tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('fileExists')
            ->will($this->returnValue(false));
        $toolsMock->expects($this->atLeastOnce())
            ->method('rename')
            ->will($this->returnValue(true));
        $toolsMock->expects($this->atLeastOnce())
            ->method('emptyFolder');

        $di = new \Box_Di();
        $di['extension'] = $extensionMock;
        $di['guzzle_client'] = $guzzleMock;
        $di['tools'] = $toolsMock;

        $this->service->setDi($di);
        $result = $this->service->downloadAndExtract('mod', 'extensionId', true);
        $this->assertTrue($result);
    }

    public function testdownloadAndExtractTypeExceptionNotDefinedTypeException()
    {
        $extensionMock = $this->getMockBuilder(\Box_Extension::class)->getMock();

        $extensionMock->expects($this->atLeastOnce())
            ->method('getExtension')
            ->will($this->returnValue(array('download_url' => 'www.boxbillig.com')));

        $guzzleMock = $this->getMockBuilder(\GuzzleHttp\Client::class)->getMock();
        $guzzleMock->expects($this->atLeastOnce())
            ->method('request');
        
        $di = new \Box_Di();
        $di['extension'] = $extensionMock;
        $di['guzzle_client'] = $guzzleMock;
        
        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Extension does not support auto-install feature. Extension must be installed manually');
        $this->service->downloadAndExtract('notDefinedType', 'extensionId', true);
    }

    public function testdownloadAndExtractTranslationException()
    {
        $extensionMock = $this->getMockBuilder(\Box_Extension::class)->getMock();

        $extensionMock->expects($this->atLeastOnce())
            ->method('getExtension')
            ->will($this->returnValue(array('download_url' => 'www.boxbillig.com')));

        $guzzleMock = $this->getMockBuilder(\GuzzleHttp\Client::class)->getMock();
        $guzzleMock->expects($this->atLeastOnce())
            ->method('request');

        $toolsMock = $this->getMockBuilder(\Box_tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('fileExists')
            ->will($this->returnValue(false));
        $toolsMock->expects($this->atLeastOnce())
            ->method('mkdir');
        $toolsMock->expects($this->atLeastOnce())
            ->method('rename')
            ->will($this->returnValue(false));
        $toolsMock->expects($this->atLeastOnce())
            ->method('emptyFolder');

        $di = new \Box_Di();
        $di['extension'] = $extensionMock;
        $di['guzzle_client'] = $guzzleMock;
        $di['tools'] = $toolsMock;

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(440);
        $this->expectExceptionMessage('Locale files can not be moved. Make sure your server allows you to write to the locale folder');
        $this->service->downloadAndExtract('translation', 'extensionId', true);
    }

    public function testdownloadAndExtractThemeTypeException()
    {
        $extensionMock = $this->getMockBuilder(\Box_Extension::class)->getMock();

        $extensionMock->expects($this->atLeastOnce())
            ->method('getExtension')
            ->will($this->returnValue(array('download_url' => 'www.boxbillig.com')));

        $guzzleMock = $this->getMockBuilder(\GuzzleHttp\Client::class)->getMock();
        $guzzleMock->expects($this->atLeastOnce())
            ->method('request');

        $toolsMock = $this->getMockBuilder(\Box_tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('fileExists')
            ->will($this->returnValue(false));
        $toolsMock->expects($this->atLeastOnce())
            ->method('rename')
            ->will($this->returnValue(false));

        $di = new \Box_Di();
        $di['extension'] = $extensionMock;
        $di['guzzle_client'] = $guzzleMock;
        $di['tools'] = $toolsMock;

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(439);
        $this->expectExceptionMessage('Theme can not be moved. Make sure your server allows you to write to the themes folder.');
        $this->service->downloadAndExtract('theme', 'extensionId', true);
    }

    public function testdownloadAndExtractRenameException()
    {
        $extensionMock = $this->getMockBuilder(\Box_Extension::class)->getMock();

        $extensionMock->expects($this->atLeastOnce())
            ->method('getExtension')
            ->will($this->returnValue(array('download_url' => 'www.boxbillig.com')));

        $guzzleMock = $this->getMockBuilder(\GuzzleHttp\Client::class)->getMock();
        $guzzleMock->expects($this->atLeastOnce())
            ->method('request');

        $toolsMock = $this->getMockBuilder(\Box_tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('fileExists')
            ->will($this->returnValue(false));
        $toolsMock->expects($this->atLeastOnce())
            ->method('rename')
            ->will($this->returnValue(false));

        $di = new \Box_Di();
        $di['extension'] = $extensionMock;
        $di['guzzle_client'] = $guzzleMock;
        $di['tools'] = $toolsMock;

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(437);
        $this->expectExceptionMessage('Extension can not be moved. Make sure your server allows you to write to the modules folder.');
        $this->service->downloadAndExtract('mod', 'extensionId', true);
    }

    public function testdownloadAndExtractFileExistsException()
    {
        $extensionMock = $this->getMockBuilder(\Box_Extension::class)->getMock();

        $extensionMock->expects($this->atLeastOnce())
            ->method('getExtension')
            ->will($this->returnValue(array('download_url' => 'www.boxbillig.com')));

        $guzzleMock = $this->getMockBuilder(\GuzzleHttp\Client::class)->getMock();
        $guzzleMock->expects($this->atLeastOnce())
            ->method('request');

        $toolsMock = $this->getMockBuilder(\Box_tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('fileExists')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['extension'] = $extensionMock;
        $di['guzzle_client'] = $guzzleMock;
        $di['tools'] = $toolsMock;

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(436);
        $this->expectExceptionMessage('Module seems to be already installed.');
        $this->service->downloadAndExtract('mod', 'extensionId', true);
    }

    public function testdownloadAndExtractDownloadUrlMisssing()
    {
        $extensionMock = $this->getMockBuilder(\Box_Extension::class)->getMock();

        $extensionMock->expects($this->atLeastOnce())
            ->method('getExtension')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['extension'] = $extensionMock;

        $this->service->setDi($di);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid download URL for the extension');
        $this->service->downloadAndExtract('mod', 'extensionId', true);
    }

    public function testgetInstalledMods()
    {
        $pdoStatment = $this->getMockBuilder(\Box\Mod\Extension\PdoStatmentsMock::class)->getMock();
        $pdoStatment->expects($this->atLeastOnce())
            ->method('execute');
        $pdoStatment->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->will($this->returnValue(array()));


        $pdoMock = $this->getMockBuilder('\Box\Mod\Extension\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatment));


        $di = new \Box_Di();
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

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')
            ->setMethods(array('findExtension', 'activate'))
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
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $di = new \Box_Di();
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

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')
            ->setMethods(array('findExtension', 'activate'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->will($this->returnValue($model));
        $serviceMock->expects($this->atLeastOnce())
            ->method('activate')
            ->will($this->throwException(new \Exception()));

        $eventMock = $this->getMockBuilder(\Box_EventManager::class)->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $di = new \Box_Di();
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

        $toolsMock = $this->getMockBuilder(\Box_Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['crypt'] = $cryptMock;
        $di['tools'] = $toolsMock;
        $di['config'] = array('salt' => '');

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

        $di = new \Box_Di();
        $di['db'] = $dbMock;

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

        $serviceMock = $this->getMockBuilder(\Box\Mod\Extension\Service::class)->setMethods(['getConfig'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array()));

        $toolsMock = $this->getMockBuilder(\Box_Tools::class)->getMock();

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
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['tools'] = $toolsMock;
        $di['crypt'] = $cryptMock;
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['config'] = array('salt' => '');

        $serviceMock->setDi($di);
        $result = $serviceMock->setConfig($data);

        $this->assertTrue($result);
    }

}
