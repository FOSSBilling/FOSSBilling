<?php


namespace Box\Mod\Servicelicense\Api;


class AdminTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Servicelicense\Api\Admin
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api= new \Box\Mod\Servicelicense\Api\Admin();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testplugin_get_pairs()
    {
        $licensePluginArray[]['filename'] = 'plugin1';
        $licensePluginArray[]['filename'] = 'plugin2';
        $licensePluginArray[]['filename'] = 'plugin3';

        $expected = array(
            'plugin1' => 'plugin1',
            'plugin2' => 'plugin2',
            'plugin3' => 'plugin3',
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicelicense\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getLicensePlugins')
            ->will($this->returnValue($licensePluginArray));

        $this->api->setService($serviceMock);

        $result = $this->api->plugin_get_pairs(array());
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testupdate()
    {
        $data = array(
            'order_id' => 1,
        );

        $apiMock = $this->getMockBuilder('\Box\Mod\Servicelicense\Api\Admin')
            ->setMethods(array('_getService'))
            ->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->will($this->returnValue(new \Model_ServiceLicense()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicelicense\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('update')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);
        $result = $apiMock->update($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testreset()
    {
        $data = array(
            'order_id' => 1,
        );

        $apiMock = $this->getMockBuilder('\Box\Mod\Servicelicense\Api\Admin')
            ->setMethods(array('_getService'))
            ->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->will($this->returnValue(new \Model_ServiceLicense()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicelicense\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('reset')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);
        $result = $apiMock->reset($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function test_getService()
    {
        $data['order_id'] = 1;

        $orderServiceMock= $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue(new \Model_ServiceLicense()));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_ClientOrder()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) {return $orderServiceMock;});

        $this->api->setDi($di);

        $result = $this->api->_getService($data);
        $this->assertInstanceOf('\Model_ServiceLicense', $result);
    }

    public function test_getServiceOrderNotActivated()
    {
        $data['order_id'] = 1;

        $orderServiceMock= $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_ClientOrder()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) {return $orderServiceMock;});

        $this->api->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        $this->api->_getService($data);
    }



}
 