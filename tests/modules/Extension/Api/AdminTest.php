<?php


namespace Box\Mod\Extension\Api;


class AdminTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Extension\Service
     */
    protected $service = null;

    /**
     * @var \Box\Mod\Extension\Api\Admin
     */
    protected $api = null;


    public function setup(): void
    {
        $this->service = new \Box\Mod\Extension\Service();
        $this->api = new \Box\Mod\Extension\Api\Admin();
    }


    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testget_list()
    {
        $data = array();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getExtensionsList')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $result = $this->api->get_list($data);
        $this->assertIsArray($result);
    }
/*
 * @todo enable when extensions are available
    public function testget_latest()
    {
        $data = array();

        $extensionMock = $this->getMockBuilder('\Box_Extension')->getMock();

        $extensionMock->expects($this->atLeastOnce())
            ->method('getLatest')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['extension'] = $extensionMock;

        $this->api->setDi($di);
        $result = $this->api->get_latest($data);
        $this->assertIsArray($result);
    }

    public function testget_latestException()
    {
        $data = array('type' => 'mod');

        $extensionMock = $this->getMockBuilder('\Box_Extension')->getMock();

        $extensionMock->expects($this->atLeastOnce())
            ->method('getLatest')
            ->willThrowException(new \Exception());

        $di = new \Box_Di();
        $di['extension'] = $extensionMock;

        $this->api->setDi($di);
        $result = $this->api->get_latest($data);
        $this->assertIsArray($result);
        $this->assertEquals(array(), $result);
    }
*/

    public function testget_navigation()
    {
        $data = array('url' =>'billing');

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdminNavigation')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Admin());

        $result = $this->api->get_navigation($data);

        $this->assertIsArray($result);
    }

    public function testlanguages()
    {
        $result = $this->api->languages();
        $this->assertIsArray($result);
    }

    public function testupdateExtensionNotFound()
    {
        $data = array(
            'id' => 'extensionId',
            'type' => 'extensioTYpe',
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->will($this->returnValue(null));
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Extension not found');
        $this->api->update($data);
    }

    public function testactivate()
    {
        $data = array(
            'id' => 'extensionId',
            'type' => 'extensionType',
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('activateExistingExtension')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->activate($data);
        $this->assertIsArray($result);
    }

    public function testdeactivate()
    {
        $data = array(
            'id' => 'extensionId',
            'type' => 'extensionType',
        );

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->will($this->onConsecutiveCalls($model));
        $serviceMock->expects($this->atLeastOnce())
            ->method('deactivate')
            ->will($this->returnValue(true));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $di = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->deactivate($data);
        $this->assertTrue($result);
    }

    public function testuninstall()
    {
        $data = array(
            'id' => 'extensionId',
            'type' => 'extensionType',
        );

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->will($this->returnValue($model));
        $serviceMock->expects($this->atLeastOnce())
            ->method('uninstall')
            ->will($this->returnValue(true));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $di = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->uninstall($data);
        $this->assertTrue($result);
    }

    public function testinstall()
    {
        $data = array(
            'id' => 'extensionId',
            'type' => 'extensionType',
        );

        $expected = array(
            'success'   =>  true,
            'id'        =>  $data['id'],
            'type'      =>  $data['type'],
        );

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('downloadAndExtract')
            ->will($this->returnValue(true));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $di = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->install($data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testinstallExceptionActivate()
    {
        $data = array(
            'id' => 'extensionId',
            'type' => 'extensionType',
        );

        $expected = array(
            'success'   =>  true,
            'id'        =>  $data['id'],
            'type'      =>  $data['type'],
        );

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('downloadAndExtract')
            ->will($this->returnValue(true));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $di = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->install($data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testconfig_get()
    {
        $data = array(
            'ext' => 'extensionName',
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->config_get($data);

        $this->assertIsArray($result);
    }

    public function testconfig_save()
    {
        $data = array(
            'ext' => 'extensionName',
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('setConfig')
            ->will($this->returnValue(true));

        $serviceMock->expects($this->never())
            ->method('getConfig');

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->config_save($data);

        $this->assertTrue($result);
    }


}
