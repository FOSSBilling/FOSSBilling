<?php

namespace Box\Mod\Extension\Api;

class AdminTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Extension\Service
     */
    protected $service;

    /**
     * @var Admin
     */
    protected $api;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Extension\Service();
        $this->api = new Admin();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetList(): void
    {
        $data = [];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getExtensionsList')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->get_list($data);
        $this->assertIsArray($result);
    }

    public function testgetLatest(): void
    {
        $data = [];

        $extensionMock = $this->getMockBuilder('\\' . \FOSSBilling\ExtensionManager::class)->getMock();

        $extensionMock->expects($this->atLeastOnce())
            ->method('getExtensionList')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['extension_manager'] = $extensionMock;

        $this->api->setDi($di);
        $result = $this->api->get_latest($data);
        $this->assertIsArray($result);
    }

    public function testgetLatestException(): void
    {
        $data = ['type' => 'mod'];

        $extensionMock = $this->getMockBuilder('\\' . \FOSSBilling\ExtensionManager::class)->getMock();

        $extensionMock->expects($this->atLeastOnce())
            ->method('getExtensionList')
            ->willThrowException(new \Exception());

        $di = new \Pimple\Container();
        $di['extension_manager'] = $extensionMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $this->api->setDi($di);
        $result = $this->api->get_latest($data);
        $this->assertIsArray($result);
        $this->assertEquals([], $result);
    }

    public function testgetNavigation(): void
    {
        $data = ['url' => 'billing'];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdminNavigation')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Admin());

        $result = $this->api->get_navigation($data);

        $this->assertIsArray($result);
    }

    public function testlanguages(): void
    {
        $result = $this->api->languages([]);
        $this->assertIsArray($result);
    }

    public function testupdateExtensionNotFound(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensioTYpe',
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->willReturn(null);
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Extension not found');
        $this->api->update($data);
    }

    public function testactivate(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('activateExistingExtension')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->activate($data);
        $this->assertIsArray($result);
    }

    public function testdeactivate(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->willReturnOnConsecutiveCalls($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('deactivate')
            ->willReturn(true);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->deactivate($data);
        $this->assertTrue($result);
    }

    public function testuninstall(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('uninstall')
            ->willReturn(true);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->uninstall($data);
        $this->assertTrue($result);
    }

    public function testinstall(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $expected = [
            'success' => true,
            'id' => $data['id'],
            'type' => $data['type'],
        ];

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('downloadAndExtract')
            ->willReturn(true);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->install($data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testinstallExceptionActivate(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $expected = [
            'success' => true,
            'id' => $data['id'],
            'type' => $data['type'],
        ];

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('downloadAndExtract')
            ->willReturn(true);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->install($data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testconfigGet(): void
    {
        $data = [
            'ext' => 'extensionName',
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->config_get($data);

        $this->assertIsArray($result);
    }

    public function testconfigSave(): void
    {
        $data = [
            'ext' => 'extensionName',
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('setConfig')
            ->willReturn(true);

        $serviceMock->expects($this->never())
            ->method('getConfig');

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->config_save($data);

        $this->assertTrue($result);
    }
}
