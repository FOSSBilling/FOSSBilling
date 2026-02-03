<?php

declare(strict_types=1);

namespace Box\Mod\Order;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

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

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;

    public function setUp(): void
    {
        $this->service = new Service();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testCounter(): void
    {
        $counter = [
            \Model_ClientOrder::STATUS_ACTIVE => 1,
        ];
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn($counter);

        $di = $this->getDi();
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
            'id' => 1,
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

        $emailServiceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $orderArr = [
            'id' => 1,
            'client' => [
                'id' => 1,
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['logger'] = $this->createMock('Box_Log');
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
            'id' => 1,
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

        $emailServiceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = [
            'id' => 1,
            'client' => [
                'id' => 1,
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['logger'] = $this->createMock('Box_Log');
        $di['logger'] = $this->createMock('Box_Log');
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
            'id' => 1,
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

        $emailServiceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $orderArr = [
            'id' => 1,
            'client' => [
                'id' => 1,
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['logger'] = $this->createMock('Box_Log');
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
            'id' => 1,
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

        $emailServiceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = [
            'id' => 1,
            'client' => [
                'id' => 1,
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['logger'] = $this->createMock('Box_Log');
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
            'id' => 1,
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

        $emailServiceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $orderArr = [
            'id' => 1,
            'client' => [
                'id' => 1,
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['logger'] = $this->createMock('Box_Log');
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
            'id' => 1,
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

        $emailServiceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = [
            'id' => 1,
            'client' => [
                'id' => 1,
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['logger'] = $this->createMock('Box_Log');
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
            'id' => 1,
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

        $emailServiceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $orderArr = [
            'id' => 1,
            'client' => [
                'id' => 1,
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['logger'] = $this->createMock('Box_Log');
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
            'id' => 1,
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

        $emailServiceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = [
            'id' => 1,
            'client' => [
                'id' => 1,
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['logger'] = $this->createMock('Box_Log');
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
            'id' => 1,
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

        $emailServiceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $orderArr = [
            'id' => 1,
            'client' => [
                'id' => 1,
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['logger'] = $this->createMock('Box_Log');
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
            'id' => 1,
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

        $emailServiceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = [
            'id' => 1,
            'client' => [
                'id' => 1,
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['logger'] = $this->createMock('Box_Log');
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
            'id' => 1,
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

        $emailServiceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $orderArr = [
            'id' => 1,
            'client' => [
                'id' => 1,
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['logger'] = $this->createMock('Box_Log');
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
            'id' => 1,
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

        $emailServiceMock = $this->getMockBuilder(\Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willThrowException(new \Exception('PHPUnit controlled exception'));

        $orderArr = [
            'id' => 1,
            'client' => [
                'id' => 1,
            ],
            'service_type' => 'domain',
        ];

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getOrderServiceData', 'toApiArray'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getOrderServiceData')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($orderArr);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['loggedin_admin'] = $admin;
        $di['logger'] = $this->createMock('Box_Log');
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
        $customEntity = new \FOSSBilling\ProductType\Custom\Entity\Custom(1);
        $reflectionClass = new \ReflectionClass($customEntity);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($customEntity, 1);

        $customHandlerMock = $this->getMockBuilder(\FOSSBilling\ProductType\Custom\CustomHandler::class)
            ->onlyMethods(['loadEntity'])->getMock();
        $customHandlerMock->expects($this->once())->method('loadEntity')
            ->with(1)
            ->willReturn($customEntity);

        $registry = new \FOSSBilling\ProductTypeRegistry();
        $registry->registerDefinition([
            'code' => 'custom',
            'label' => 'Custom',
            'handler' => $customHandlerMock,
            'templates' => [
                'html_admin' => null,
                'html_client' => null,
            ],
        ]);

        $di = $this->getDi();
        $di['product_type_registry'] = $registry;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->service_id = 1;
        $order->service_type = 'custom';

        $result = $this->service->getOrderService($order);
        $this->assertInstanceOf(\FOSSBilling\ProductType\Custom\Entity\Custom::class, $result);
    }

    public function testGetOrderNotCoreService(): void
    {
        $customEntity = new \FOSSBilling\ProductType\Custom\Entity\Custom(1);
        $reflectionClass = new \ReflectionClass($customEntity);
        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setValue($customEntity, 1);

        $customHandlerMock = $this->getMockBuilder(\FOSSBilling\ProductType\Custom\CustomHandler::class)
            ->onlyMethods(['loadEntity'])->getMock();
        $customHandlerMock->expects($this->once())->method('loadEntity')
            ->with(1)
            ->willReturn($customEntity);

        $registry = new \FOSSBilling\ProductTypeRegistry();
        $registry->registerDefinition([
            'code' => 'custom',
            'label' => 'Custom',
            'handler' => $customHandlerMock,
            'templates' => [
                'html_admin' => null,
                'html_client' => null,
            ],
        ]);

        $di = $this->getDi();
        $di['product_type_registry'] = $registry;
        $di['logger'] = $this->createMock('Box_Log');
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->service_id = 1;
        $order->service_type = 'custom';

        $result = $this->service->getOrderService($order);
        $this->assertInstanceOf(\FOSSBilling\ProductType\Custom\Entity\Custom::class, $result);
    }

    public function testGetOrderServiceIdNotSet(): void
    {
        $service = new \Model_ExtProductCustom();

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('getExistingModelById')
            ->willReturn($service);
        $dbMock->expects($this->never())
            ->method('findOne')
            ->willReturn($service);

        $di = $this->getDi();
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

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('from_camel_case')
            ->willReturn('servicecustom');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);

        $service = new \FOSSBilling\ProductType\Custom\Entity\Custom(1);
        $reflection = new \ReflectionProperty($service, 'id');
        $reflection->setValue($service, 1);

        $result = $this->service->getServiceOrder($service);
        $this->assertInstanceOf('Model_ClientOrder', $result);
    }

    public function testGetConfig(): void
    {
        $decoded = [
            'key' => 'value',
        ];

        $di = $this->getDi();
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

    #[DataProvider('productHasOrdersProvider')]
    public function testProductHasOrders(?\Model_ClientOrder $order, bool $expectedResult): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($order);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());
        $product->id = 1;

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
            ->willReturn(1);

        $di = $this->getDi();
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

        $serviceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getSoonExpiringActiveOrdersQuery'])->disableOriginalConstructor()->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSoonExpiringActiveOrdersQuery')
            ->willReturn(['query', []]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $serviceMock->getSoonExpiringActiveOrders();
    }

    public function testGetSoonExpiringActiveOrdersQuery(): void
    {
        $randId = 1;

        $orderStatus = new \Model_ClientOrderStatus();
        $orderStatus->loadBean(new \DummyBean());

        $systemService = $this->getMockBuilder(\Box\Mod\System\Service::class)
            ->onlyMethods(['getParamValue'])->getMock();
        $systemService->expects($this->atLeastOnce())->method('getParamValue')
            ->willReturn($randId);

        $di = $this->getDi();
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

    public function testGetRelatedOrderIdByType(): void
    {
        $id = 1;
        $model = new \Model_ClientOrder();
        $model->loadBean(new \DummyBean());
        $model->id = $id;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrder')
            ->willReturn($model);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getRelatedOrderIdByType($model, 'domain');
        $this->assertIsInt($result);
        $this->assertEquals($id, $result);
    }

    public function testGetRelatedOrderIdByTypeReturnNull(): void
    {
        $id = 1;
        $model = new \Model_ClientOrder();
        $model->loadBean(new \DummyBean());
        $model->id = $id;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrder')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getRelatedOrderIdByType($model, 'domain');
        $this->assertNull($result);
    }

    public function testGetLogger(): void
    {
        $model = new \Model_ClientOrder();
        $model->loadBean(new \DummyBean());

        $loggerMock = $this->getMockBuilder('\Box_Log')->disableOriginalConstructor()->getMock();
        $loggerMock->expects($this->atLeastOnce())
            ->method('addWriter');

        $setEventItemExpected = ['client_order_id', 'status'];
        $matcher = $this->atLeastOnce();
        $loggerMock->expects($this->exactly(2))
            ->method('setEventItem')
            ->willReturnCallback(function (...$args) use ($matcher, $loggerMock) {
                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertEquals($args[0], 'client_order_id');
                } elseif ($matcher->numberOfInvocations() === 2) {
                    $this->assertEquals($args[0], 'status');
                }

                return $loggerMock;
            });

        $di = $this->getDi();
        $di['logger'] = $loggerMock;
        $this->service->setDi($di);

        $result = $this->service->getLogger($model);
        $this->assertInstanceOf('\Box_Log', $result);
    }

    public function testToApiArray(): void
    {
        $model = new \Model_ClientOrder();
        $model->loadBean(new \DummyBean());
        $model->config = '{}';
        $model->price = 10;
        $model->quantity = 1;
        $model->client_id = 1;

        $clientService = $this->createMock(\Box\Mod\Client\Service::class);
        $clientService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $supportService = $this->createMock(\Box\Mod\Support\Service::class);
        $supportService->expects($this->atLeastOnce())
            ->method('getActiveTicketsCountForOrder')
            ->willReturn(1);

        $dbMock = $this->createMock(\Box_Database::class);
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

        $di = $this->getDi();
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

    #[DataProvider('searchQueryData')]
    public function testGetSearchQuery(array $data, string $expectedStr, array $expectedParams): void
    {
        $di = $this->getDi();

        $this->service->setDi($di);

        $result = $this->service->getSearchQuery($data);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertTrue(str_contains($result[0], $expectedStr), $result[0]);
        $this->assertEquals([], array_diff_key($result[1], $expectedParams));
    }

    public function testCreateOrderMissingOrderCurrency(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());

        $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyRepositoryMock->expects($this->atLeastOnce())
            ->method('findDefault')
            ->willReturn(null);

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getCurrencyRepository'])
            ->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($currencyRepositoryMock);

        $di = $this->getDi();
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

    public function testCreateOrderOutOfStock(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->currency = 'USD';

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());
        $modelProduct->id = 1;

        $currencyModel = $this->getMockBuilder('\\' . \Box\Mod\Currency\Entity\Currency::class)->disableOriginalConstructor()->getMock();

        $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyRepositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn($currencyModel);

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getCurrencyRepository'])
            ->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($currencyRepositoryMock);

        $cartServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)->getMock();
        $cartServiceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->with($modelProduct)
            ->willReturn(false);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = $this->getDi();
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

    public function testCreateOrderGroupIdMissing(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->currency = 'USD';

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());
        $modelProduct->id = 1;
        $modelProduct->is_addon = 1;

        $currencyModel = $this->getMockBuilder(\Box\Mod\Currency\Entity\Currency::class)->disableOriginalConstructor()->getMock();

        $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyRepositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn($currencyModel);

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getCurrencyRepository'])
            ->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($currencyRepositoryMock);

        $cartServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)->getMock();
        $cartServiceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->with($modelProduct)
            ->willReturn(true);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = $this->getDi();
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

    public function testCreateOrderParentOrderNotFound(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->currency = 'USD';

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());
        $modelProduct->id = 1;

        $currencyModel = $this->getMockBuilder(\Box\Mod\Currency\Entity\Currency::class)->disableOriginalConstructor()->getMock();

        $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyRepositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn($currencyModel);

        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getCurrencyRepository'])
            ->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($currencyRepositoryMock);

        $cartServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)->getMock();
        $cartServiceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->with($modelProduct)
            ->willReturn(true);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock) {
            if ($serviceName == 'currency') {
                return $currencyServiceMock;
            }
            if ($serviceName == 'cart') {
                return $cartServiceMock;
            }
        });

        $di['events_manager'] = $eventMock;

        $serviceMock = $this->getMockBuilder(Service::class)
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

    public function testCreateOrder(): void
    {
        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->currency = 'USD';

        $modelProduct = new \Model_Product();
        $modelProduct->loadBean(new \DummyBean());
        $modelProduct->id = 1;
        $modelProduct->type = 'custom';

        $currencyModel = $this->getMockBuilder(\Box\Mod\Currency\Entity\Currency::class)->disableOriginalConstructor()->getMock();
        $currencyRepositoryMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $currencyRepositoryMock->expects($this->atLeastOnce())
            ->method('findOneByCode')
            ->willReturn($currencyModel);
        $currencyServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Currency\Service::class)
            ->onlyMethods(['getCurrencyRepository'])
            ->getMock();
        $currencyServiceMock->expects($this->atLeastOnce())
            ->method('getCurrencyRepository')
            ->willReturn($currencyRepositoryMock);
        $cartServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Cart\Service::class)->getMock();
        $cartServiceMock->expects($this->atLeastOnce())
            ->method('isStockAvailable')
            ->with($modelProduct)
            ->willReturn(true);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $productServiceMock = $this->createMock(\FOSSBilling\ProductType\Custom\CustomHandler::class);

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
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

        $di = $this->getDi();

        $productTypeRegistryMock = $this->createMock(\FOSSBilling\ProductTypeRegistry::class);
        $productTypeRegistryMock->expects($this->atLeastOnce())
            ->method('getHandler')
            ->with('custom')
            ->willReturn($productServiceMock);
        $di['product_type_registry'] = $productTypeRegistryMock;

        $di['mod_service'] = $di->protect(function ($serviceName) use ($currencyServiceMock, $cartServiceMock) {
            if ($serviceName == 'currency') {
                return $currencyServiceMock;
            }
            if ($serviceName == 'cart') {
                return $cartServiceMock;
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

    public function testGetMasterOrderForClient(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrder')
            ->willReturn($clientOrderModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);
        $result = $this->service->getMasterOrderForClient($clientModel, 1);
        $this->assertInstanceOf('Model_ClientOrder', $result);
    }

    public function testActivateOrderExceptionPendingOrFailedOrders(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_CANCELED;
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Only pending setup or failed orders can be activated');
        $this->service->activateOrder($clientOrderModel);
    }

    public function testActivateOrder(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_PENDING_SETUP;
        $clientOrderModel->group_master = 1;

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();

        $serviceMock = $this->getMockBuilder(Service::class)
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

    public function testActivateOrderAddons(): void
    {
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(Service::class)
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

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->activateOrderAddons($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testGetOrderAddonsList(): void
    {
        $modelClientOrder = new \Model_ClientOrder();
        $modelClientOrder->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('ClientOrder')
            ->willReturn([new \Model_ClientOrder()]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getOrderAddonsList($modelClientOrder);
        $this->assertIsArray($result);
        $this->assertInstanceOf('\Model_ClientOrder', $result[0]);
    }

    public function testStockSale(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->stock_control = 1;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($productModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->stockSale($productModel, 2);
        $this->assertTrue($result);
    }

    public function testUpdateOrder(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($clientOrderModel);

        $di = $this->getDi();
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

        $serviceMock = $this->getMockBuilder(Service::class)
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

    public function testRenewOrder(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->group_master = 1;
        $clientOrderModel->status = \Model_ClientOrder::STATUS_PENDING_SETUP;

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();

        $serviceMock = $this->getMockBuilder(Service::class)
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

    public function testRenewFromOrder(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['_callOnService', 'saveStatusChange'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('_callOnService');
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->period = '1Y';

        $periodMock = $this->getMockBuilder('\Box_Period')->disableOriginalConstructor()->getMock();

        $dbMock = $this->createMock('Box_Database');

        $invoiceServiceMock = $this->createMock(\Box\Mod\Invoice\Service::class);
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('findPaidInvoicesForOrder');

        $di = $this->getDi();
        $di['mod_config'] = $di->protect(fn ($name): array => []);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $invoiceServiceMock);
        $di['period'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $periodMock);
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $serviceMock->renewFromOrder($clientOrderModel);
    }

    public function testSuspendFromOrderExceptionNotActiveOrder(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_SUSPENDED;

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Only active orders can be suspended');
        $this->service->suspendFromOrder($clientOrderModel);
    }

    public function testSuspendFromOrder(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_ACTIVE;

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($clientOrderModel);

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $serviceMock = $this->getMockBuilder(Service::class)
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

    public function testRmByClient(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $queryBuilderMock = new class {
            private bool $deleteCalled = false;
            private bool $whereCalled = false;
            private bool $setParamCalled = false;
            private mixed $deleteTable = null;
            private mixed $whereCond = null;
            private mixed $paramId = null;

            public function delete($table)
            {
                $this->deleteCalled = true;
                $this->deleteTable = $table;

                return $this;
            }

            public function where($cond)
            {
                $this->whereCalled = true;
                $this->whereCond = $cond;

                return $this;
            }

            public function setParameter($key, $val)
            {
                $this->setParamCalled = true;
                $this->paramId = $val;

                return $this;
            }

            public function executeStatement(): int
            {
                return 1;
            }

            public function getDeleteTable()
            {
                return $this->deleteTable;
            }

            public function getWhereCond()
            {
                return $this->whereCond;
            }

            public function getParamId()
            {
                return $this->paramId;
            }

            public function wasDeleteCalled(): bool
            {
                return $this->deleteCalled;
            }

            public function wasWhereCalled(): bool
            {
                return $this->whereCalled;
            }

            public function wasSetParamCalled(): bool
            {
                return $this->setParamCalled;
            }
        };

        $dbalMock = new class($queryBuilderMock) {
            public function __construct(private $qb)
            {
            }

            public function createQueryBuilder()
            {
                return $this->qb;
            }
        };

        $di = new \Pimple\Container();
        $di['dbal'] = $dbalMock;
        $this->service->setDi($di);
        $this->service->rmByClient($clientModel);

        $this->assertSame('client_order', $queryBuilderMock->getDeleteTable());
        $this->assertSame('client_id = :id', $queryBuilderMock->getWhereCond());
        $this->assertSame($clientModel->id, $queryBuilderMock->getParamId());
    }

    public function testUpdatePeriod(): void
    {
        $period = '1Y';
        $di = $this->getDi();

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

    public function testUpdatePeriodIsEmpty(): void
    {
        $period = '';
        $di = $this->getDi();
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

    public function testUpdatePeriodNotSet(): void
    {
        $period = null;
        $di = $this->getDi();
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

    public function testUpdateOrderMetaIsNotSet(): void
    {
        $meta = null;
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $result = $this->service->updateOrderMeta($clientOrder, $meta);
        $this->assertEquals(0, $result);
    }

    public function testUpdateOrderMetaIsEmpty(): void
    {
        $meta = [];
        $di = $this->getDi();

        $dBMock = $this->createMock('\Box_Database');
        $dBMock->expects($this->atLeastOnce())
            ->method('exec');
        $di['db'] = $dBMock;

        $this->service->setDi($di);
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $result = $this->service->updateOrderMeta($clientOrder, $meta);
        $this->assertEquals(1, $result);
    }

    public function testUpdateOrderMeta(): void
    {
        $meta = [
            'key' => 'value',
        ];
        $di = $this->getDi();

        $dBMock = $this->createMock('\Box_Database');
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
