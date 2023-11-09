<?php

namespace Box\Tests\Mod\Support;

use \RedBeanPHP\OODBBean;

class ServiceTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Support\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Support\Service();
    }

    public function testDi()
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testOnAfterClientOpenTicket()
    {
        $toApiArrayReturn   = array(
            'client' => array(
                'id' => random_int(1, 100)
            )
        );
        $serviceMock        = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('getTicketById', 'toApiArray'))->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getTicketById')
            ->will($this->returnValue($supportTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                    = new \Pimple\Container();
        $di['mod_service']     = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ('email' == $serviceName) {
                return $emailServiceMock;
            }
            if ('support' == $serviceName) {
                return $serviceMock;
            }
        });
        $di['loggedin_client'] = new \Model_Client();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $result = $serviceMock->onAfterClientOpenTicket($eventMock);
        $this->assertNull($result);
    }

    public function testOnAfterAdminOpenTicket()
    {
        $toApiArrayReturn   = array(
            'client' => array(
                'id' => random_int(1, 100)
            )
        );
        $serviceMock        = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('getTicketById', 'toApiArray'))->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getTicketById')
            ->will($this->returnValue($supportTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                   = new \Pimple\Container();
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ('email' == $serviceName) {
                return $emailServiceMock;
            }
            if ('support' == $serviceName) {
                return $serviceMock;
            }
        });
        $di['loggedin_admin'] = new \Model_Admin();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $result = $serviceMock->onAfterAdminOpenTicket($eventMock);
        $this->assertNull($result);
    }

    public function testOnAfterAdminCloseTicket()
    {
        $toApiArrayReturn   = array(
            'client' => array(
                'id' => random_int(1, 100)
            )
        );
        $serviceMock        = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('getTicketById', 'toApiArray'))->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getTicketById')
            ->will($this->returnValue($supportTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                   = new \Pimple\Container();
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ('email' == $serviceName) {
                return $emailServiceMock;
            }
            if ('support' == $serviceName) {
                return $serviceMock;
            }
        });
        $di['loggedin_admin'] = new \Model_Admin();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $result = $serviceMock->onAfterAdminCloseTicket($eventMock);
        $this->assertNull($result);
    }

    public function testOnAfterAdminReplyTicket()
    {
        $toApiArrayReturn   = array(
            'client' => array(
                'id' => random_int(1, 100)
            )
        );
        $serviceMock        = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('getTicketById', 'toApiArray'))->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getTicketById')
            ->will($this->returnValue($supportTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                   = new \Pimple\Container();
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ('email' == $serviceName) {
                return $emailServiceMock;
            }
            if ('support' == $serviceName) {
                return $serviceMock;
            }
        });
        $di['loggedin_admin'] = new \Model_Admin();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $result = $serviceMock->onAfterAdminReplyTicket($eventMock);
        $this->assertNull($result);
    }

    public function testOnAfterGuestPublicTicketOpen()
    {
        $toApiArrayReturn    = array(
            'author_email' => 'email@example.com',
            'author_name'  => 'Name',
        );
        $serviceMock         = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('getPublicTicketById', 'publicToApiArray'))->getMock();
        $supportPTicketModel = new \Model_SupportPTicket();
        $supportPTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getPublicTicketById')
            ->will($this->returnValue($supportPTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ('email' == $serviceName) {
                return $emailServiceMock;
            }
            if ('support' == $serviceName) {
                return $serviceMock;
            }
        });
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $result = $serviceMock->onAfterGuestPublicTicketOpen($eventMock);
        $this->assertNull($result);
    }

    public function testOnAfterAdminPublicTicketOpen()
    {
        $toApiArrayReturn    = array(
            'author_email' => 'email@example.com',
            'author_name'  => 'Name',
        );
        $serviceMock         = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('getPublicTicketById', 'publicToApiArray'))->getMock();
        $supportPTicketModel = new \Model_SupportPTicket();
        $supportPTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getPublicTicketById')
            ->will($this->returnValue($supportPTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                   = new \Pimple\Container();
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ('email' == $serviceName) {
                return $emailServiceMock;
            }
            if ('support' == $serviceName) {
                return $serviceMock;
            }
        });
        $di['loggedin_admin'] = new \Model_Admin();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $result = $serviceMock->onAfterAdminPublicTicketOpen($eventMock);
        $this->assertNull($result);
    }

    public function testOnAfterAdminPublicTicketReply()
    {
        $toApiArrayReturn    = array(
            'author_email' => 'email@example.com',
            'author_name'  => 'Name',
        );
        $serviceMock         = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('getPublicTicketById', 'publicToApiArray'))->getMock();
        $supportPTicketModel = new \Model_SupportPTicket();
        $supportPTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getPublicTicketById')
            ->will($this->returnValue($supportPTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                   = new \Pimple\Container();
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ('email' == $serviceName) {
                return $emailServiceMock;
            }
            if ('support' == $serviceName) {
                return $serviceMock;
            }
        });
        $di['loggedin_admin'] = new \Model_Admin();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $result = $serviceMock->onAfterAdminPublicTicketReply($eventMock);
        $this->assertNull($result);
    }


    public function testOnAfterAdminPublicTicketClose()
    {
        $toApiArrayReturn    = array(
            'author_email' => 'email@example.com',
            'author_name'  => 'Name',
        );
        $serviceMock         = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('getPublicTicketById', 'publicToApiArray'))->getMock();
        $supportPTicketModel = new \Model_SupportPTicket();
        $supportPTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getPublicTicketById')
            ->will($this->returnValue($supportPTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                   = new \Pimple\Container();
        $di['mod_service']    = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ('email' == $serviceName) {
                return $emailServiceMock;
            }
            if ('support' == $serviceName) {
                return $serviceMock;
            }
        });
        $di['loggedin_admin'] = new \Model_Admin();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->will($this->returnValue($di));

        $result = $serviceMock->onAfterAdminPublicTicketClose($eventMock);
        $this->assertNull($result);
    }

    public function testGetTicketById()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getTicketById(random_int(1, 100));
        $this->assertInstanceOf('Model_SupportTicket', $result);
    }

    public function testGetPublicTicketById()
    {
        $dbMock             = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($supportTicketModel));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getPublicTicketById(random_int(1, 100));
        $this->assertInstanceOf('Model_SupportTicket', $result);
    }

    public function testGetStatuses()
    {
        $result = $this->service->getStatuses();
        $this->assertIsArray($result);
    }

    public function testFindOneByClient()
    {
        $dbMock             = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($supportTicketModel));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $result = $this->service->findOneByClient($client, random_int(1, 100));
        $this->assertInstanceOf('Model_SupportTicket', $result);
    }

    public function testFindOneByClientNotFoundException()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->findOneByClient($client, random_int(1, 100));
        $this->assertInstanceOf('Model_SupportTicket', $result);
    }

    public static function getSearchQueryProvider()
    {
        return array(
            array(
                array(
                    'search'              => 'query',
                    'id'                  => random_int(1, 100),
                    'status'              => 'open',
                    'client_id'           => random_int(1, 100),
                    'client'              => 'Client name',
                    'order_id'            => random_int(1, 100),
                    'subject'             => 'subject',
                    'content'             => 'Content',
                    'support_helpdesk_id' => random_int(1, 100),
                    'created_at'          => date('Y-m-d H:i:s'),
                    'date_from'           => date('Y-m-d H:i:s'),
                    'date_to'             => date('Y-m-d H:i:s'),
                    'priority'            => random_int(1, 100),
                )
            ),
            array(
                array(
                    'search'              => random_int(1, 100),
                    'id'                  => random_int(1, 100),
                    'status'              => 'open',
                    'client_id'           => random_int(1, 100),
                    'client'              => 'Client name',
                    'order_id'            => random_int(1, 100),
                    'subject'             => 'subject',
                    'content'             => 'Content',
                    'support_helpdesk_id' => random_int(1, 100),
                    'created_at'          => date('Y-m-d H:i:s'),
                    'date_from'           => date('Y-m-d H:i:s'),
                    'date_to'             => date('Y-m-d H:i:s'),
                    'priority'            => random_int(1, 100),
                )
            )
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getSearchQueryProvider')]
    public function testGetSearchQuery($data)
    {
        $di              = new \Pimple\Container();

        $this->service->setDi($di);
        [$query, $bindings] = $this->service->getSearchQuery($data);
        $this->assertIsString($query);
        $this->assertIsArray($bindings);
    }

    public function testCounter()
    {
        $arr    = array(
            \Model_SupportTicket::OPENED => random_int(1, 100),
            \Model_SupportTicket::ONHOLD => random_int(1, 100),
            \Model_SupportTicket::CLOSED => random_int(1, 100),
        );
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue($arr));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->counter();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(array_sum($arr), $result['total']);
    }

    public function testGetLatest()
    {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($ticket, $ticket)));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getLatest();
        $this->assertIsArray($result);
        $this->assertInstanceOf('Model_SupportTicket', $result[0]);
    }

    public function testGetExpired()
    {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue(array(array('id' => 1))));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getExpired();
        $this->assertIsArray($result);
        $this->assertIsArray($result[0]);
    }

    public function testCountByStatus()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(random_int(1, 100)));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->countByStatus('open');
        $this->assertIsInt($result);
    }

    public function testGetActiveTicketsCountForOrder()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(random_int(1, 100)));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $result = $this->service->getActiveTicketsCountForOrder($order);
        $this->assertIsInt($result);
    }

    public function testCheckIfTaskAlreadyExistsTrue()
    {
        $dbMock             = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($supportTicketModel));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $result = $this->service->checkIfTaskAlreadyExists($client, random_int(1, 100), random_int(1, 100), random_int(1, 100));
        $this->assertTrue($result);
    }

    public function testCheckIfTaskAlreadyExistsFalse()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(false));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $result = $this->service->checkIfTaskAlreadyExists($client, random_int(1, 100), random_int(1, 100), 'Task');
        $this->assertFalse($result);
    }

    public static function closeTicketProvider()
    {
        return array(
            array(new \Model_Admin()),
            array(new \Model_Client())
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('closeTicketProvider')]
    public function testCloseTicket($identity)
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(random_int(1, 100)));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di                   = new \Pimple\Container();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->closeTicket($ticket, $identity);
        $this->assertTrue($result);
    }

    public function testAutoClose()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(random_int(1, 100)));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->autoClose($ticket);
        $this->assertTrue($result);
    }

    public function testCanBeReopenedNotClosed()
    {
        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('getExistingModelById')
            ->will($this->returnValue($helpdesk));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->canBeReopened($ticket);
        $this->assertTrue($result);
    }

    public function testCanBeReopened()
    {
        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());
        $helpdesk->support_helpdesk_id = random_int(1, 100);
        $helpdesk->can_reopen          = true;

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($helpdesk));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->status = \Model_SupportTicket::CLOSED;

        $result = $this->service->canBeReopened($ticket);
        $this->assertTrue($result);
    }

    public function testRmByClient()
    {
        $model = new \Model_SupportTicket();
        $model->loadBean(new \DummyBean());
        $model->id = random_int(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($model)));
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());


        $result = $this->service->rmByClient($client);
        $this->assertNull($result);
    }

    public function testRm()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(function (...$args) {
                $value = match($args[0]) {
                    'SupportTicketNote' => new \Model_SupportTicketNote(),
                    'SupportTicketMessage' => new \Model_SupportTicketMessage()
                };

                return $value;
            });

        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->rm($ticket);
        $this->assertTrue($result);
    }

    public function testToApiArray()
    {
        $supportTicketMessageModel = new \Model_SupportTicketMessage();
        $supportTicketMessageModel->loadBean(new \DummyBean());
        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($supportTicketMessageModel));
        $dbMock->expects($this->atleastOnce())
            ->method('load')
            ->willReturnCallback(function (...$args) use ($helpdesk) {
                $value = match($args[0]) {
                    'SupportHelpdesk' => $helpdesk,
                    'Client' => new \Model_Client()
                };

                return $value;
            });

        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array(new \Model_SupportTicketNote())));

        $ticketMessages = array(new \Model_SupportTicketMessage(), new \Model_SupportTicketMessage());
        $serviceMock    = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('messageGetRepliesCount', 'messageToApiArray', 'helpdeskToApiArray', 'messageGetTicketMessages', 'noteToApiArray', 'getClientApiArrayForTicket'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('messageGetRepliesCount')
            ->will($this->returnValue(random_int(1, 100)));
        $serviceMock->expects($this->atLeastOnce())->method('messageToApiArray')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskToApiArray')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('messageGetTicketMessages')
            ->will($this->returnValue($ticketMessages));
        $serviceMock->expects($this->atLeastOnce())->method('noteToApiArray')
            ->will($this->returnValue(null));
        $serviceMock->expects($this->atLeastOnce())->method('getClientApiArrayForTicket')
            ->will($this->returnValue(array()));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $serviceMock->toApiArray($ticket, true, new \Model_Admin());
        $this->assertIsArray($result);
        $this->assertArrayHasKey('replies', $result);
        $this->assertArrayHasKey('helpdesk', $result);
        $this->assertArrayHasKey('messages', $result);

        $this->assertEquals(count($result['messages']), count($ticketMessages));
    }

    public function testToApiArrayWithRelDetails()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(new \Model_SupportTicketMessage()));
        $dbMock->expects($this->atleastOnce())
            ->method('load')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    ['SupportHelpdesk', new \Model_SupportHelpdesk()],
                    ['Client', new \Model_Client()],
                ];

                [$expectedArgs, $return] = array_shift($series);

                return $return;
            });

        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array(new \Model_SupportTicketNote())));

        $ticketMessages = array(new \Model_SupportTicketMessage(), new \Model_SupportTicketMessage());
        $serviceMock    = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('messageGetRepliesCount', 'messageToApiArray', 'helpdeskToApiArray', 'messageGetTicketMessages', 'noteToApiArray', 'getClientApiArrayForTicket'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('messageGetRepliesCount')
            ->will($this->returnValue(random_int(1, 100)));
        $serviceMock->expects($this->atLeastOnce())->method('messageToApiArray')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskToApiArray')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('messageGetTicketMessages')
            ->will($this->returnValue($ticketMessages));
        $serviceMock->expects($this->atLeastOnce())->method('noteToApiArray')
            ->will($this->returnValue(null));
        $serviceMock->expects($this->atLeastOnce())->method('getClientApiArrayForTicket')
            ->will($this->returnValue(array()));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->rel_id   = random_int(1, 100);
        $ticket->rel_type = 'Type';

        $result = $serviceMock->toApiArray($ticket, true, new \Model_Admin());
        $this->assertIsArray($result);
        $this->assertArrayHasKey('replies', $result);
        $this->assertArrayHasKey('helpdesk', $result);
        $this->assertArrayHasKey('messages', $result);

        $this->assertEquals(count($result['messages']), count($ticketMessages));
    }

    public function testGetClientApiArrayForTicket()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue(new \Model_Client()));

        $clientServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)
            ->onlyMethods(array('toApiArray'))->getMock();
        $clientServiceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $di                = new \Pimple\Container();
        $di['db']          = $dbMock;
        $di['logger']      = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod_service'] = $di->protect(fn() => $clientServiceMock);
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->getClientApiArrayForTicket($ticket);

        $this->assertIsArray($result);
    }

    public function testGetClientApiArrayForTicketClientNotExists()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue(null));

        $clientServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)
            ->onlyMethods(array('toApiArray'))->getMock();
        $clientServiceMock->expects($this->never())->method('toApiArray')
            ->will($this->returnValue(array()));

        $di                = new \Pimple\Container();
        $di['db']          = $dbMock;
        $di['logger']      = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod_service'] = $di->protect(fn() => $clientServiceMock);
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->getClientApiArrayForTicket($ticket);

        $this->assertIsArray($result);
    }

    public function testNoteGetAuthorDetails()
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->name = 'AdminName';

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($admin));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \DummyBean());

        $this->service->noteGetAuthorDetails($note);
    }

    public function testNoteRm()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \DummyBean());

        $result = $this->service->noteRm($note);
        $this->assertTrue($result);
    }

    public function testNoteToApiArray()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('noteGetAuthorDetails'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('noteGetAuthorDetails')
            ->will($this->returnValue(array()));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $serviceMock->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \DummyBean());

        $result = $serviceMock->noteToApiArray($note);
        $this->assertArrayHasKey('author', $result);
        $this->assertIsArray($result['author']);
    }

    public function testHelpdeskGetSearchQuery()
    {
        $di              = new \Pimple\Container();

        $this->service->setDi($di);

        $data = array(
            'search' => 'SearchQuery'
        );
        [$query, $bindings] = $this->service->helpdeskGetSearchQuery($data);

        $expectedBindings = array(
            ':name'      => '%SearchQuery%',
            ':email'     => '%SearchQuery%',
            ':signature' => '%SearchQuery%',
        );

        $this->assertIsString($query);
        $this->assertIsArray($bindings);

        $this->assertEquals($expectedBindings, $bindings);
    }

    public function testHelpdeskGetPairs()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue(array(0 => 'General')));


        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $this->service->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \DummyBean());

        $result = $this->service->helpdeskGetPairs();
        $this->assertIsArray($result);
    }

    public function testHelpdeskRm()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array()));

        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());
        $helpdesk->id = random_int(1, 100);
        $result       = $this->service->helpdeskRm($helpdesk);
        $this->assertTrue($result);
    }

    public function testHelpdeskRmHAsTicketsException()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array(new \Model_SupportTicket())));

        $dbMock->expects($this->never())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());
        $helpdesk->id = random_int(1, 100);
        $this->expectException(\FOSSBilling\Exception::class);
        $result       = $this->service->helpdeskRm($helpdesk);
        $this->assertTrue($result);
    }

    public function testHelpdeskToApiArray()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());
        $helpdesk->id = random_int(1, 100);
        $result       = $this->service->helpdeskToApiArray($helpdesk);
        $this->assertIsArray($result);
    }

    public function testMessageGetTicketMessages()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array(new \Model_SupportTicketMessage())));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->id = random_int(1, 100);

        $result = $this->service->messageGetTicketMessages($ticket);
        $this->assertIsArray($result);
    }

    public function testMessageGetRepliesCount()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(random_int(1, 100)));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->id = random_int(1, 100);

        $result = $this->service->messageGetRepliesCount($ticket);
        $this->assertIsInt($result);
    }

    public function testMessageGetAuthorDetailsAdmin()
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->id = random_int(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($admin));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticketMsg = new \Model_SupportTicketMessage();
        $ticketMsg->loadBean(new \DummyBean());
        $ticketMsg->admin_id = random_int(1, 100);

        $result = $this->service->messageGetAuthorDetails($ticketMsg);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
    }

    public function testMessageGetAuthorDetailsClient()
    {
        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($client));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticketMsg = new \Model_SupportTicketMessage();
        $ticketMsg->loadBean(new \DummyBean());
        $ticketMsg->client_id = random_int(1, 100);

        $result = $this->service->messageGetAuthorDetails($ticketMsg);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
    }

    public function testMessageToApiArray()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('messageGetAuthorDetails'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('messageGetAuthorDetails')
            ->will($this->returnValue(array()));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $ticketMsg = new \Model_SupportTicketMessage();
        $ticketMsg->loadBean(new \DummyBean());
        $ticketMsg->id = random_int(1, 100);

        $result = $serviceMock->messageToApiArray($ticketMsg);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('author', $result);
    }

    public function testTicketUpdate()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(random_int(1, 100)));

        $di              = new \Pimple\Container();
        $di['db']        = $dbMock;
        $di['logger']    = $this->getMockBuilder('Box_Log')->getMock();

        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $data = array(
            'support_helpdesk_id' => random_int(1, 100),
            'status'              => \Model_SupportTicket::OPENED,
            'subject'             => 'Subject',
            'priority'            => random_int(1, 100),
        );

        $result = $this->service->ticketUpdate($ticket, $data);
        $this->assertTrue($result);
    }

    public function testTicketMessageUpdate()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(random_int(1, 100)));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $data = array(
            'support_helpdesk_id' => random_int(1, 100),
            'status'              => \Model_SupportTicket::OPENED,
            'subject'             => 'Subject',
            'priority'            => random_int(1, 100),
        );

        $result = $this->service->ticketMessageUpdate($message, $data);
        $this->assertTrue($result);
    }

    public static function ticketReplyProvider()
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->id = random_int(1, 100);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        return array(
            array($admin),
            array($client)
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ticketReplyProvider')]
    public function testTicketReply($identity)
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($message));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di                   = new \Pimple\Container();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $this->getMockBuilder('\\' . \FOSSBilling\Request::class)->getMock();
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());


        $result = $this->service->ticketReply($ticket, $identity, 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testTicketCreateForAdmin()
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($message));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di                   = new \Pimple\Container();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $this->getMockBuilder('\\' . \FOSSBilling\Request::class)->getMock();
        $di['events_manager'] = $eventMock;

        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->id = random_int(1, 100);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $data = array(
            'subject' => 'Subject',
            'content' => 'Content'
        );

        $result = $this->service->ticketCreateForAdmin($client, $helpdesk, $data, $admin);
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testTicketCreateForGuest()
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($message));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('fire')
            ->will($this->returnValue(array()));

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

        $di                   = new \Pimple\Container();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $this->getMockBuilder('\\' . \FOSSBilling\Request::class)->getMock();
        $di['events_manager'] = $eventMock;
        $di['tools']          = $toolsMock;

        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $data = array(
            'name'    => 'Name',
            'email'   => 'email@example.com',
            'subject' => 'Subject',
            'message' => 'message'
        );

        $result = $this->service->ticketCreateForGuest($data);
        $this->assertIsString($result);
        $this->assertGreaterThanOrEqual(200, strlen($result));
        $this->assertLessThanOrEqual(255, strlen($result));
    }

    public function testTicketCreateForClient()
    {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->with('SupportTicket')
            ->will($this->returnValue($ticket));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPr()));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $config         = array(
            'autorespond_enable'     => 1,
            'autorespond_message_id' => random_int(1, 100)
        );
        $supportModMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()
            ->onlyMethods(array('getConfig'))->getMock();
        $supportModMock->expects($this->atLeastOnce())->method('getConfig')
            ->will($this->returnValue($config));

        $staffServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)
            ->onlyMethods(array('getCronAdmin'))->getMock();
        $staffServiceMock->expects($this->atLeastOnce())->method('getCronAdmin')
            ->will($this->returnValue(new \Model_Admin()));

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('ticketReply', 'messageCreateForTicket', 'cannedToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketReply')
            ->will($this->returnValue(new \Model_Admin()));
        $serviceMock->expects($this->atLeastOnce())->method('messageCreateForTicket')
            ->will($this->returnValue(new \Model_Admin()));
        $serviceMock->expects($this->atLeastOnce())->method('cannedToApiArray')
            ->will($this->returnValue(array('content' => 'Content')));

        $di                   = new \Pimple\Container();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $this->getMockBuilder('\\' . \FOSSBilling\Request::class)->getMock();
        $di['events_manager'] = $eventMock;
        $di['mod']            = $di->protect(fn() => $supportModMock);
        $di['mod_service']    = $di->protect(fn() => $staffServiceMock);

        $serviceMock->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $guest     = new \Model_Guest();
        $guest->id = random_int(1, 100);

        $data = array(
            'name'    => 'Name',
            'email'   => 'email@example.com',
            'subject' => 'Subject',
            'content' => 'content'
        );

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $result = $serviceMock->ticketCreateForClient($client, $helpdesk, $data);
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testTicketCreateForClientTaskAlreadyExistsException()
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('checkIfTaskAlreadyExists'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('checkIfTaskAlreadyExists')
            ->will($this->returnValue(true));

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $guest     = new \Model_Guest();
        $guest->id = random_int(1, 100);

        $data = array(
            'rel_id'        => random_int(1, 100),
            'rel_type'      => 'Type',
            'rel_task'      => 'Task',
            'rel_new_value' => 'New value',
        );

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $di              = new \Pimple\Container();

        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $serviceMock->ticketCreateForClient($client, $helpdesk, $data);
    }

    public static function messageCreateForTicketProvider()
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->id = random_int(1, 100);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        return array(
            array(
                $admin
            ),
            array(
                $client
            ),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('messageCreateForTicketProvider')]
    public function testMessageCreateForTicket($identity)
    {
        $randId               = random_int(1, 100);
        $supportTicketMessage = new \Model_SupportTicketMessage();
        $supportTicketMessage->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($supportTicketMessage));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di            = new \Pimple\Container();
        $di['db']      = $dbMock;
        $di['logger']  = $this->getMockBuilder('Box_Log')->getMock();
        $di['request'] = $this->getMockBuilder('\\' . \FOSSBilling\Request::class)->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->messageCreateForTicket($ticket, $identity, 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testMessageCreateForTicketIdentityException()
    {
        $randId               = random_int(1, 100);
        $supportTicketMessage = new \Model_SupportTicketMessage();
        $supportTicketMessage->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($supportTicketMessage));
        $dbMock->expects($this->never())
            ->method('store')
            ->will($this->returnValue($randId));

        $di            = new \Pimple\Container();
        $di['db']      = $dbMock;
        $di['logger']  = $this->getMockBuilder('Box_Log')->getMock();
        $di['request'] = $this->getMockBuilder('B\FOSSBilling\Request')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->messageCreateForTicket($ticket, null, 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testPublicGetStatuses()
    {
        $result = $this->service->publicGetStatuses();
        $this->assertIsArray($result);
    }

    public function testPublicFindOneByHash()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(new \Model_SupportPTicket()));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $result = $this->service->publicFindOneByHash(sha1(uniqid()));
        $this->assertInstanceOf('Model_SupportPTicket', $result);
    }

    public function testPublicFindOneByHashNotFoundException()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->publicFindOneByHash(sha1(uniqid()));
        $this->assertInstanceOf('Model_SupportPTicket', $result);
    }

    public static function publicGetSearchQueryProvider()
    {
        return array(
            array(
                array(
                    'search'  => 'Query',
                    'id'      => random_int(1, 100),
                    'status'  => \Model_SupportPTicket::OPENED,
                    'name'    => 'Name',
                    'email'   => 'email@example.com',
                    'subject' => 'Subject',
                    'content' => 'Content',
                )
            ),
            array(
                array(
                    'search'  => random_int(1, 100),
                    'search'  => random_int(1, 100),
                    'id'      => random_int(1, 100),
                    'status'  => \Model_SupportPTicket::OPENED,
                    'name'    => 'Name',
                    'email'   => 'email@example.com',
                    'subject' => 'Subject',
                    'content' => 'Content',
                )
            ),
        );
    }


    #[\PHPUnit\Framework\Attributes\DataProvider('publicGetSearchQueryProvider')]
    public function testPublicGetSearchQuery($data)
    {
        $di              = new \Pimple\Container();

        $this->service->setDi($di);

        [$query, $bindings] = $this->service->publicgetSearchQuery($data);
        $this->assertIsString($query);
        $this->assertIsArray($bindings);
    }

    public function testPublicCounter()
    {
        $arr    = array(
            \Model_SupportPTicket::OPENED => random_int(1, 100),
            \Model_SupportPTicket::ONHOLD => random_int(1, 100),
            \Model_SupportPTicket::CLOSED => random_int(1, 100),
        );
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue($arr));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->publicCounter();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(array_sum($arr), $result['total']);
    }

    public function testPublicGetLatest()
    {
        $ticket = new \Model_SupportPTicket();
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($ticket, $ticket)));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->publicGetLatest();
        $this->assertIsArray($result);
        $this->assertInstanceOf('Model_SupportPTicket', $result[0]);
    }

    public function testPublicCountByStatus()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(random_int(1, 100)));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->publicCountByStatus('open');
        $this->assertIsInt($result);
    }

    public function testPublicGetExpired()
    {
        $ticket = new \Model_SupportPTicket();
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($ticket, $ticket)));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->publicGetExpired();
        $this->assertIsArray($result);
        $this->assertInstanceOf('Model_SupportPTicket', $result[0]);
    }

    public static function publicCloseTicketProvider()
    {
        return array(
            array(new \Model_Admin()),
            array(new \Model_Guest())
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicCloseTicketProvider')]
    public function testPublicCloseTicket($identity)
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(random_int(1, 100)));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di                   = new \Pimple\Container();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->publicCloseTicket($ticket, $identity);
        $this->assertTrue($result);
    }

    public function testPublicAutoClose()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(random_int(1, 100)));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->publicAutoClose($ticket);
        $this->assertTrue($result);
    }

    public function testPublicRm()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array(new \Model_SupportPTicketMessage())));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $canned = new \Model_SupportPTicket();
        $canned->loadBean(new \DummyBean());

        $result = $this->service->publicRm($canned);
        $this->assertTrue($result);
    }

    public static function publicToApiArrayProvider()
    {
        $self = new ServiceTest('ServiceTest');

        return array(
            array(
                new \Model_SupportPTicketMessage(),
                $self->atLeastOnce()
            ),
            array(
                null,
                $self->never()
            )
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicToApiArrayProvider')]
    public function testPublicToApiArray($findOne, $publicMessageGetAuthorDetailsCalled)
    {
        $ticketMessages = array(new \Model_SupportPTicketMessage(), new \Model_SupportPTicketMessage());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->any())
            ->method('findOne')
            ->will($this->returnValue($findOne));
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));
        $dbMock->expects($this->any())
            ->method('find')
            ->will($this->returnValue($ticketMessages));

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('publicMessageToApiArray', 'publicMessageGetAuthorDetails'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicMessageToApiArray')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('publicMessageGetAuthorDetails')
            ->will($this->returnValue(array('name' => 'Name', 'email' => 'email#example.com')));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $serviceMock->publicToApiArray($ticket, true);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('author', $result);
        $this->assertArrayHasKey('messages', $result);

        $this->assertEquals(count($result['messages']), count($ticketMessages));
    }

    public function testPublicMessageGetAuthorDetailsAdmin()
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->id = random_int(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($admin));


        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticketMsg = new \Model_SupportPTicketMessage();
        $ticketMsg->loadBean(new \DummyBean());
        $ticketMsg->admin_id = random_int(1, 100);

        $result = $this->service->publicMessageGetAuthorDetails($ticketMsg);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
    }

    public function testPublicMessageGetAuthorDetailsNotAdmin()
    {
        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->author_name  = "Name";
        $ticket->author_email = "Email@example.com";

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($ticket));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticketMsg = new \Model_SupportPTicketMessage();
        $ticketMsg->loadBean(new \DummyBean());
        $ticketMsg->admin_id = null;

        $result = $this->service->publicMessageGetAuthorDetails($ticketMsg);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
    }

    public function testPublicMessageToApiArray()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(array('publicMessageGetAuthorDetails'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicMessageGetAuthorDetails')
            ->will($this->returnValue(array()));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $ticketMsg = new \Model_SupportPTicketMessage();
        $ticketMsg->loadBean(new \DummyBean());
        $ticketMsg->id = random_int(1, 100);

        $result = $serviceMock->publicMessageToApiArray($ticketMsg);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('author', $result);
    }

    public function testPublicTicketCreate()
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($message));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

        $di                   = new \Pimple\Container();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $this->getMockBuilder('\\' . \FOSSBilling\Request::class)->getMock();
        $di['events_manager'] = $eventMock;
        $di['tools']          = $toolsMock;
        $this->service->setDi($di);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->id = random_int(1, 100);

        $data = array(
            'email'   => 'email@example.com',
            'name'    => 'Name',
            'message' => 'Message',
            'request' => 'Request',
            'subject' => 'Subject',
        );

        $result = $this->service->publicTicketCreate($data, $admin);
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testPublicTicketUpdate()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(random_int(1, 100)));

        $di              = new \Pimple\Container();
        $di['db']        = $dbMock;
        $di['logger']    = $this->getMockBuilder('Box_Log')->getMock();

        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());

        $data = array(
            'support_helpdesk_id' => random_int(1, 100),
            'status'              => \Model_SupportTicket::OPENED,
            'subject'             => 'Subject',
            'priority'            => random_int(1, 100),
        );

        $result = $this->service->publicTicketUpdate($ticket, $data);
        $this->assertTrue($result);
    }

    public function testPublicTicketReply()
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($message));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di                   = new \Pimple\Container();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $this->getMockBuilder('\\' . \FOSSBilling\Request::class)->getMock();
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $result = $this->service->publicTicketReply($ticket, $admin, 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testPublicTicketReplyForGuest()
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($message));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di                   = new \Pimple\Container();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $this->getMockBuilder('\\' . \FOSSBilling\Request::class)->getMock();
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->hash = sha1(uniqid());

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $result = $this->service->publicTicketReplyForGuest($ticket, 'Message');
        $this->assertIsString($result);
        $this->assertEquals(strlen($result), 40);
    }

    public function testHelpdeskUpdate()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(random_int(1, 100)));

        $di              = new \Pimple\Container();
        $di['db']        = $dbMock;
        $di['logger']    = $this->getMockBuilder('Box_Log')->getMock();

        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $data = array(
            'name'        => 'Name',
            'email'       => 'email@example.com',
            'can_reopen'  => 1,
            'close_after' => random_int(1, 100),
            'signature'   => 'Signature',
        );

        $result = $this->service->helpdeskUpdate($helpdesk, $data);
        $this->assertTrue($result);
    }

    public function testHelpdeskCreate()
    {
        $randId        = random_int(1, 100);
        $helpDeskModel = new \Model_SupportHelpdesk();
        $helpDeskModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($helpDeskModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di              = new \Pimple\Container();
        $di['db']        = $dbMock;
        $di['logger']    = $this->getMockBuilder('Box_Log')->getMock();

        $this->service->setDi($di);

        $ticket = new \Model_SupportHelpdesk();
        $ticket->loadBean(new \DummyBean());

        $data = array(
            'name'        => 'Name',
            'email'       => 'email@example.com',
            'can_reopen'  => 1,
            'close_after' => random_int(1, 100),
            'signature'   => 'Signature',
        );

        $result = $this->service->helpdeskCreate($data);
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testCannedGetSearchQuery()
    {
        $di              = new \Pimple\Container();

        $this->service->setDi($di);

        $data = array(
            'search' => 'query',
        );

        [$query, $bindings] = $this->service->cannedGetSearchQuery($data);
        $this->assertIsString($query);
        $this->assertIsArray($bindings);
    }

    public function testCannedGetGroupedPairs()
    {
        $pairs = array(
            0 => array(
                'id'      => 1,
                'r_title' => 'R  Title',
                'c_title' => 'General',
            )
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue($pairs));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $expected = array(
            'General' =>
            array(
                1 => 'R  Title',
            ),
        );

        $result = $this->service->cannedGetGroupedPairs();
        $this->assertIsArray($result);
        $this->assertEquals($result, $expected);
    }

    public function testCannedRm()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $canned = new \Model_SupportPr();
        $canned->loadBean(new \DummyBean());

        $result = $this->service->cannedRm($canned);
        $this->assertTrue($result);
    }

    public function testCannedToApiArray()
    {
        $category = new \Model_SupportPrCategory();
        $category->loadBean(new \DummyBean());
        $category->id    = random_int(1, 100);
        $category->title = 'General';

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($category));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);


        $canned = new \Model_SupportPr();
        $canned->loadBean(new \DummyBean());

        $result = $this->service->cannedToApiArray($canned);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('category', $result);
        $this->assertIsArray($result['category']);
        $this->assertArrayHasKey('id', $result['category']);
        $this->assertArrayHasKey('title', $result['category']);
    }

    public function testCannedToApiArrayCategotyNotFound()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue(null));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);


        $canned = new \Model_SupportPr();
        $canned->loadBean(new \DummyBean());

        $result = $this->service->cannedToApiArray($canned);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('category', $result);
        $this->assertEquals($result['category'], array());
    }

    public function testCannedCategoryGetPairs()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue(array(0 => 'General')));


        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $this->service->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \DummyBean());

        $result = $this->service->cannedCategoryGetPairs();
        $this->assertIsArray($result);
    }

    public function testCannedCategoryRm()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $canned = new \Model_SupportPrCategory();
        $canned->loadBean(new \DummyBean());

        $result = $this->service->cannedCategoryRm($canned);
        $this->assertTrue($result);
    }

    public function testCannedCategoryToApiArray()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $canned = new \Model_SupportPrCategory();
        $canned->loadBean(new \DummyBean());

        $result = $this->service->cannedCategoryToApiArray($canned);
        $this->assertIsArray($result);
    }

    public function testCannedCreate()
    {
        $randId        = random_int(1, 100);
        $helpDeskModel = new \Model_SupportHelpdesk();
        $helpDeskModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($helpDeskModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $settingsServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->addMethods(array('checkLimits'))->getMock();
        $settingsServiceMock->expects($this->atLeastOnce())->method('checkLimits')
            ->will($this->returnValue(null));

        $di                = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn() => $settingsServiceMock);
        $di['db']          = $dbMock;
        $di['logger']      = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportHelpdesk();
        $ticket->loadBean(new \DummyBean());

        $data = array(
            'name'        => 'Name',
            'email'       => 'email@example.com',
            'can_reopen'  => 1,
            'close_after' => random_int(1, 100),
            'signature'   => 'Signature',
        );

        $result = $this->service->cannedCreate($data, random_int(1, 100), 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testCannedUpdate()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(random_int(1, 100)));

        $di              = new \Pimple\Container();
        $di['db']        = $dbMock;
        $di['logger']    = $this->getMockBuilder('Box_Log')->getMock();

        $this->service->setDi($di);

        $model = new \Model_SupportPr();
        $model->loadBean(new \DummyBean());

        $data = array(
            'category_id' => random_int(1, 100),
            'title'       => 'email@example.com',
            'content'     => 1,
        );

        $result = $this->service->cannedUpdate($model, $data);
        $this->assertTrue($result);
    }

    public function testCannedCategoryCreate()
    {
        $randId                 = random_int(1, 100);
        $supportPrCategoryModel = new \Model_SupportPrCategory();
        $supportPrCategoryModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($supportPrCategoryModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $data = array(
            'name'        => 'Name',
            'email'       => 'email@example.com',
            'can_reopen'  => 1,
            'close_after' => random_int(1, 100),
            'signature'   => 'Signature',
        );

        $result = $this->service->cannedCategoryCreate($data);
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testCannedCategoryUpdate()
    {
        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $model = new \Model_SupportPrCategory();
        $model->loadBean(new \DummyBean());

        $result = $this->service->cannedCategoryUpdate($model, 'Title');
        $this->assertTrue($result);
    }

    public function testNoteCreate()
    {
        $randId                 = random_int(1, 100);
        $supportPrCategoryModel = new \Model_SupportPrCategory();
        $supportPrCategoryModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($supportPrCategoryModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $data = array(
            'name'        => 'Name',
            'email'       => 'email@example.com',
            'can_reopen'  => 1,
            'close_after' => random_int(1, 100),
            'signature'   => 'Signature',
        );

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->noteCreate($ticket, $admin, 'Note');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testTicketTaskComplete()
    {
        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $model = new \Model_SupportTicket();
        $model->loadBean(new \DummyBean());

        $result = $this->service->ticketTaskComplete($model);
        $this->assertTrue($result);
    }

    public static function canClientSubmitNewTicketProvider()
    {

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->client_id  = 5;
        $ticket->created_at = date('Y-m-d H:i:s');

        $ticket2 = new \Model_SupportTicket();
        $ticket2->loadBean(new \DummyBean());
        $ticket2->client_id  = 5;
        $ticket2->created_at = date('Y-m-d H:i:s', strtotime("-2 days"));;

        return array(
            array($ticket, 24, false), //Ticket is created today, exception should be thrown
            array(null, 24, true), //No previously created tickets found, can submit a ticket
            array($ticket2, 24, true) //Last ticket submitted 2 days ago, can submit a ticket
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('canClientSubmitNewTicketProvider')]
    public function testCanClientSubmitNewTicket($ticket, $hours, $expected)
    {
        if (!$expected) {
            $this->expectException(\FOSSBilling\Exception::class);
        }
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($ticket));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);
        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 5;

        $config = array('wait_hours' => $hours);

        $result = $this->service->canClientSubmitNewTicket($client, $config);
        $this->assertTrue($result);
    }

    /*
    * Knowledge Base Tests.
    */

    public function testKb_searchArticles()
    {
        $service = new \Box\Mod\Support\Service();

        $willReturn = array(
            "pages"    => 5,
            "page"     => 2,
            "per_page" => 2,
            "total"    => 10,
            "list"     => array(),
        );

        $di = new \Pimple\Container();

        $pager = $this->getMockBuilder('Box_Pagination')->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue($willReturn));


        $client      = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id  = 5;
        $di['pager'] = $pager;
        $service->setDi($di);

        $result = $service->kbSearchArticles('active', 'keyword', 'category');
        $this->assertIsArray($result);

        $this->assertArrayHasKey('list', $result);
        $this->assertIsArray($result['list']);

        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function testKb_findActiveArticleById()
    {
        $service = new \Box\Mod\Support\Service();

        $model = new \Model_SupportKbArticle();
        $model->loadBean(new \DummyBean());
        $db    = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbFindActiveArticleById(5);
        $this->assertInstanceOf('Model_SupportKbArticle', $result);
        $this->assertEquals($result, $model);
    }

    public function testKb_findActiveArticleBySlug()
    {
        $service = new \Box\Mod\Support\Service();

        $model = new \Model_SupportKbArticle();
        $db    = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbFindActiveArticleBySlug('slug');
        $this->assertInstanceOf('Model_SupportKbArticle', $result);
        $this->assertEquals($result, $model);
    }

    public function testKb_findActive()
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array()));
        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbFindActive();
        $this->assertIsArray($result);
    }

    public function testKb_hitView()
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(5));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $modelKb        = new \Model_SupportKbArticle();
        $modelKb->loadBean(new \DummyBean());
        $modelKb->views = 10;

        $result = $service->kbHitView($modelKb);
        $this->assertNull($result);
    }

    public function testKb_rm()
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Pimple\Container();
        $di['db']     = $db;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $modelKb        = new \Model_SupportKbArticle();
        $modelKb->loadBean(new \DummyBean());
        $modelKb->id    = 1;
        $modelKb->views = 10;


        $result = $service->kbRm($modelKb);
        $this->assertNull($result);
    }

    public static function kbToApiArrayProvider()
    {
        $model                         = new \Model_SupportKbArticle();
        $model->loadBean(new \DummyBean());
        $model->id                     = random_int(1, 100);
        $model->slug                   = 'article-slug';
        $model->title                  = "Title";
        $model->views                  = random_int(1, 100);
        $model->content                = 'Content';
        $model->created_at             = '2013-01-01 12:00:00';
        $model->updated_at             = '2014-01-01 12:00:00';
        $model->status                 = 'active';
        $model->kb_article_category_id = random_int(1, 100);

        $category        = new \Model_SupportKbArticleCategory();
        $category->loadBean(new \DummyBean());
        $category->id    = random_int(1, 100);
        $category->slug  = 'category-slug';
        $category->title = 'category-title';

        return array(
            array(
                $model,
                array(
                    'id'         => $model->id,
                    'slug'       => $model->slug,
                    'title'      => $model->title,
                    'views'      => $model->views,
                    'created_at' => $model->created_at,
                    'updated_at' => $model->updated_at,
                    'category'   => array(
                        'id'    => $category->id,
                        'slug'  => $category->slug,
                        'title' => $category->title,
                    ),
                    'status'                 => $model->status,
                ),
                false,
                null,
                $category
            ),
            array(
                $model,
                array(
                    'id'         => $model->id,
                    'slug'       => $model->slug,
                    'title'      => $model->title,
                    'views'      => $model->views,
                    'created_at' => $model->created_at,
                    'updated_at' => $model->updated_at,
                    'category'   => array(
                        'id'    => $category->id,
                        'slug'  => $category->slug,
                        'title' => $category->title,
                    ),
                    'content'    => $model->content,
                    'status'     => $model->status,
                ),
                true,
                null,
                $category
            ),
            array(
                $model,
                array(
                    'id'                     => $model->id,
                    'slug'                   => $model->slug,
                    'title'                  => $model->title,
                    'views'                  => $model->views,
                    'created_at'             => $model->created_at,
                    'updated_at'             => $model->updated_at,
                    'category'   => array(
                        'id'    => $category->id,
                        'slug'  => $category->slug,
                        'title' => $category->title,
                    ),
                    'content'                => $model->content,
                    'status'                 => $model->status,
                    'kb_article_category_id' => $model->kb_article_category_id
                ),
                true,
                new \Model_Admin(),
                $category
            ),

        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('kbToApiArrayProvider')]
    public function testKb_toApiArray($model, $expected, $deep, $identity, $category)
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($category));
        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result  = $service->kbToApiArray($model, $deep, $identity);
        $this->assertEquals($result, $expected);
    }

    public function testKb_createArticle()
    {
        $service = new \Box\Mod\Support\Service();
        $randId  = random_int(1, 100);
        $db      = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));
        $model = new \Model_SupportKbArticle();
        $model->loadBean(new \DummyBean());
        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($model));

        $tools = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->onlyMethods(array('slug'))->getMock();
        $tools->expects($this->atLeastOnce())
            ->method('slug')
            ->will($this->returnValue('article-slug'));

        $di           = new \Pimple\Container();
        $di['db']     = $db;
        $di['tools']  = $tools;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $service->setDi($di);
        $result = $service->kbCreateArticle(random_int(1, 100), 'Title', 'Active', 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testKb_updateArticle()
    {
        $service = new \Box\Mod\Support\Service();
        $randId  = random_int(1, 100);

        $model = new \Model_SupportKbArticle();
        $model->loadBean(new \DummyBean());

        $kb_article_category_id = random_int(1, 100);
        $title                  = 'Title';
        $slug                   = 'article-slug';
        $status                 = 'active';
        $content                = 'content';
        $views                  = random_int(1, 100);

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Pimple\Container();
        $di['db']     = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $service->setDi($di);
        $result = $service->kbUpdateArticle($randId, $kb_article_category_id, $title, $slug, $status, $content, $views);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testKb_updateArticleNotFoundException()
    {
        $service = new \Box\Mod\Support\Service();
        $randId  = random_int(1, 100);


        $kb_article_category_id = random_int(1, 100);
        $title                  = 'Title';
        $slug                   = 'article-slug';
        $status                 = 'active';
        $content                = 'content';
        $views                  = random_int(1, 100);

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(false));
        $db->expects($this->never())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Pimple\Container();
        $di['db']     = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $result = $service->kbUpdateArticle($randId, $kb_article_category_id, $title, $slug, $status, $content, $views);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public static function kbCategoryGetSearchQueryProvider()
    {
        return array(
            array(
                array(),
                '
                SELECT kac.*
                FROM support_kb_article_category kac
                LEFT JOIN support_kb_article ka ON kac.id  = ka.kb_article_category_id GROUP BY kac.id ORDER BY kac.id DESC',
                array(),
            ),
            array(
                array(
                    'article_status' => "active"
                ),
                'SELECT kac.*
                 FROM support_kb_article_category kac
                 LEFT JOIN support_kb_article ka ON kac.id  = ka.kb_article_category_id
                 WHERE ka.status = :status GROUP BY kac.id ORDER BY kac.id DESC',
                array(
                    ':status' => 'active',
                ),
            ),
            array(
                array(
                    'q' => "search query"
                ),
                'SELECT kac.*
                 FROM support_kb_article_category kac
                 LEFT JOIN support_kb_article ka ON kac.id  = ka.kb_article_category_id
                 WHERE (ka.title LIKE :title OR ka.content LIKE :content) GROUP BY kac.id ORDER BY kac.id DESC',
                array(
                    ':title'   => '%search query%',
                    ':content' => '%search query%',
                ),
            ),
            array(
                array(
                    'q'              => "search query",
                    'article_status' => "active"
                ),
                'SELECT kac.*
                 FROM support_kb_article_category kac
                 LEFT JOIN support_kb_article ka ON kac.id  = ka.kb_article_category_id
                 WHERE ka.status = :status AND (ka.title LIKE :title OR ka.content LIKE :content) GROUP BY kac.id ORDER BY kac.id DESC',
                array(
                    ':title'   => '%search query%',
                    ':content' => '%search query%',
                    ':status'  => 'active',
                ),
            ),

        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('kbCategoryGetSearchQueryProvider')]
    public function testKb_categoryGetSearchQuery($data, $query, $bindings)
    {
        $service = new \Box\Mod\Support\Service();

        $di = new \Pimple\Container();

        $service->setDi($di);

        $result = $service->kbCategoryGetSearchQuery($data);

        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertEquals(trim(preg_replace('/\s+/', '', str_replace("\n", " ", $result[0]))), trim(preg_replace('/\s+/', '', str_replace("\n", " ", $query))));
        $this->assertEquals($result[1], $bindings);

    }

    public function testKb_categoryFindAll()
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue(array()));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbCategoryFindAll();
        $this->assertIsArray($result);
    }

    public function testKb_categoryGetPairs()
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue(array()));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbCategoryGetPairs();
        $this->assertIsArray($result);
    }

    public function testKb_categoryRm()
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(0));

        $di           = new \Pimple\Container();
        $di['db']     = $db;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $model            = new \Model_SupportKbArticleCategory();
        $model->loadBean(new \DummyBean());
        $model->id        = random_int(1, 100);
        $model->KbArticle = new \Model_SupportKbArticleCategory();
        $model->KbArticle->loadBean(new \DummyBean());

        $result = $service->kbCategoryRm($model);
        $this->assertTrue($result);
    }

    public function testKb_categoryRmHasArticlesException()
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(1));

        $di           = new \Pimple\Container();
        $di['db']     = $db;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $model            = new \Model_SupportKbArticleCategory();
        $model->loadBean(new \DummyBean());
        $model->id        = random_int(1, 100);
        $model->KbArticle = new \Model_SupportKbArticle();
        $model->KbArticle->loadBean(new \DummyBean());

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $service->kbCategoryRm($model);
        $this->assertNull($result);
    }

    public function testKb_createCategory()
    {
        $service = new \Box\Mod\Support\Service();

        $randId = random_int(1, 100);

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));
        $articleCategoryModel = new \Model_SupportKbArticleCategory();
        $articleCategoryModel->loadBean(new \DummyBean());

        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($articleCategoryModel));

        $tools = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->onlyMethods(array('slug'))->getMock();
        $tools->expects($this->atLeastOnce())
            ->method('slug')
            ->will($this->returnValue('article-slug'));

        $systemService = $this->getMockBuilder(\Box\Mod\System\Service::class)->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('checkLimits')
            ->will($this->returnValue(true));

        $di                = new \Pimple\Container();
        $di['db']          = $db;
        $di['tools']       = $tools;
        $di['logger']      = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod_service'] = $di->protect(fn() => $systemService);
        $service->setDi($di);

        $result = $service->kbCreateCategory('Title', 'Description');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);

    }

    public function testKb_updateCategory()
    {
        $service = new \Box\Mod\Support\Service();
        $randId  = random_int(1, 100);
        $db      = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Pimple\Container();
        $di['db']     = $db;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $model     = new \Model_SupportKbArticleCategory();
        $model->loadBean(new \DummyBean());
        $model->id = random_int(1, 100);

        $result = $service->kbUpdateCategory($model, 'New Title', 'new-title', 'Description');
        $this->assertTrue($result);
    }

    public function testKb_FindCategoryById()
    {
        $service = new \Box\Mod\Support\Service();

        $model = new \Model_SupportKbArticleCategory();
        $db    = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbFindCategoryById(5);
        $this->assertInstanceOf('Model_SupportKbArticleCategory', $result);
        $this->assertEquals($result, $model);
    }

    public function testKb_FindCategoryBySlug()
    {
        $service = new \Box\Mod\Support\Service();

        $model = new \Model_SupportKbArticleCategory();
        $db    = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbFindCategoryBySlug('slug');
        $this->assertInstanceOf('Model_SupportKbArticleCategory', $result);
        $this->assertEquals($result, $model);
    }
}
