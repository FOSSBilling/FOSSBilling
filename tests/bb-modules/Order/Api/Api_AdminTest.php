<?php

class Api_AdminTest extends \BBTestCase
{

    /**
     * @var \Box\Mod\Order\Api\Admin
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api = new \Box\Mod\Order\Api\Admin();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGet()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $apiMock->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $apiMock->get($data);

        $this->assertIsArray($result);
    }

    public function testGet_list()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getSearchQuery'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSearchQuery')
            ->will($this->returnValue(array('query', array())));

        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getAdvancedResultSet')
            ->will($this->returnValue(array('list' => array())));

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array('show_addons' => 0)));

        $di          = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['mod'] = $di->protect(function() use ($modMock) {return $modMock;});
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->get_list(array());

        $this->assertIsArray($result);
    }

    public function testCreate()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('createOrder'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('createOrder')
            ->will($this->returnValue(rand(1, 100)));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->exactly(2))
            ->method('getExistingModelById')
            ->will($this->onConsecutiveCalls(new \Model_Client(), new \Model_Product()));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db']        = $dbMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $data   = array(
            'client_id'  => rand(1, 100),
            'product_id' => rand(1, 100)
        );
        $result = $this->api->create($data);

        $this->assertIsInt($result);
    }

    public function testUpdate()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('updateOrder'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateOrder')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);

        $data   = array(
            'client_id'  => rand(1, 100),
            'product_id' => rand(1, 100)
        );
        $result = $apiMock->update($data);

        $this->assertTrue($result);
    }

    public function testActivate()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('activateOrder'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('activateOrder')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);

        $data   = array(
            'client_id'  => rand(1, 100),
            'product_id' => rand(1, 100)
        );
        $result = $apiMock->activate($data);

        $this->assertTrue($result);
    }

    public function testRenew()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('renewOrder'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('renewOrder')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);

        $data   = array();
        $result = $apiMock->renew($data);

        $this->assertTrue($result);
    }

    public function testRenewPendingSetup()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->status = Model_ClientOrder::STATUS_PENDING_SETUP;

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder', 'activate'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));
        $apiMock->expects($this->atLeastOnce())
            ->method('activate')
            ->will($this->returnValue(true));

        $data   = array();
        $result = $apiMock->renew($data);

        $this->assertTrue($result);
    }

    public function testSuspend()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('suspendFromOrder'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('suspendFromOrder')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $apiMock->setDi($di);

        $apiMock->setService($serviceMock);

        $data   = array();
        $result = $apiMock->suspend($data);

        $this->assertTrue($result);
    }

    public function testUnsuspend()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->status = Model_ClientOrder::STATUS_SUSPENDED;

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('unsuspendFromOrder'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('unsuspendFromOrder')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);

        $data   = array();
        $result = $apiMock->unsuspend($data);

        $this->assertTrue($result);
    }

    public function testUnsuspendNotSuspendedException()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->status = Model_ClientOrder::STATUS_ACTIVE;

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('unsuspendFromOrder'))->getMock();
        $serviceMock->expects($this->never())->method('unsuspendFromOrder')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);

        $data   = array();
        $this->expectException(\Box_Exception::class);
        $result = $apiMock->unsuspend($data);

        $this->assertTrue($result);
    }

    public function testCancel()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('cancelFromOrder'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cancelFromOrder')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $apiMock->setDi($di);
        $apiMock->setService($serviceMock);

        $data   = array();
        $result = $apiMock->cancel($data);

        $this->assertTrue($result);
    }

    public function testUncancel()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->status = Model_ClientOrder::STATUS_CANCELED;

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('uncancelFromOrder'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('uncancelFromOrder')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);

        $data   = array();
        $result = $apiMock->uncancel($data);

        $this->assertTrue($result);
    }

    public function testUncancelNotCanceledException()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());
        $order->status = Model_ClientOrder::STATUS_ACTIVE;

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('uncancelFromOrder'))->getMock();
        $serviceMock->expects($this->never())->method('uncancelFromOrder')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);

        $data   = array();
        $this->expectException(\Box_Exception::class);
        $result = $apiMock->uncancel($data);

        $this->assertTrue($result);
    }

    public function testDelete()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('deleteFromOrder'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('deleteFromOrder')
            ->will($this->returnValue(true));

        $apiMock->setService($serviceMock);

        $data   = array();
        $result = $apiMock->delete($data);

        $this->assertTrue($result);
    }

    public function testDeleteWithAddons()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('deleteFromOrder', 'getOrderAddonsList'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('deleteFromOrder')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())->method('getOrderAddonsList')
            ->will($this->returnValue(array(new Model_ClientOrder())));

        $apiMock->setService($serviceMock);

        $data   = array(
            'delete_addons' => true
        );
        $result = $apiMock->delete($data);

        $this->assertTrue($result);
    }

    public function testBatchSuspendExpired()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('batchSuspendExpired'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('batchSuspendExpired')
            ->will($this->returnValue(true));

        $this->api->setService($serviceMock);

        $data   = array();
        $result = $this->api->batch_suspend_expired($data);

        $this->assertTrue($result);
    }

    public function testBatchCancelSuspended()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('batchCancelSuspended'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('batchCancelSuspended')
            ->will($this->returnValue(true));

        $this->api->setService($serviceMock);

        $data   = array();
        $result = $this->api->batch_cancel_suspended($data);

        $this->assertTrue($result);
    }

    public function testUpdate_config()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('updateOrderConfig'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateOrderConfig')
            ->will($this->returnValue(true));


        $apiMock->setService($serviceMock);

        $data   = array(
            'config' => array()
        );
        $result = $apiMock->update_config($data);

        $this->assertTrue($result);
    }

    public function testUpdate_configNotSetConfigException()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('updateOrderConfig'))->getMock();
        $serviceMock->expects($this->never())->method('updateOrderConfig')
            ->will($this->returnValue(true));


        $apiMock->setService($serviceMock);

        $data   = array();
        $this->expectException(\Box_Exception::class);
        $result = $apiMock->update_config($data);

        $this->assertTrue($result);
    }

    public function testService()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getOrderServiceData'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->will($this->returnValue(array()));

        $admin = new Model_Admin();
        $admin->loadBean(new RedBeanPHP\OODBBean());

        $apiMock->setService($serviceMock);
        $apiMock->setIdentity($admin);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $apiMock->service($data);

        $this->assertIsArray($result);
    }



    public function testStatus_history_get_list()
    {
        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getOrderStatusSearchQuery'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderStatusSearchQuery')
            ->will($this->returnValue(array('query', array())));

        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();

        $di['pager'] = $paginatorMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $apiMock->setDi($di);

        $apiMock->setService($serviceMock);

        $result = $apiMock->status_history_get_list(array());

        $this->assertIsArray($result);
    }

    public function testStatus_history_add()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('orderStatusAdd'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('orderStatusAdd')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));


        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $apiMock->setDi($di);
        $apiMock->setService($serviceMock);

        $data   = array(
            'status' => Model_ClientOrder::STATUS_ACTIVE
        );
        $result = $apiMock->status_history_add($data);

        $this->assertTrue($result);
    }

    public function testStatus_history_delete()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('orderStatusRm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('orderStatusRm')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $this->api->status_history_delete($data);

        $this->assertTrue($result);
    }

    public function testGet_statuses()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('counter'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('counter')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $result = $this->api->get_statuses();
        $this->assertIsArray($result);
    }

    public function testGet_invoice_options()
    {
        $result = $this->api->get_invoice_options(array());
        $this->assertIsArray($result);
    }

    public function testGet_status_pairs()
    {
        $result = $this->api->get_status_pairs(array());
        $this->assertIsArray($result);
    }

    public function testAddons()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getOrderAddonsList', 'toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderAddonsList')
            ->will($this->returnValue(array(new Model_ClientOrder())));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $order = new Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $apiMock = $this->getMockBuilder('\\Box\Mod\Order\Api\Admin')->setMethods(array('_getOrder'))->disableOriginalConstructor()->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getOrder')
            ->will($this->returnValue($order));

        $apiMock->setService($serviceMock);

        $data   = array(
            'status' => Model_ClientOrder::STATUS_ACTIVE
        );
        $result = $apiMock->addons($data);

        $this->assertIsArray($result);
        $this->assertIsArray($result[0]);
    }

    public function testGetOrder()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($order));

        $di              = new Box_Di();
        $di['validator'] = $validatorMock;
        $di['db']        = $dbMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $this->api->get($data);
    }

    public function testBatch_delete()
    {
        $activityMock = $this->getMockBuilder('\Box\Mod\Order\Api\Admin')->setMethods(array('delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->
        method('delete')->
        will($this->returnValue(true));
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(array('ids' => array(1, 2, 3)));
        $this->assertEquals(true, $result);
    }


}
 