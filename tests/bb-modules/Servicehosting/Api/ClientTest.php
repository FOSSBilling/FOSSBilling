<?php


namespace Box\Mod\Servicehosting\Api;


class ClientTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Servicehosting\Api\Client
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api= new \Box\Mod\Servicehosting\Api\Client();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testchange_username()
    {
        $getServiceReturnValue = array(new \Model_ClientOrder(), new \Model_ServiceHosting);
        $apiMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Api\Client')
            ->setMethods(array('_getService'))
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->will($this->returnValue($getServiceReturnValue));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountUsername')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);

        $result = $apiMock->change_username(array());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testchange_domain()
    {
        $getServiceReturnValue = array(new \Model_ClientOrder(), new \Model_ServiceHosting);
        $apiMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Api\Client')
            ->setMethods(array('_getService'))
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->will($this->returnValue($getServiceReturnValue));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountDomain')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);

        $result = $apiMock->change_domain(array());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testchange_password()
    {
        $getServiceReturnValue = array(new \Model_ClientOrder(), new \Model_ServiceHosting);
        $apiMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Api\Client')
            ->setMethods(array('_getService'))
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->will($this->returnValue($getServiceReturnValue));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountPassword')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);

        $result = $apiMock->change_password(array());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testhp_get_pairs()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getHpPairs')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);
        $result = $this->api->hp_get_pairs(array());
        $this->assertIsArray($result);
    }

    public function test_getService()
    {
        $data = array(
            'order_id' => 1,
        );

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($clientOrderModel));


        $model = new \Model_ServiceHosting();
        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientModel->id = 1;
        $this->api->setIdentity($clientModel);
        $result = $this->api->_getService($data);
        $this->assertIsArray($result);
        $this->assertInstanceOf('\Model_ClientOrder', $result[0]);
        $this->assertInstanceOf('\Model_ServiceHosting', $result[1]);
    }

    public function test_getServiceOrderNotActivated()
    {
        $data = array(
            'order_id' => 1,
        );

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($clientOrderModel));


        $model = null;
        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientModel->id = 1;
        $this->api->setIdentity($clientModel);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        $this->api->_getService($data);
    }

    public function test_getServiceOrderNotFound()
    {
        $data = array(
            'order_id' => 1,
        );

        $clientOrderModel = null;
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($clientOrderModel));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientModel->id = 1;
        $this->api->setIdentity($clientModel);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Order not found');
        $this->api->_getService($data);

    }

    public function test_getServiceMissingOrderId()
    {
        $data = array();

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Order id is required');
        $this->api->_getService($data);
    }
}
 