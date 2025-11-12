<?php

namespace Box\Mod\Order;

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
     * @var Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new Service();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testCounter(): void
    {
        $counter = [
            \Model_ClientOrder::STATUS_ACTIVE => random_int(1, 100),
        ];
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn($counter);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

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

    public function testOnAfterAdminOrderActivate(): void
    {
        $params = [
            'id' => random_int(1, 100),
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($order);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $orderArr = [
            'id' => random_int(1, 100),
            'client' => [
                'id' => random_int(1, 100),
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $serviceMock->setDi($di);

        $serviceMock->onAfterAdminOrderActivate($eventMock);
    }

    public function testOnAfterAdminOrderActivateLogException(): void
    {
        $params = [
            'id' => random_int(1, 100),
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($order);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = [
            'id' => random_int(1, 100),
            'client' => [
                'id' => random_int(1, 100),
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'order') {
                return $serviceMock;
            }
        });

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $serviceMock->setDi($di);

        $serviceMock->onAfterAdminOrderActivate($eventMock);
    }

    public function testOnAfterAdminOrderRenew(): void
    {
        $params = [
            'id' => random_int(1, 100),
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($order);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $orderArr = [
            'id' => random_int(1, 100),
            'client' => [
                'id' => random_int(1, 100),
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
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
            ->willReturn($di);

        $serviceMock->onAfterAdminOrderRenew($eventMock);
    }

    public function testOnAfterAdminOrderRenewLogException(): void
    {
        $params = [
            'id' => random_int(1, 100),
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($order);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = [
            'id' => random_int(1, 100),
            'client' => [
                'id' => random_int(1, 100),
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
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
            ->willReturn($di);

        $serviceMock->onAfterAdminOrderRenew($eventMock);
    }

    public function testOnAfterAdminOrderSuspend(): void
    {
        $params = [
            'id' => random_int(1, 100),
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($order);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $orderArr = [
            'id' => random_int(1, 100),
            'client' => [
                'id' => random_int(1, 100),
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
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
            ->willReturn($di);

        $serviceMock->onAfterAdminOrderSuspend($eventMock);
    }

    public function testOnAfterAdminOrderSuspendLogException(): void
    {
        $params = [
            'id' => random_int(1, 100),
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($order);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = [
            'id' => random_int(1, 100),
            'client' => [
                'id' => random_int(1, 100),
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
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
            ->willReturn($di);

        $serviceMock->onAfterAdminOrderSuspend($eventMock);
    }

    public function testOnAfterAdminOrderUnsuspend(): void
    {
        $params = [
            'id' => random_int(1, 100),
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($order);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $orderArr = [
            'id' => random_int(1, 100),
            'client' => [
                'id' => random_int(1, 100),
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
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
            ->willReturn($di);

        $serviceMock->onAfterAdminOrderUnsuspend($eventMock);
    }

    public function testOnAfterAdminOrderUnsuspendLogException(): void
    {
        $params = [
            'id' => random_int(1, 100),
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($order);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = [
            'id' => random_int(1, 100),
            'client' => [
                'id' => random_int(1, 100),
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
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
            ->willReturn($di);

        $serviceMock->onAfterAdminOrderUnsuspend($eventMock);
    }

    public function testOnAfterAdminOrderCancel(): void
    {
        $params = [
            'id' => random_int(1, 100),
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($order);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $orderArr = [
            'id' => random_int(1, 100),
            'client' => [
                'id' => random_int(1, 100),
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)->onlyMethods(['toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
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
            ->willReturn($di);

        $serviceMock->onAfterAdminOrderCancel($eventMock);
    }

    public function testOnAfterAdminOrderCancelLogException(): void
    {
        $params = [
            'id' => random_int(1, 100),
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($order);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = [
            'id' => random_int(1, 100),
            'client' => [
                'id' => random_int(1, 100),
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)->onlyMethods(['toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
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
            ->willReturn($di);

        $serviceMock->onAfterAdminOrderCancel($eventMock);
    }

    public function testOnAfterAdminOrderUncancel(): void
    {
        $params = [
            'id' => random_int(1, 100),
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($order);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $orderArr = [
            'id' => random_int(1, 100),
            'client' => [
                'id' => random_int(1, 100),
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
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
            ->willReturn($di);

        $serviceMock->onAfterAdminOrderUncancel($eventMock);
    }

    public function testOnAfterAdminOrderUncancelLogException(): void
    {
        $params = [
            'id' => random_int(1, 100),
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($params);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($order);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = [
            'id' => random_int(1, 100),
            'client' => [
                'id' => random_int(1, 100),
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
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
            ->willReturn($di);

        $serviceMock->onAfterAdminOrderUncancel($eventMock);
    }

    public function testGetOrderCoreService(): void
    {
        $service = new \Model_ServiceCustom();

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('findOne');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($service);

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('to_camel_case')
            ->willReturn('ServiceCustom');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->service_id = random_int(1, 100);
        $order->service_type = \Model_ProductTable::CUSTOM;

        $result = $this->service->getOrderService($order);
        $this->assertInstanceOf('Model_ServiceCustom', $result);
    }

    public function testGetOrderNotCoreService(): void
    {
        $service = new \Model_ServiceCustom();

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('getExistingModelById')
            ->willReturn($service);
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($service);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->service_id = random_int(1, 100);

        $result = $this->service->getOrderService($order);
        $this->assertInstanceOf('Model_ServiceCustom', $result);
    }

    public function testGetOrderServiceIdNotSet(): void
    {
        $service = new \Model_ServiceCustom();

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('getExistingModelById')
            ->willReturn($service);
        $dbMock->expects($this->never())
            ->method('findOne')
            ->willReturn($service);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $result = $this->service->getOrderService($order);
        $this->assertNull($result);
    }

    public function testGetServiceOrder(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($order);

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('from_camel_case')
            ->willReturn('servicecustom');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);

        $service = new \Model_ServiceCustom();
        $service->loadBean(new \DummyBean());
        $service->id = random_int(1, 100);

        $result = $this->service->getServiceOrder($service);
        $this->assertInstanceOf('Model_ClientOrder', $result);
    }

    public function testGetConfig(): void
    {
        $decoded = [
            'key' => 'value',
        ];

        $di = new \Pimple\Container();
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $result = $this->service->getConfig($order);
        $this->assertIsArray($result);
    }

    public static function productHasOrdersProvider(): array
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        return [
            [$order, true],
            [null, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('productHasOrdersProvider')]
    public function testProductHasOrders(?\Model_ClientOrder $order, bool $expectedResult): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($order);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());
        $product->id = random_int(1, 100);

        $result = $this->service->productHasOrders($product);

        $this->assertEquals($result, $expectedResult);
    }

    public function testSaveStatusChange(): void
    {
        $orderStatus = new \Model_ClientOrderStatus();
        $orderStatus->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($orderStatus);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $result = $this->service->saveStatusChange($order);

        $this->assertNull($result);
    }

    public function testGetSoonExpiringActiveOrders(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([[], []]);

        $serviceMock = $this->getMockBuilder('\\' . Service::class)->onlyMethods(['getSoonExpiringActiveOrdersQuery'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSoonExpiringActiveOrdersQuery')
            ->willReturn(['query', []]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $serviceMock->getSoonExpiringActiveOrders();
    }

    public function testGetSoonExpiringActiveOrdersQuery(): void
    {
        $randId = random_int(1, 100);

        $orderStatus = new \Model_ClientOrderStatus();
        $orderStatus->loadBean(new \DummyBean());

        $systemService = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)
            ->onlyMethods(['getParamValue'])->getMock();
        $systemService->expects($this->atLeastOnce())->method('getParamValue')
            ->willReturn($randId);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);

        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $data = [
            'client_id' => $randId,
        ];
        $result = $this->service->getSoonExpiringActiveOrdersQuery($data);

        $expectedQuery = 'SELECT *
                FROM client_order
                WHERE status = :status
                AND invoice_option = :invoice_option
                AND period IS NOT NULL
                AND expires_at IS NOT NULL
                AND unpaid_invoice_id IS NULL AND client_id = :client_id HAVING DATEDIFF(expires_at, NOW()) <= :days_until_expiration ORDER BY client_id DESC';

        $expectedBindings = [
            ':client_id' => $randId,
            ':status' => 'active',
            ':invoice_option' => 'issue-invoice',
            ':days_until_expiration' => $randId,
        ];

        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertEquals($expectedQuery, $result[0]);
        $this->assertEquals($expectedBindings, $result[1]);
    }

    public function testgetRelatedOrderIdByType(): void
    {
        $id = 1;
        $model = new \Model_ClientOrder();
        $model->loadBean(new \DummyBean());
        $model->id = $id;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrder')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getRelatedOrderIdByType($model, 'domain');
        $this->assertIsInt($result);
        $this->assertEquals($id, $result);
    }

    public function testgetRelatedOrderIdByTypeReturnNull(): void
    {
        $id = 1;
        $model = new \Model_ClientOrder();
        $model->loadBean(new \DummyBean());
        $model->id = $id;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrder')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getRelatedOrderIdByType($model, 'domain');
        $this->assertNull($result);
    }

    public function testgetLogger(): void
    {
        $model = new \Model_ClientOrder();
        $model->loadBean(new \DummyBean());

        $loggerMock = $this->getMockBuilder('\Box_Log')->disableOriginalConstructor()->getMock();
        $loggerMock->expects($this->atLeastOnce())
            ->method('addWriter');

        $setEventItemExpected = ['client_order_id', 'status'];
        $matcher = $this->atLeastOnce();
        $loggerMock->expects($matcher)
            ->method('setEventItem')
            ->willReturnCallback(function (...$args) use ($matcher, $loggerMock) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals($args[0], 'client_order_id'),
                    2 => $this->assertEquals($args[0], 'status'),
                };

                return $loggerMock;
            });

        $di = new \Pimple\Container();
        $di['logger'] = $loggerMock;
        $this->service->setDi($di);

        $result = $this->service->getLogger($model);
        $this->assertInstanceOf('\Box_Log', $result);
    }

    public function testtoApiArray(): void
    {
        $model = new \Model_ClientOrder();
        $model->loadBean(new \DummyBean());
        $model->config = '{}';
        $model->price = 10;
        $model->quantity = 1;
        $model->client_id = 1;

        $clientService = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $clientService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $supportService = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)->getMock();
        $supportService->expects($this->atLeastOnce())
            ->method('getActiveTicketsCountForOrder')
            ->willReturn(1);

        $dbMock = $this->getMockBuilder(\Box_Database::class)->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn([]);

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturnCallback(fn (...$args): \Model_Product => match ($args[0]) {
                'Product' => $modelProduct,
            });

        $exceptionError = 'Client not found';
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('Client', $model->client_id, $exceptionError)
            ->willReturn($modelClient);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($clientService, $supportService) {
            if ($serviceName == 'client') {
                return $clientService;
            }
            if ($serviceName == 'support') {
                return $supportService;
            }
        });
        $di['db'] = $dbMock;

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

    public static function searchQueryData(): array
    {
        return [
            [[], 'SELECT co.* from client_order co', []],
            [
                ['client_id' => 1],
                'co.client_id = :client_id',
                [':client_id' => '1'],
            ],
            [
                ['invoice_option' => 'issue-invoice'],
                'co.invoice_option = :invoice_option',
                [':invoice_option' => 'issue-invoice'],
            ],
            [
                ['id' => 1],
                'co.id = :id',
                [':id' => '1'],
            ],
            [
                ['status' => 'pending_setup'],
                'co.status = :status',
                [':status' => 'pending_setup'],
            ],
            [
                ['product_id' => 1],
                'co.product_id = :product_id',
                [':product_id' => '1'],
            ],
            [
                ['type' => 'custom'],
                'co.service_type = :service_type',
                [':service_type' => 'custom'],
            ],
            [
                ['title' => 'titleField'],
                'co.title LIKE :title',
                [':title' => '%titleField%'],
            ],
            [
                ['period' => '1Y'],
                'co.period = :period',
                [':period' => '1Y'],
            ],
            [
                ['hide_addons' => true],
                'co.group_master = 1',
                [],
            ],
            [
                ['created_at' => '2012-12-11'],
                "DATE_FORMAT(co.created_at, '%Y-%m-%d') = :created_at",
                [':created_at' => '2012-12-11'],
            ],
            [
                ['date_from' => '2012-12-11'],
                'UNIX_TIMESTAMP(co.created_at) >= :date_from',
                [':date_from' => strtotime('2012-12-11')],
            ],
            [
                ['date_to' => '2012-12-11'],
                'UNIX_TIMESTAMP(co.created_at) <= :date_to',
                [':date_to' => strtotime('2012-12-11')],
            ],
            [
                ['search' => 120],
                'co.id = :search',
                [':search' => 120],
            ],
            [
                ['search' => 'John'],
                '(c.first_name LIKE :first_name OR c.last_name LIKE :last_name OR co.title LIKE :title)',
                [
                    ':first_name' => '%John%',
                    ':last_name' => '%John%',
                    ':title' => '%John%',
                ],
            ],
            [
                ['ids' => [1, 2, 3]],
                'co.id IN (:ids)',
                [':ids' => [1, 2, 3]],
            ],

            [
                ['meta' => ['param' => 'value']],
                '(meta.name = :meta_name1 AND meta.value LIKE :meta_value1)',
                [
                    ':meta_name1' => 'param',
                    ':meta_value1' => 'value%',
                ],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('searchQueryData')]
    public function testgetSearchQuery(array $data, string $expectedStr, array $expectedParams): void
    {
        $di = new \Pimple\Container();

        $this->service->setDi($di);

        $result = $this->service->getSearchQuery($data);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(str_contains($result[0], $expectedStr), $result[0]);
        $this->assertTrue(array_diff_key($result[1], $expectedParams) == []);
    }

    public function testcreateOrderMissingOrderCurrency(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getDefault')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock) {
            if ($serviceName == 'currency') {
                return $currencyServiceMock;
            }
        });

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Currency could not be determined for order');
        $this->service->createOrder($modelClient, $modelProduct, []);
    }

    public function testcreateOrderOutOfStock(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->currency = 'USD';

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());
        $modelProduct->id = 1;

        $currencyModel = new \Model_Currency();
        $currencyModel->loadBean(new \DummyBean());

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn($currencyModel);

        $cartServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)->getMock();
        $cartServiceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->with($modelProduct)
            ->willReturn(false);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock) {
            if ($serviceName == 'currency') {
                return $currencyServiceMock;
            }
            if ($serviceName == 'cart') {
                return $cartServiceMock;
            }
        });

        $di['events_manager'] = $eventMock;

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(831);
        $this->expectExceptionMessage('Product 1 is out of stock.');
        $this->service->createOrder($modelClient, $modelProduct, []);
    }

    public function testcreateOrderGroupIdMissing(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->currency = 'USD';

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());
        $modelProduct->id = 1;
        $modelProduct->is_addon = 1;

        $currencyModel = new \Model_Currency();
        $currencyModel->loadBean(new \DummyBean());

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn($currencyModel);

        $cartServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)->getMock();
        $cartServiceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->with($modelProduct)
            ->willReturn(true);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock) {
            if ($serviceName == 'currency') {
                return $currencyServiceMock;
            }
            if ($serviceName == 'cart') {
                return $cartServiceMock;
            }
        });

        $di['events_manager'] = $eventMock;

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(832);
        $this->expectExceptionMessage('Group ID parameter is missing for addon product order');
        $this->service->createOrder($modelClient, $modelProduct, []);
    }

    public function testcreateOrderParentOrderNotFound(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->currency = 'USD';

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());
        $modelProduct->id = 1;

        $currencyModel = new \Model_Currency();
        $currencyModel->loadBean(new \DummyBean());

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn($currencyModel);

        $cartServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)->getMock();
        $cartServiceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->with($modelProduct)
            ->willReturn(true);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock) {
            if ($serviceName == 'currency') {
                return $currencyServiceMock;
            }
            if ($serviceName == 'cart') {
                return $cartServiceMock;
            }
        });

        $di['events_manager'] = $eventMock;

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getMasterOrderForClient'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getMasterOrderForClient')
            ->willReturn(null);

        $serviceMock->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Parent order 1 was not found');
        $serviceMock->createOrder($modelClient, $modelProduct, ['group_id' => 1]);
    }

    public function testcreateOrder(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->currency = 'USD';

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());
        $modelProduct->id = 1;
        $modelProduct->type = 'custom';

        $currencyModel = new \Model_Currency();
        $currencyModel->loadBean(new \DummyBean());
        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getByCode')
            ->willReturn($currencyModel);
        $cartServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)->getMock();
        $cartServiceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->with($modelProduct)
            ->willReturn(true);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $productServiceMock = $this->getMockBuilder('\Box\Mod\Servicecustom')->getMock();

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

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

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock, $productServiceMock) {
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
        $di['db'] = $dbMock;
        $di['period'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $periodMock);
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);
        $result = $this->service->createOrder($modelClient, $modelProduct, ['period' => '1Y', 'price' => '10', 'notes' => 'test']);
        $this->assertEquals($newId, $result);
    }

    public function testgetMasterOrderForClient(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrder')
            ->willReturn($clientOrderModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);
        $result = $this->service->getMasterOrderForClient($clientModel, 1);
        $this->assertInstanceOf('Model_ClientOrder', $result);
    }

    public function testactivateOrderExceptionPendingOrFailedOrders(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_CANCELED;
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Only pending setup or failed orders can be activated');
        $this->service->activateOrder($clientOrderModel);
    }

    public function testactivateOrder(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_PENDING_SETUP;
        $clientOrderModel->group_master = 1;

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['createFromOrder', 'getOrderAddonsList', 'activateOrderAddons'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('createFromOrder')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('activateOrderAddons');

        $serviceMock->setDi($di);
        $result = $serviceMock->activateOrder($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testactivateOrderAddons(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['createFromOrder', 'getOrderAddonsList'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('createFromOrder')
            ->willReturn([]);

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_PENDING_SETUP;
        $clientOrderModel->group_master = 1;
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderAddonsList')
            ->willReturn([$clientOrderModel]);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->activateOrderAddons($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testgetOrderAddonsList(): void
    {
        $modelClientOrder = new \Model_ClientOrder();
        $modelClientOrder->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('ClientOrder')
            ->willReturn([new \Model_ClientOrder()]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getOrderAddonsList($modelClientOrder);
        $this->assertIsArray($result);
        $this->assertInstanceOf('\Model_ClientOrder', $result[0]);
    }

    public function teststockSale(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->stock_control = 1;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($productModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->stockSale($productModel, 2);
        $this->assertTrue($result);
    }

    public function testupdateOrder(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($clientOrderModel);

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $data = [
            'period' => '1Y',
            'created_at' => '2012-12-01',
            'activated_at' => '2012-12-01',
            'expires_at' => '2013-12-01',
            'invoice_option' => 'issue-invoice',
            'title' => 'Testing',
            'price' => 10,
            'status' => 'active',
            'notes' => 'Empty note',
            'reason' => 'non',
            'meta' => [],
        ];

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['updatePeriod', 'updateOrderMeta'])
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

    public function testrenewOrder(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->group_master = 1;
        $clientOrderModel->status = \Model_ClientOrder::STATUS_PENDING_SETUP;

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['renewFromOrder', 'getOrderAddonsList'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('renewFromOrder')
            ->with($clientOrderModel);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderAddonsList')
            ->willReturn([$clientOrderModel]);

        $serviceMock->setDi($di);
        $result = $serviceMock->renewOrder($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testrenewFromOrder(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['_callOnService', 'saveStatusChange'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('_callOnService');
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->period = '1Y';

        $periodMock = $this->getMockBuilder('\Box_Period')->disableOriginalConstructor()->getMock();

        $dbMock = $this->getMockBuilder('Box_Database')->getMock();

        $invoiceServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('findPaidInvoicesForOrder');

        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn ($name): array => []);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $invoiceServiceMock);
        $di['period'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $periodMock);
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $serviceMock->renewFromOrder($clientOrderModel);
    }

    public function testsuspendFromOrderExceptionNotActiveOrder(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_SUSPENDED;

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Only active orders can be suspended');
        $this->service->suspendFromOrder($clientOrderModel);
    }

    public function testsuspendFromOrder(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_ACTIVE;

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($clientOrderModel);

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['_callOnService', 'saveStatusChange'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('_callOnService');
        $serviceMock->expects($this->atLeastOnce())
            ->method('saveStatusChange');

        $serviceMock->setDi($di);
        $result = $serviceMock->suspendFromOrder($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testrmByClient(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $pdoStatment = $this->getMockBuilder('\\' . PdoStatmentsMock::class)->getMock();
        $pdoStatment->expects($this->atLeastOnce())
            ->method('execute');

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatment);

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;
        $this->service->setDi($di);
        $this->service->rmByClient($clientModel);
    }

    public function testupdatePeriod(): void
    {
        $period = '1Y';
        $di = new \Pimple\Container();

        $periodMock = $this->getMockBuilder('\Box_Period')->disableOriginalConstructor()->getMock();
        $periodMock->expects($this->atLeastOnce())
            ->method('getCode');
        $di['period'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $periodMock);

        $this->service->setDi($di);
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $result = $this->service->updatePeriod($clientOrder, $period);
        $this->assertEquals(1, $result);
    }

    public function testupdatePeriodIsEmpty(): void
    {
        $period = '';
        $di = new \Pimple\Container();
        $periodMock = $this->getMockBuilder('\Box_Period')->disableOriginalConstructor()->getMock();
        $periodMock->expects($this->never())
            ->method('getCode');
        $di['period'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $periodMock);

        $this->service->setDi($di);
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $result = $this->service->updatePeriod($clientOrder, $period);
        $this->assertEquals(2, $result);
    }

    public function testupdatePeriodNotSet(): void
    {
        $period = null;
        $di = new \Pimple\Container();
        $periodMock = $this->getMockBuilder('\Box_Period')->disableOriginalConstructor()->getMock();
        $periodMock->expects($this->never())
            ->method('getCode');
        $di['period'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $periodMock);

        $this->service->setDi($di);
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $result = $this->service->updatePeriod($clientOrder, $period);
        $this->assertEquals(0, $result);
    }

    public function testupdateOrderMetaIsNotSet(): void
    {
        $meta = null;
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $result = $this->service->updateOrderMeta($clientOrder, $meta);
        $this->assertEquals(0, $result);
    }

    public function testupdateOrderMetaIsEmpty(): void
    {
        $meta = [];
        $di = new \Pimple\Container();

        $dBMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dBMock->expects($this->atLeastOnce())
            ->method('exec');
        $di['db'] = $dBMock;

        $this->service->setDi($di);
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $result = $this->service->updateOrderMeta($clientOrder, $meta);
        $this->assertEquals(1, $result);
    }

    public function testupdateOrderMeta(): void
    {
        $meta = [
            'key' => 'value',
        ];
        $di = new \Pimple\Container();

        $dBMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dBMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrderMeta')
            ->willReturn(null);

        $clientOrderMetaModel = new \Model_ClientOrderMeta();
        $clientOrderMetaModel->loadBean(new \DummyBean());
        $dBMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->with('ClientOrderMeta')
            ->willReturn($clientOrderMetaModel);
        $dBMock->expects($this->atLeastOnce())
            ->method('store');
        $di['db'] = $dBMock;

        $this->service->setDi($di);
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $result = $this->service->updateOrderMeta($clientOrder, $meta);
        $this->assertEquals(2, $result);
    }
}
