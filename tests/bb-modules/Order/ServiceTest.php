<?php

namespace Box\Mod\Order;

class PdoMock extends \PDO
{
    public function __construct() { }
}

class PdoStatmentsMock extends \PDOStatement
{
    public function __construct() { }
}

class ServiceTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Order\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Order\Service();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testCounter()
    {
        $counter = array(
            \Model_ClientOrder::STATUS_ACTIVE => rand(1, 100)
        );
        $dbMock  = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue($counter));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $result = $this->service->counter();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(array_sum($counter), $result['total']);
        $this->assertArrayHasKey(\Model_ClientOrder::STATUS_PENDING_SETUP, $result);
        $this->assertArrayHasKey(\Model_ClientOrder::STATUS_FAILED_SETUP, $result);
        $this->assertArrayHasKey(\Model_ClientOrder::STATUS_ACTIVE, $result);
        $this->assertArrayHasKey(\Model_ClientOrder::STATUS_SUSPENDED, $result);
        $this->assertArrayHasKey(\Model_ClientOrder::STATUS_CANCELED, $result);
    }

    public function testOnAfterAdminOrderActivate()
    {
        $params = array(
            'id' => rand(1, 100)
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->will($this->returnValue($params));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($order));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $orderArr = array(
            'id'           => rand(1, 100),
            'client'       => array(
                'id' => rand(1, 100)
            ),
            'service_type' => 'domain'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderServiceData', 'toApiArray'))->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($orderArr));

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $serviceMock->setDi($di);

        $serviceMock->onAfterAdminOrderActivate($eventMock);
    }

    public function testOnAfterAdminOrderActivate_LogException()
    {
        $params = array(
            'id' => rand(1, 100)
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->will($this->returnValue($params));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($order));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = array(
            'id'           => rand(1, 100),
            'client'       => array(
                'id' => rand(1, 100)
            ),
            'service_type' => 'domain'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderServiceData', 'toApiArray'))->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($orderArr));

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $serviceMock->setDi($di);

        $serviceMock->onAfterAdminOrderActivate($eventMock);
    }

    public function testOnAfterAdminOrderRenew()
    {
        $params = array(
            'id' => rand(1, 100)
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->will($this->returnValue($params));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedbeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($order));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $orderArr = array(
            'id'           => rand(1, 100),
            'client'       => array(
                'id' => rand(1, 100)
            ),
            'service_type' => 'domain'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderServiceData', 'toApiArray'))->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($orderArr));

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedbeanPHP\OODBBean());

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });
        $serviceMock->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $serviceMock->onAfterAdminOrderRenew($eventMock);
    }

    public function testOnAfterAdminOrderRenew_LogException()
    {
        $params = array(
            'id' => rand(1, 100)
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->will($this->returnValue($params));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($order));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = array(
            'id'           => rand(1, 100),
            'client'       => array(
                'id' => rand(1, 100)
            ),
            'service_type' => 'domain'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderServiceData', 'toApiArray'))->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($orderArr));

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });
        $serviceMock->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $serviceMock->onAfterAdminOrderRenew($eventMock);
    }

    public function testOnAfterAdminOrderSuspend()
    {
        $params = array(
            'id' => rand(1, 100)
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->will($this->returnValue($params));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedbeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($order));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $orderArr = array(
            'id'           => rand(1, 100),
            'client'       => array(
                'id' => rand(1, 100)
            ),
            'service_type' => 'domain'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderServiceData', 'toApiArray'))->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($orderArr));

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedbeanPHP\OODBBean());

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });
        $serviceMock->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $serviceMock->onAfterAdminOrderSuspend($eventMock);
    }

    public function testOnAfterAdminOrderSuspend_LogException()
    {
        $params = array(
            'id' => rand(1, 100)
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->will($this->returnValue($params));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($order));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = array(
            'id'           => rand(1, 100),
            'client'       => array(
                'id' => rand(1, 100)
            ),
            'service_type' => 'domain'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderServiceData', 'toApiArray'))->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($orderArr));

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $serviceMock->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $serviceMock->onAfterAdminOrderSuspend($eventMock);
    }

    public function testOnAfterAdminOrderUnsuspend()
    {
        $params = array(
            'id' => rand(1, 100)
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->will($this->returnValue($params));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedbeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($order));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $orderArr = array(
            'id'           => rand(1, 100),
            'client'       => array(
                'id' => rand(1, 100)
            ),
            'service_type' => 'domain'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderServiceData', 'toApiArray'))->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($orderArr));

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedbeanPHP\OODBBean());

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });
        $serviceMock->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $serviceMock->onAfterAdminOrderUnsuspend($eventMock);
    }

    public function testOnAfterAdminOrderUnsuspend_LogException()
    {
        $params = array(
            'id' => rand(1, 100)
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->will($this->returnValue($params));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($order));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = array(
            'id'           => rand(1, 100),
            'client'       => array(
                'id' => rand(1, 100)
            ),
            'service_type' => 'domain'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderServiceData', 'toApiArray'))->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($orderArr));

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });
        $serviceMock->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $serviceMock->onAfterAdminOrderUnsuspend($eventMock);
    }

    public function testOnAfterAdminOrderCancel()
    {
        $params = array(
            'id' => rand(1, 100)
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->will($this->returnValue($params));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedbeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($order));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $orderArr = array(
            'id'           => rand(1, 100),
            'client'       => array(
                'id' => rand(1, 100)
            ),
            'service_type' => 'domain'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('toApiArray'))->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($orderArr));

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedbeanPHP\OODBBean());

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });
        $serviceMock->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $serviceMock->onAfterAdminOrderCancel($eventMock);
    }

    public function testOnAfterAdminOrderCancel_LogException()
    {
        $params = array(
            'id' => rand(1, 100)
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->will($this->returnValue($params));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($order));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = array(
            'id'           => rand(1, 100),
            'client'       => array(
                'id' => rand(1, 100)
            ),
            'service_type' => 'domain'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('toApiArray'))->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($orderArr));

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });
        $serviceMock->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $serviceMock->onAfterAdminOrderCancel($eventMock);
    }

    public function testOnAfterAdminOrderUncancel()
    {
        $params = array(
            'id' => rand(1, 100)
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->will($this->returnValue($params));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($order));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $orderArr = array(
            'id'           => rand(1, 100),
            'client'       => array(
                'id' => rand(1, 100)
            ),
            'service_type' => 'domain'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderServiceData', 'toApiArray'))->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($orderArr));

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });
        $serviceMock->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $serviceMock->onAfterAdminOrderUncancel($eventMock);
    }

    public function testOnAfterAdminOrderUncancel_LogException()
    {
        $params = array(
            'id' => rand(1, 100)
        );

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->will($this->returnValue($params));

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($order));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = array(
            'id'           => rand(1, 100),
            'client'       => array(
                'id' => rand(1, 100)
            ),
            'service_type' => 'domain'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getOrderServiceData', 'toApiArray'))->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($orderArr));

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedbeanPHP\OODBBean());

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });
        $serviceMock->setDi($di);
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $serviceMock->onAfterAdminOrderUncancel($eventMock);
    }

    public function testGetOrderCoreService()
    {
        $service = new \Model_ServiceCustom();

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('findOne');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($service));

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('to_camel_case')
            ->will($this->returnValue('ServiceCustom'));

        $di          = new \Box_Di();
        $di['db']    = $dbMock;
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedbeanPHP\OODBBean());
        $order->service_id   = rand(1, 100);
        $order->service_type = \Model_ProductTable::CUSTOM;

        $result = $this->service->getOrderService($order);
        $this->assertInstanceOf('Model_ServiceCustom', $result);
    }

    public function testGetOrderNotCoreService()
    {
        $service = new \Model_ServiceCustom();

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('getExistingModelById')
            ->will($this->returnValue($service));
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($service));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedbeanPHP\OODBBean());
        $order->service_id = rand(1, 100);

        $result = $this->service->getOrderService($order);
        $this->assertInstanceOf('Model_ServiceCustom', $result);
    }

    public function testGetOrderServiceIdNotSet()
    {
        $service = new \Model_ServiceCustom();

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('getExistingModelById')
            ->will($this->returnValue($service));
        $dbMock->expects($this->never())
            ->method('findOne')
            ->will($this->returnValue($service));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedbeanPHP\OODBBean());

        $result = $this->service->getOrderService($order);
        $this->assertNull($result);
    }

    public function testGetServiceOrder()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($order));

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('from_camel_case')
            ->will($this->returnValue('servicecustom'));

        $di          = new \Box_Di();
        $di['db']    = $dbMock;
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);

        $service = new \Model_ServiceCustom();
        $service->loadBean(new \RedBeanPHP\OODBBean());
        $service->id = rand(1, 100);

        $result = $this->service->getServiceOrder($service);
        $this->assertInstanceOf('Model_ClientOrder', $result);
    }

    public function testGetConfig()
    {
        $decoded   = array(
            'key' => 'value'
        );
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('decodeJ')
            ->will($this->returnValue($decoded));

        $di          = new \Box_Di();
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->getConfig($order);
        $this->assertIsArray($result);
    }

    public function productHasOrdersProvider()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        return array(
            array($order, true),
            array(null, false),
        );
    }

    /**
     * @dataProvider productHasOrdersProvider
     */
    public function testProductHasOrders($order, $expectedResult)
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($order));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $product = new \Model_Product();
        $product->loadBean(new \RedBeanPHP\OODBBean());
        $product->id = rand(1, 100);

        $result = $this->service->productHasOrders($product);

        $this->assertEquals($result, $expectedResult);
    }

    public function testSaveStatusChange()
    {
        $orderStatus = new \Model_ClientOrderStatus();
        $orderStatus->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($orderStatus));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->saveStatusChange($order);

        $this->assertNull($result);
    }

    public function testGetSoonExpiringActiveOrders()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue(array(array(), array())));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->setMethods(array('getSoonExpiringActiveOrdersQuery'))->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSoonExpiringActiveOrdersQuery')
            ->will($this->returnValue(array('query', array())));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $serviceMock->getSoonExpiringActiveOrders();
    }

    public function testGetSoonExpiringActiveOrdersQuery()
    {
        $randId = rand(1, 100);

        $orderStatus = new \Model_ClientOrderStatus();
        $orderStatus->loadBean(new \RedBeanPHP\OODBBean());

        $systemService = $this->getMockBuilder('\Box\Mod\System\Service')
            ->setMethods(array('getParamValue'))->getMock();
        $systemService->expects($this->atLeastOnce())->method('getParamValue')
            ->will($this->returnValue($randId));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($systemService) {
            return $systemService;
        });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $data   = array(
            'client_id' => $randId
        );
        $result = $this->service->getSoonExpiringActiveOrdersQuery($data);

        $expectedQuery = "SELECT *
                FROM client_order
                WHERE status = :status
                AND invoice_option = :invoice_option
                AND period IS NOT NULL
                AND expires_at IS NOT NULL
                AND unpaid_invoice_id IS NULL AND client_id = :client_id HAVING DATEDIFF(expires_at, NOW()) <= :days_until_expiration ORDER BY client_id DESC";

        $expectedBindings = array(
            ':client_id'             => $randId,
            ':status'                => 'active',
            ':invoice_option'        => 'issue-invoice',
            ':days_until_expiration' => $randId,
        );

        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertEquals($expectedQuery, $result[0]);
        $this->assertEquals($expectedBindings, $result[1]);

    }

    public function testgetRelatedOrderIdByType()
    {
        $id    = 1;
        $model = new \Model_ClientOrder();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = $id;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrder')
            ->will($this->returnValue($model));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getRelatedOrderIdByType($model, 'domain');
        $this->assertIsInt($result);
        $this->assertEquals($id, $result);
    }

    public function testgetRelatedOrderIdByType_returnNull()
    {
        $id    = 1;
        $model = new \Model_ClientOrder();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = $id;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrder')
            ->will($this->returnValue(null));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getRelatedOrderIdByType($model, 'domain');
        $this->assertNull($result);
    }

    public function testgetLogger()
    {
        $model = new \Model_ClientOrder();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $loggerMock = $this->getMockBuilder('\Box_Log')->disableOriginalConstructor()->getMock();
        $loggerMock->expects($this->atLeastOnce())
            ->method('addWriter');
        $loggerMock->expects($this->atLeastOnce())
            ->method('setEventItem')
            ->withConsecutive(
                array('client_order_id'),
                array('status')
            );

        $di           = new \Box_Di();
        $di['logger'] = $loggerMock;
        $this->service->setDi($di);

        $result = $this->service->getLogger($model);
        $this->assertInstanceOf('\Box_Log', $result);
    }

    public function testtoApiArray()
    {
        $model = new \Model_ClientOrder();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->config   = '{}';
        $model->price    = 10;
        $model->quantity = 1;
        $model->client_id = 1;

        $clientService = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $clientService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn(array());

        $supportService = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $supportService->expects($this->atLeastOnce())
            ->method('getActiveTicketsCountForOrder')
            ->willReturn(1);

        $dbMock = $this->getMockBuilder(\Box_Database::class)->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn(array());
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn(array());

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \RedBeanPHP\OODBBean());
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->withConsecutive(array('Product'))
            ->willReturnOnConsecutiveCalls($modelProduct);
        $exceptionError = 'Client not found';
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('Client', $model->client_id, $exceptionError)
            ->willReturn($modelClient);

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->willReturn(array());

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($clientService, $supportService) {
            if ($serviceName == 'client') {
                return $clientService;
            }
            if ($serviceName == 'support') {
                return $supportService;
            }
        });
        $di['db']          = $dbMock;
        $di['tools']       = $toolsMock;

        $this->service->setDi($di);
        $result = $this->service->toApiArray($model, true, new \Model_Admin());

        $this->assertArrayHasKey('config', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('active_tickets', $result);
        $this->assertArrayHasKey('plugin', $result);
        $this->assertArrayHasKey('client', $result);
    }

    public function searchQueryData()
    {
        return array(
            array(array(), 'SELECT co.* from client_order co', array()),
            array(
                array('client_id' => 1),
                'co.client_id = :client_id',
                array(':client_id' => '1',)
            ),
            array(
                array('invoice_option' => 'issue-invoice'),
                'co.invoice_option = :invoice_option',
                array(':invoice_option' => 'issue-invoice',)
            ),
            array(
                array('id' => 1),
                'co.id = :id',
                array(':id' => '1',)
            ),
            array(
                array('status' => 'pending_setup'),
                'co.status = :status',
                array(':status' => 'pending_setup',)
            ),
            array(
                array('product_id' => 1),
                'co.product_id = :product_id',
                array(':product_id' => '1',)
            ),
            array(
                array('type' => 'custom'),
                'co.service_type = :service_type',
                array(':service_type' => 'custom',)
            ),
            array(
                array('title' => 'titleField'),
                'co.title LIKE :title',
                array(':title' => '%titleField%',)
            ),
            array(
                array('period' => '1Y'),
                'co.period = :period',
                array(':period' => '1Y',)
            ),
            array(
                array('hide_addons' => true),
                'co.group_master = 1',
                array()
            ),
            array(
                array('created_at' => '2012-12-11'),
                "DATE_FORMAT(co.created_at, '%Y-%m-%d') = :created_at",
                array(':created_at' => '2012-12-11',)
            ),
            array(
                array('date_from' => '2012-12-11'),
                'UNIX_TIMESTAMP(co.created_at) >= :date_from',
                array(':date_from' => strtotime('2012-12-11'),)
            ),
            array(
                array('date_to' => '2012-12-11'),
                'UNIX_TIMESTAMP(co.created_at) <= :date_to',
                array(':date_to' => strtotime('2012-12-11'),)
            ),
            array(
                array('search' => 120),
                'co.id = :search',
                array(':search' => 120,)
            ),
            array(
                array('search' => 'John'),
                '(c.first_name LIKE :first_name OR c.last_name LIKE :last_name OR co.title LIKE :title)',
                array(
                    ':first_name' => '%John%',
                    ':last_name'  => '%John%',
                    ':title'      => '%John%',
                )
            ),
            array(
                array('ids' => array(1, 2, 3)),
                'co.id IN (:ids)',
                array(':ids' => array(1, 2, 3))
            ),

            array(
                array('meta' => array('param' => 'value')),
                '(meta.name = :meta_name1 AND meta.value LIKE :meta_value1)',
                array(
                    ':meta_name1'  => 'param',
                    ':meta_value1' => 'value%'
                ),
            ),
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

        $result = $this->service->getSearchQuery($data);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(strpos($result[0], $expectedStr) !== false, $result[0]);
        $this->assertTrue(array_diff_key($result[1], $expectedParams) == array());
    }

    public function testcreateOrder_MissingOrderCurrency()
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \RedBeanPHP\OODBBean());

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \RedBeanPHP\OODBBean());

        $currencyServiceMock = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getDefault')
            ->willReturn(null);

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock) {
            if ($serviceName == 'currency') {
                return $currencyServiceMock;
            }
        });

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Currency could not be determined for order');
        $this->service->createOrder($modelClient, $modelProduct, array());
    }

    public function testcreateOrder_OutOfStock()
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \RedBeanPHP\OODBBean());
        $modelClient->currency = 'USD';

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \RedBeanPHP\OODBBean());
        $modelProduct->id = 1;

        $currencyModel = new \Model_Currency();
        $currencyModel->loadBean(new \RedBeanPHP\OODBBean());

        $currencyServiceMock = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn($currencyModel);

        $cartServiceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')->getMock();
        $cartServiceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->with($modelProduct)
            ->willReturn(false);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di                   = new \Box_Di();
        $di['mod_service']    = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock) {
            if ($serviceName == 'currency') {
                return $currencyServiceMock;
            }
            if ($serviceName == 'cart') {
                return $cartServiceMock;
            }
        });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $di['events_manager'] = $eventMock;

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(831);
        $this->expectExceptionMessage('Product 1 is out of stock.');
        $this->service->createOrder($modelClient, $modelProduct, array());
    }

    public function testcreateOrder_GroupIdMissing()
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \RedBeanPHP\OODBBean());
        $modelClient->currency = 'USD';

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \RedBeanPHP\OODBBean());
        $modelProduct->id       = 1;
        $modelProduct->is_addon = 1;

        $currencyModel = new \Model_Currency();
        $currencyModel->loadBean(new \RedBeanPHP\OODBBean());

        $currencyServiceMock = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn($currencyModel);

        $cartServiceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')->getMock();
        $cartServiceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->with($modelProduct)
            ->willReturn(true);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di                   = new \Box_Di();
        $di['mod_service']    = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock) {
            if ($serviceName == 'currency') {
                return $currencyServiceMock;
            }
            if ($serviceName == 'cart') {
                return $cartServiceMock;
            }
        });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $di['events_manager'] = $eventMock;

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(832);
        $this->expectExceptionMessage('Group ID parameter is missing for addon product order');
        $this->service->createOrder($modelClient, $modelProduct, array());
    }

    public function testcreateOrder_ParentOrderNotFound()
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \RedBeanPHP\OODBBean());
        $modelClient->currency = 'USD';

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \RedBeanPHP\OODBBean());
        $modelProduct->id = 1;

        $currencyModel = new \Model_Currency();
        $currencyModel->loadBean(new \RedBeanPHP\OODBBean());

        $currencyServiceMock = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn($currencyModel);

        $cartServiceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')->getMock();
        $cartServiceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->with($modelProduct)
            ->willReturn(true);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di                   = new \Box_Di();
        $di['mod_service']    = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock) {
            if ($serviceName == 'currency') {
                return $currencyServiceMock;
            }
            if ($serviceName == 'cart') {
                return $cartServiceMock;
            }
        });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $di['events_manager'] = $eventMock;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('getMasterOrderForClient'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getMasterOrderForClient')
            ->willReturn(null);

        $serviceMock->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Parent order 1 was not found');
        $serviceMock->createOrder($modelClient, $modelProduct, array('group_id' => 1));
    }

    public function testcreateOrder()
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \RedBeanPHP\OODBBean());
        $modelClient->currency = 'USD';

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \RedBeanPHP\OODBBean());
        $modelProduct->id   = 1;
        $modelProduct->type = 'custom';

        $currencyModel = new \Model_Currency();
        $currencyModel->loadBean(new \RedBeanPHP\OODBBean());
        $currencyServiceMock = $this->getMockBuilder('\Box\Mod\Currency\Service')->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn($currencyModel);
        $cartServiceMock = $this->getMockBuilder('\Box\Mod\Cart\Service')->getMock();
        $cartServiceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->with($modelProduct)
            ->willReturn(true);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $productServiceMock = $this->getMockBuilder('\Box\Mod\Servicecustom')->getMock();

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->with('ClientOrder')
            ->willReturn($clientOrderModel);
        $newId = 1;
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($clientOrderModel)
            ->willReturn($newId);

        $periodMock = $this->getMockBuilder('\Box_Period')->disableOriginalConstructor()->getMock();
        $periodMock->expects($this->atLeastOnce())
            ->method('getCode')
            ->willReturn('1Y');

        $di                   = new \Box_Di();
        $di['mod_service']    = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock, $productServiceMock) {
            if ($serviceName == 'currency') {
                return $currencyServiceMock;
            }
            if ($serviceName == 'cart') {
                return $cartServiceMock;
            }
            if ($serviceName == 'servicecustom') {
                return $productServiceMock;
            }
        });
        $di['events_manager'] = $eventMock;
        $di['db']             = $dbMock;
        $di['period']         = $di->protect(function () use ($periodMock) { return $periodMock; });
        $di['logger']         = new \Box_Log();
        $di['array_get']      = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->service->setDi($di);
        $result = $this->service->createOrder($modelClient, $modelProduct, array('period' => '1Y', 'price' => '10', 'notes' => 'test'));
        $this->assertEquals($newId, $result);
    }

    public function testgetMasterOrderForClient()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrder')
            ->willReturn($clientOrderModel);

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);
        $result = $this->service->getMasterOrderForClient($clientModel, 1);
        $this->assertInstanceOf('Model_ClientOrder', $result);
    }

    public function testactivateOrder_ExceptionPendingOrFailedOrders()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_CANCELED;
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Only pending setup or failed orders can be activated');
        $this->service->activateOrder($clientOrderModel);
    }

    public function testactivateOrder()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrderModel->status       = \Model_ClientOrder::STATUS_PENDING_SETUP;
        $clientOrderModel->group_master = 1;

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['logger']         = new \Box_Log();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('createFromOrder', 'getOrderAddonsList', 'activateOrderAddons'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('createFromOrder')
            ->willReturn(array());
        $serviceMock->expects($this->atLeastOnce())
            ->method('activateOrderAddons');

        $serviceMock->setDi($di);
        $result = $serviceMock->activateOrder($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testactivateOrderAddons()
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('createFromOrder', 'getOrderAddonsList'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('createFromOrder')
            ->willReturn(array());

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrderModel->status       = \Model_ClientOrder::STATUS_PENDING_SETUP;
        $clientOrderModel->group_master = 1;
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderAddonsList')
            ->willReturn(array($clientOrderModel));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->activateOrderAddons($clientOrderModel);
        $this->assertTrue($result);

    }

    public function testgetOrderAddonsList()
    {
        $modelClientOrder = new \Model_ClientOrder();
        $modelClientOrder->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('ClientOrder')
            ->willReturn(array(new \Model_ClientOrder()));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getOrderAddonsList($modelClientOrder);
        $this->assertIsArray($result);
        $this->assertInstanceOf('\Model_ClientOrder', $result[0]);
    }

    public function teststockSale()
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \RedBeanPHP\OODBBean());
        $productModel->stock_control = 1;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($productModel);

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->stockSale($productModel, 2);
        $this->assertTrue($result);
    }

    public function testupdateOrder()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($clientOrderModel);

        $di                   = new \Box_Di();
        $di['array_get']      = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $di['events_manager'] = $eventMock;
        $di['db']             = $dbMock;
        $di['logger']         = new \Box_Log();

        $data = array(
            'period'         => '1Y',
            'created_at'     => '2012-12-01',
            'activated_at'   => '2012-12-01',
            'expires_at'     => '2013-12-01',
            'invoice_option' => 'issue-invoice',
            'title'          => 'Testing',
            'price'          => 10,
            'status'         => 'active',
            'notes'          => 'Empty note',
            'reason'         => 'non',
            'meta'           => array()

        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('updatePeriod', 'updateOrderMeta'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updatePeriod')
            ->with($clientOrderModel, $data['period']);
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateOrderMeta')
            ->with($clientOrderModel, $data['meta']);

        $serviceMock->setDi($di);
        $result = $serviceMock->updateOrder($clientOrderModel, $data);
        $this->assertTrue($result);
    }

    public function testrenewOrder()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrderModel->group_master = 1;
        $clientOrderModel->status       = \Model_ClientOrder::STATUS_PENDING_SETUP;

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['logger']         = new \Box_Log();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('renewFromOrder', 'getOrderAddonsList'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('renewFromOrder')
            ->with($clientOrderModel);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderAddonsList')
            ->willReturn(array($clientOrderModel));

        $serviceMock->setDi($di);
        $result = $serviceMock->renewOrder($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testrenewFromOrder()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('_callOnService', 'saveStatusChange'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('_callOnService');
        $serviceMock->expects($this->atLeastOnce())
            ->method('saveStatusChange');
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrderModel->period = '1Y';

        $periodMock = $this->getMockBuilder('\Box_Period')->disableOriginalConstructor()->getMock();
        $periodMock->expects($this->atLeastOnce())
            ->method('getExpirationTime');

        $dbMock = $this->getMockBuilder('Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($clientOrderModel);

        $di               = new \Box_Di();
        $di['mod_config'] = $di->protect(function ($name) { return array(); });
        $di['period']     = $di->protect(function () use ($periodMock) { return $periodMock; });
        $di['db']         = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $serviceMock->setDi($di);
        $serviceMock->renewFromOrder($clientOrderModel);
    }

    public function testsuspendFromOrder_ExceptionNotActiveOrder()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_SUSPENDED;

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventMock;

        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Only active orders can be suspended');
        $this->service->suspendFromOrder($clientOrderModel);
    }

    public function testsuspendFromOrder()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_ACTIVE;

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($clientOrderModel);


        $di                   = new \Box_Di();
        $di['events_manager'] = $eventMock;
        $di['logger']         = new \Box_Log();
        $di['db']             = $dbMock;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
            ->setMethods(array('_callOnService', 'saveStatusChange'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('_callOnService');
        $serviceMock->expects($this->atLeastOnce())
            ->method('saveStatusChange');

        $serviceMock->setDi($di);
        $result = $serviceMock->suspendFromOrder($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testrmByClient()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $pdoStatment = $this->getMockBuilder('\Box\Mod\Order\PdoStatmentsMock')->getMock();
        $pdoStatment->expects($this->atLeastOnce())
            ->method('execute');


        $pdoMock = $this->getMockBuilder('\Box\Mod\Order\PdoMock')->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->will($this->returnValue($pdoStatment));

        $di        = new \Box_Di();
        $di['pdo'] = $pdoMock;
        $this->service->setDi($di);
        $this->service->rmByClient($clientModel);

    }

    public function testupdatePeriod()
    {
        $period = '1Y';
        $di     = new \Box_Di();

        $periodMock = $this->getMockBuilder('\Box_Period')->disableOriginalConstructor()->getMock();
        $periodMock->expects($this->atLeastOnce())
            ->method('getCode');
        $di['period'] = $di->protect(function () use ($periodMock) {
            return $periodMock;
        });

        $this->service->setDi($di);
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());
        $result = $this->service->updatePeriod($clientOrder, $period);
        $this->assertEquals(1, $result);
    }

    public function testupdatePeriod_isEmpty()
    {
        $period     = '';
        $di         = new \Box_Di();
        $periodMock = $this->getMockBuilder('\Box_Period')->disableOriginalConstructor()->getMock();
        $periodMock->expects($this->never())
            ->method('getCode');
        $di['period'] = $di->protect(function () use ($periodMock) {
            return $periodMock;
        });

        $this->service->setDi($di);
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());
        $result = $this->service->updatePeriod($clientOrder, $period);
        $this->assertEquals(2, $result);
    }

    public function testupdatePeriod_notSet()
    {
        $period     = null;
        $di         = new \Box_Di();
        $periodMock = $this->getMockBuilder('\Box_Period')->disableOriginalConstructor()->getMock();
        $periodMock->expects($this->never())
            ->method('getCode');
        $di['period'] = $di->protect(function () use ($periodMock) {
            return $periodMock;
        });

        $this->service->setDi($di);
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());
        $result = $this->service->updatePeriod($clientOrder, $period);
        $this->assertEquals(0, $result);
    }

    public function testupdateOrderMeta_isNotSet()
    {
        $meta        = null;
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());
        $result = $this->service->updateOrderMeta($clientOrder, $meta);
        $this->assertEquals(0, $result);
    }

    public function testupdateOrderMeta_isEmpty()
    {
        $meta = array();
        $di   = new \Box_Di();

        $dBMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dBMock->expects($this->atLeastOnce())
            ->method('exec');
        $di['db'] = $dBMock;

        $this->service->setDi($di);
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());
        $result = $this->service->updateOrderMeta($clientOrder, $meta);
        $this->assertEquals(1, $result);
    }

    public function testupdateOrderMeta()
    {
        $meta = array(
            'key' => 'value',
        );
        $di   = new \Box_Di();

        $dBMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dBMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrderMeta')
            ->willReturn(null);

        $clientOrderMetaModel = new \Model_ClientOrderMeta();
        $clientOrderMetaModel->loadBean(new \RedBeanPHP\OODBBean());
        $dBMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->with('ClientOrderMeta')
            ->willReturn($clientOrderMetaModel);
        $dBMock->expects($this->atLeastOnce())
            ->method('store');
        $di['db'] = $dBMock;

        $this->service->setDi($di);
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());
        $result = $this->service->updateOrderMeta($clientOrder, $meta);
        $this->assertEquals(2, $result);
    }
}
 