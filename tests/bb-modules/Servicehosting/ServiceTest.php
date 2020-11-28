<?php


namespace Box\Mod\Servicehosting;


class ServiceTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Servicehosting\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service= new \Box\Mod\Servicehosting\Service();
    }


    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function validateOrdertDataProvider()
    {
        return array(
            array('server_id', 'Hosting product is not configured completely. Configure server for hosting product.', 701),
            array('hosting_plan_id', 'Hosting product is not configured completely. Configure hosting plan for hosting product.', 702),
            array('sld', 'Domain name is not valid.', 703),
            array('tld', 'Domain extension is not valid.', 704),
        );
    }

    /**
     * @dataProvider validateOrdertDataProvider
     */
    public function testvalidateOrderData($field, $exceptionMessage, $excCode)
    {
        $data = array(
            'server_id' => 1,
            'hosting_plan_id' => 2,
            'sld' => 'great',
            'tld' => 'com'
        );

        unset ($data [ $field ]);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage($exceptionMessage, $excCode);
        $this->service->validateOrderData($data);
    }

    public function testaction_create()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());
        $confArr = array(
            'server_id' => 1,
            'hosting_plan_id' => 2,
            'sld' => 'great',
            'tld' => 'com'
        );
        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue($confArr));

        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \RedBeanPHP\OODBBean());
        $hostingPlansModel = new \Model_ServiceHostingHp();
        $hostingPlansModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->onConsecutiveCalls($hostingServerModel, $hostingPlansModel));

        $servhostingModel = new \Model_ServiceHosting();
        $servhostingModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($servhostingModel));

        $newserviceHostingId = 4;
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($newserviceHostingId));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->service->setDi($di);
        $this->service->action_create($orderModel);
    }
   
    public function testaction_activate()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $confArr = array(
            'server_id' => 1,
            'hosting_plan_id' => 2,
            'sld' => 'great',
            'tld' => 'com'
        );
        
        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue($confArr));

        $servhostingModel = new \Model_ServiceHosting();
        $servhostingModel->loadBean(new \RedBeanPHP\OODBBean());
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($servhostingModel));


        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('generatePassword')
            ->will($this->returnValue('generatedPass'));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('_getAM'))
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('createAccount');

        $AMresultArray = array($serverManagerMock, new \Server_Account());
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->will($this->returnValue($AMresultArray));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['tools'] = $toolsMock;
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});

        $serviceMock->setDi($di);
        $orderModel->config = $confArr;
        $result = $serviceMock->action_activate($orderModel);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result['username']);
        $this->assertNotEmpty($result['password']);
    }

    public function testaction_renew()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($model));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');


        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});

        $this->service->setDi($di);
        $result = $this->service->action_renew($orderModel);
        $this->assertTrue($result);
    }

    public function testaction_renewOrderWithoutActiveService()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());
        $orderModel->id = 1;

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
        $this->service->action_renew($orderModel);

    }

    public function testaction_suspend()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($model));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');


        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('_getAM'))
            ->getMock();
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('suspendAccount');
        $AMresultArray = array($serverManagerMock, new \Server_Account());
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->will($this->returnValue($AMresultArray));

        $serviceMock->setDi($di);
        $result = $serviceMock->action_suspend($orderModel);
        $this->assertTrue($result);
    }

    public function testaction_suspendOrderWithoutActiveService()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());
        $orderModel->id = 1;

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
        $this->service->action_suspend($orderModel);

    }

    public function testaction_unsuspend()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($model));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');


        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('_getAM'))
            ->getMock();
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('unsuspendAccount');
        $AMresultArray = array($serverManagerMock, new \Server_Account());
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->will($this->returnValue($AMresultArray));

        $serviceMock->setDi($di);
        $result = $serviceMock->action_unsuspend($orderModel);
        $this->assertTrue($result);
    }

    public function testaction_unsuspendOrderWithoutActiveService()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());
        $orderModel->id = 1;

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
        $this->service->action_unsuspend($orderModel);

    }

    public function testaction_cancel()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($model));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('_getAM'))
            ->getMock();
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('cancelAccount');
        $AMresultArray = array($serverManagerMock, new \Server_Account());
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->will($this->returnValue($AMresultArray));

        $serviceMock->setDi($di);
        $result = $serviceMock->action_cancel($orderModel);
        $this->assertTrue($result);
    }

    public function testaction_cancelOrderWithoutActiveService()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());
        $orderModel->id = 1;

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
        $this->service->action_cancel($orderModel);
    }

    public function testaction_uncancel()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());
        $confArr = array(
            'server_id' => 1,
            'hosting_plan_id' => 2,
            'sld' => 'great',
            'tld' => 'com'
        );
        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue($confArr));

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($model));

        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \RedBeanPHP\OODBBean());
        $hostingPlansModel = new \Model_ServiceHostingHp();
        $hostingPlansModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->onConsecutiveCalls($hostingServerModel, $hostingPlansModel));

        $servhostingModel = new \Model_ServiceHosting();
        $servhostingModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($servhostingModel));

        $newserviceHostingId = 4;
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($newserviceHostingId));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });


        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('_getAM'))
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('createAccount');
        $AMresultArray = array($serverManagerMock, new \Server_Account());
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->will($this->returnValue($AMresultArray));


        $serviceMock->setDi($di);
        $serviceMock->action_uncancel($orderModel);
    }

    public function testaction_delete()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());
        $orderModel->status = 'active';

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($model));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('action_cancel'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('action_cancel');

        $serviceMock->setDi($di);
        $serviceMock->action_delete($orderModel);
    }

    public function testchangeAccountPlan()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $modelHp = new \Model_ServiceHostingHp();
        $modelHp->loadBean(new \RedBeanPHP\OODBBean());;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('_getAM', 'getServerPackage'))
            ->getMock();
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountPackage');
        $AMresultArray = array($serverManagerMock, new \Server_Account());
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->will($this->returnValue($AMresultArray));
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerPackage')
            ->will($this->returnValue(new \Server_Package()));

        $serviceMock->setDi($di);
        $result = $serviceMock->changeAccountPlan($orderModel, $model, $modelHp);
        $this->assertTrue($result);
    }

    public function testchangeAccountUsername()
    {
        $data = array(
            'username' => 'u123456',
        );

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('_getAM'))
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountUsername');

        $AMresultArray = array($serverManagerMock, new \Server_Account());
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->will($this->returnValue($AMresultArray));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->changeAccountUsername($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testchangeAccountUsernameMissingUsername()
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $data = array();

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Account username is missing or is not valid');
        $this->service->changeAccountUsername($orderModel, $model, $data);
    }

    public function testchangeAccountIp()
    {
        $data = array(
            'ip' => '1.1.1.1'
        );

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('_getAM'))
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountIp');

        $AMresultArray = array($serverManagerMock, new \Server_Account());
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->will($this->returnValue($AMresultArray));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->changeAccountIp($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testchangeAccountIpMissingIp()
    {
        $data = array();
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Account ip is missing or is not valid');
        $this->service->changeAccountIp($orderModel, $model, $data);
    }

    public function testchangeAccountDomain()
    {
        $data = array(
            'tld' => 'com',
            'sld' => 'testingSld',
        );

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('_getAM'))
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountDomain');

        $AMresultArray = array($serverManagerMock, new \Server_Account());
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->will($this->returnValue($AMresultArray));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->changeAccountDomain($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testchangeAccountDomainMissingParams()
    {
        $data = array();
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Domain sld or tld is missing');
        $this->service->changeAccountDomain($orderModel, $model, $data);
    }

    public function testchangeAccountPassword()
    {
        $data = array(
            'password' => 'topsecret',
            'password_confirm' => 'topsecret',
        );

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('_getAM'))
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountPassword');

        $AMresultArray = array($serverManagerMock, new \Server_Account());
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->will($this->returnValue($AMresultArray));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->changeAccountPassword($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testchangeAccountPasswordMissingParams()
    {
        $data = array();
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Account password is missing or is not valid');
        $this->service->changeAccountPassword($orderModel, $model, $data);
    }

    public function testsync()
    {
        $data = array(
            'password' => 'topsecret',
            'password_confirm' => 'topsecret',
        );

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \RedBeanPHP\OODBBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('_getAM'))
            ->getMock();

        $accountObj = new \Server_Account();
        $accountObj->setUsername('testUser1');
        $accountObj->setIp('1.1.1.1');

        $accountObj2 = new \Server_Account();
        $accountObj2->setUsername('testUser2');
        $accountObj2->setIp('2.2.2.2');

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('synchronizeAccount')
            ->will($this->returnValue($accountObj2));

        $AMresultArray = array($serverManagerMock, $accountObj);
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->will($this->returnValue($AMresultArray));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->sync($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testtoApiArray()
    {
        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $hostingServer = new \Model_ServiceHostingServer();
        $hostingServer->loadBean(new \RedBeanPHP\OODBBean());
        $hostingServer->manager = 'Custom';
        $hostingHp = new\Model_ServiceHostingHp();
        $hostingHp->loadBean(new \RedBeanPHP\OODBBean());


        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->onConsecutiveCalls($hostingServer, $hostingHp));

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getServiceOrder');

        $serverManagerCustomMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});
        $di['server_manager'] = $di->protect(function ($manager, $config) use($serverManagerCustomMock) {
            return $serverManagerCustomMock;
        });

        $this->service->setDi($di);

        $result = $this->service->toApiArray($model, false, new \Model_Admin());
        $this->assertIsArray($result);
    }

    public function testupdate()
    {
        $data = array(
            'username' => 'testUser',
            'ip' => '1.1.1.1',
        );
        $model = new \Model_ServiceHosting();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $result = $this->service->update($model, $data);
        $this->assertTrue($result);
    }

    public function testgetServerManagers()
    {
        $result = $this->service->getServerManagers();
        $this->assertIsArray($result);
    }

    public function testgetServerManagerConfig()
    {
        $manager = 'Custom';

        $expected = array(
            'label' => "Custom Server Manager"
        );

        $result = $this->service->getServerManagerConfig($manager);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetServerPairs()
    {
        $expected = array(
            '1' => 'name',
            '2' => 'ding',
        );

        $queryResult = array(
            array(
                'id' => 1,
                'name' => 'name'
            ),array(
                'id' => 2,
                'name' => 'ding'
            ),
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue($queryResult));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getServerPairs();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetServerSearchQuery()
    {
        $result = $this->service->getServersSearchQuery(array());
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertEquals(array(), $result[1]);
    }

    public function testcreateServer()
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($hostingServerModel));

        $newId = 1;
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($newId));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $name = 'newSuperFastServer';
        $ip = '1.1.1.1';
        $manager = 'Custom';
        $data = array();
        $result = $this->service->createServer($name, $ip, $manager, $data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testdeleteServer()
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $result = $this->service->deleteServer($hostingServerModel);
        $this->assertTrue($result);
    }

    public function testupdateServer()
    {
        $data = array(
            'name' => 'newName',
            'ip' => '1.1.1.1',
            'hostname' => 'unknownStar',
            'active' => 1,
            'status_url' => 'na',
            'ns1' => 'ns1.testserver.eu',
            'ns2' => 'ns2.testserver.eu',
            'ns3' => 'ns3.testserver.eu',
            'ns4' => 'ns4.testserver.eu',
            'manager' => 'Custom',
            'username' => 'testingJohn',
            'password' => 'hardToGuess',
            'accesshash' => 'secret',
            'port' => '23',
            'secure' => 0,
        );

        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $result = $this->service->updateServer($hostingServerModel, $data);
        $this->assertTrue($result);
    }

    public function testgetServerManager()
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \RedBeanPHP\OODBBean());
        $hostingServerModel->manager = 'Custom';

        $serverManagerCustom = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();

        $di = new \Box_Di();
        $di['server_manager'] = $di->protect(function ($manager, $config) use($serverManagerCustom) {
            return $serverManagerCustom;
        });
        $this->service->setDi($di);

        $result = $this->service->getServerManager($hostingServerModel);
        $this->assertInstanceOf('\Server_Manager_Custom', $result);
    }

    public function testgetServerManagerManagerNotDefined()
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \RedBeanPHP\OODBBean());

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(654);
        $this->expectExceptionMessage('Invalid server manager. Server was not configured properly');
        $this->service->getServerManager($hostingServerModel);
    }

    public function testgetServerManagerServerManagerInvalid()
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \RedBeanPHP\OODBBean());
        $hostingServerModel->manager = 'Custom';

        $di = new \Box_Di();
        $di['server_manager'] = $di->protect(function ($manager, $config) use($di) {
            return null;
        });
        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf('Server manager %s is not valid', $hostingServerModel->manager));
        $this->service->getServerManager($hostingServerModel);
    }

    public function testtestConnection()
    {
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('testConnection')
            ->will($this->returnValue(true));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('getServerManager'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManager')
            ->will($this->returnValue($serverManagerMock));

        $hostingServerModel = new \Model_ServiceHostingServer();
        $result = $serviceMock->testConnection($hostingServerModel );
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgetHpPairs()
    {
        $expected = array(
            '1' => 'free',
            '2' => 'paid',
        );

        $queryResult = array(
            array(
                'id' => 1,
                'name' => 'free'
            ),array(
                'id' => 2,
                'name' => 'paid'
            ),
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue($queryResult));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getHpPairs();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetHpSearchQuery()
    {
        $result = $this->service->getServersSearchQuery(array());
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertEquals(array(), $result[1]);
    }

    public function testdeleteHp()
    {
        $model = new \Model_ServiceHostingHp();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $result = $this->service->deleteHp($model);
        $this->assertTrue($result);
    }

    public function testtoHostingHpApiArray()
    {
        $model = new \Model_ServiceHostingHp();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->toHostingHpApiArray($model);
        $this->assertIsArray($result);
    }

    public function testUpdateHp()
    {
        $data = array(
            'name' => 'firstPlan',
            'bandwidth' => '100000',
            'quota' => '1000',
            'max_addon' => '0',
            'max_ft' => '1',
            'max_sql' => '2',
            'max_pop' => '1',
            'max_sub' => '2',
            'max_park' => '1',
        );

        $model = new \Model_ServiceHostingHp();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);


        $result = $this->service->updateHp($model, $data);
        $this->assertTrue($result);
    }

    public function testcreateHp()
    {
        $model = new \Model_ServiceHostingHp();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $newId = 1;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')->will($this->returnValue($model));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')->will($this->returnValue($newId));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $result = $this->service->createHp('Free Plan', array());
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testgetServerPackage()
    {
        $model = new \Model_ServiceHostingHp();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->config = '{}';

        $di = new \Box_Di();
        $di['server_package'] = new \Server_Package();

        $this->service->setDi($di);
        $result = $this->service->getServerPackage($model);
        $this->assertInstanceOf('\Server_Package', $result);
    }

    public function testgetServerManagerWithLog()
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \RedBeanPHP\OODBBean());
        $hostingServerModel->manager = 'Custom';


        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('getServerManager'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManager')
            ->will($this->returnValue($serverManagerMock));


        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getLogger')
            ->will($this->returnValue(new \Box_Log()));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function() use ($orderServiceMock) {return $orderServiceMock;});

        $serviceMock->setDi($di);
        $result = $serviceMock->getServerManagerWithLog($hostingServerModel, $clientOrderModel);
        $this->assertInstanceOf('\Server_Manager_Custom', $result);
    }

    public function testgetMangerUrls()
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \RedBeanPHP\OODBBean());
        $hostingServerModel->manager = 'Custom';

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('getLoginUrl')
            ->will($this->returnValue('/login'));
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('getResellerLoginUrl')
            ->will($this->returnValue('/admin/login'));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('getServerManager'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManager')
            ->will($this->returnValue($serverManagerMock));

        $result = $serviceMock->getMangerUrls($hostingServerModel);
        $this->assertIsArray($result);
        $this->assertIsString($result[0]);
        $this->assertIsString($result[1]);
    }

    public function testgetMangerUrlsException()
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \RedBeanPHP\OODBBean());
        $hostingServerModel->manager = 'Custom';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicehosting\Service')
            ->setMethods(array('getServerManager'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManager')
            ->will($this->throwException(new \Exception('Controlled unit test exception')));

        $result = $serviceMock->getMangerUrls($hostingServerModel);
        $this->assertIsArray($result);
        $this->assertFalse($result[0]);
        $this->assertFalse($result[1]);
    }

    public function testgetFreeTlds_FreeTldsAreNotSet()
    {
        $config  = array();
        $di = new \Box_Di();
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->willReturn($config);
        $di['tools'] = $toolsMock;

        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $tldArray = array('tld' => '.com');
        $serviceDomainServiceMock = $this->getMockBuilder('\Box\Mod\Servicedomain\Service')->getMock();
        $serviceDomainServiceMock->expects($this->atLeastOnce())
            ->method('tldToApiArray')
            ->willReturn($tldArray);
        $di['mod_service'] = $di->protect(function() use ($serviceDomainServiceMock) {return $serviceDomainServiceMock;});

        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn(array($tldModel));
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $model = new \Model_Product();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $result = $this->service->getFreeTlds($model);
        $this->assertIsArray($result);

    }

    public function testgetFreeTlds()
    {
        $config  = array(
            'free_tlds' => array('.com'),
        );
        $di = new \Box_Di();
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->willReturn($config);
        $di['tools'] = $toolsMock;

        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->service->setDi($di);
        $model = new \Model_Product();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $result = $this->service->getFreeTlds($model);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }



}