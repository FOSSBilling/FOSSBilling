<?php
namespace Box\Tests\Mod\Support;

use RedBeanPHP\OODBBean;

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
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testOnAfterClientOpenTicket()
    {
        $toApiArrayReturn   = array(
            'client' => array(
                'id' => rand(1, 100)
            )
        );
        $serviceMock        = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('getTicketById', 'toApiArray'))->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceMock->expects($this->atLeastOnce())->method('getTicketById')
            ->will($this->returnValue($supportTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                    = new \Box_Di();
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
                'id' => rand(1, 100)
            )
        );
        $serviceMock        = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('getTicketById', 'toApiArray'))->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceMock->expects($this->atLeastOnce())->method('getTicketById')
            ->will($this->returnValue($supportTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                   = new \Box_Di();
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
                'id' => rand(1, 100)
            )
        );
        $serviceMock        = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('getTicketById', 'toApiArray'))->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceMock->expects($this->atLeastOnce())->method('getTicketById')
            ->will($this->returnValue($supportTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                   = new \Box_Di();
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
                'id' => rand(1, 100)
            )
        );
        $serviceMock        = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('getTicketById', 'toApiArray'))->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceMock->expects($this->atLeastOnce())->method('getTicketById')
            ->will($this->returnValue($supportTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                   = new \Box_Di();
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
        $serviceMock         = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('getPublicTicketById', 'publicToApiArray'))->getMock();
        $supportPTicketModel = new \Model_SupportPTicket();
        $supportPTicketModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceMock->expects($this->atLeastOnce())->method('getPublicTicketById')
            ->will($this->returnValue($supportPTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                = new \Box_Di();
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
        $serviceMock         = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('getPublicTicketById', 'publicToApiArray'))->getMock();
        $supportPTicketModel = new \Model_SupportPTicket();
        $supportPTicketModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceMock->expects($this->atLeastOnce())->method('getPublicTicketById')
            ->will($this->returnValue($supportPTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                   = new \Box_Di();
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
        $serviceMock         = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('getPublicTicketById', 'publicToApiArray'))->getMock();
        $supportPTicketModel = new \Model_SupportPTicket();
        $supportPTicketModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceMock->expects($this->atLeastOnce())->method('getPublicTicketById')
            ->will($this->returnValue($supportPTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                   = new \Box_Di();
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
        $serviceMock         = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('getPublicTicketById', 'publicToApiArray'))->getMock();
        $supportPTicketModel = new \Model_SupportPTicket();
        $supportPTicketModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceMock->expects($this->atLeastOnce())->method('getPublicTicketById')
            ->will($this->returnValue($supportPTicketModel));
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue($toApiArrayReturn));

        $emailServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('sendTemplate'))->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->will($this->returnValue(true));

        $di                   = new \Box_Di();
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

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getTicketById(rand(1, 100));
        $this->assertInstanceOf('Model_SupportTicket', $result);
    }

    public function testGetPublicTicketById()
    {
        $dbMock             = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($supportTicketModel));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getPublicTicketById(rand(1, 100));
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
        $supportTicketModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($supportTicketModel));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $result = $this->service->findOneByClient($client, rand(1, 100));
        $this->assertInstanceOf('Model_SupportTicket', $result);
    }

    public function testFindOneByClientNotFoundException()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $this->expectException(\Box_Exception::class);
        $result = $this->service->findOneByClient($client, rand(1, 100));
        $this->assertInstanceOf('Model_SupportTicket', $result);
    }

    public function getSearchQueryProvider()
    {
        return array(
            array(
                array('search'              => 'query',
                      'id'                  => rand(1, 100),
                      'status'              => 'open',
                      'client_id'           => rand(1, 100),
                      'client'              => 'Client name',
                      'order_id'            => rand(1, 100),
                      'subject'             => 'subject',
                      'content'             => 'Content',
                      'support_helpdesk_id' => rand(1, 100),
                      'created_at'          => date('Y-m-d H:i:s'),
                      'date_from'           => date('Y-m-d H:i:s'),
                      'date_to'             => date('Y-m-d H:i:s'),
                      'priority'            => rand(1, 100),
                )
            ),
            array(
                array(
                    'search'              => rand(1, 100),
                    'id'                  => rand(1, 100),
                    'status'              => 'open',
                    'client_id'           => rand(1, 100),
                    'client'              => 'Client name',
                    'order_id'            => rand(1, 100),
                    'subject'             => 'subject',
                    'content'             => 'Content',
                    'support_helpdesk_id' => rand(1, 100),
                    'created_at'          => date('Y-m-d H:i:s'),
                    'date_from'           => date('Y-m-d H:i:s'),
                    'date_to'             => date('Y-m-d H:i:s'),
                    'priority'            => rand(1, 100),
                )
            )
        );
    }

    /**
     * @dataProvider getSearchQueryProvider
     */
    public function testGetSearchQuery($data)
    {
        $di              = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);
        list($query, $bindings) = $this->service->getSearchQuery($data);
        $this->assertIsString($query);
        $this->assertIsArray($bindings);
    }

    public function testCounter()
    {
        $arr    = array(
            \Model_SupportTicket::OPENED => rand(1, 100),
            \Model_SupportTicket::ONHOLD => rand(1, 100),
            \Model_SupportTicket::CLOSED => rand(1, 100),
        );
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue($arr));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $result = $this->service->counter();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(array_sum($arr), $result['total']);
    }

    public function testGetLatest()
    {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($ticket, $ticket)));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getLatest();
        $this->assertIsArray($result);
        $this->assertInstanceOf('Model_SupportTicket', $result[0]);
    }

    public function testGetExpired()
    {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue(array(array('id' => 1))));

        $di       = new \Box_Di();
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
            ->will($this->returnValue(rand(1, 100)));

        $di       = new \Box_Di();
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
            ->will($this->returnValue(rand(1, 100)));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->getActiveTicketsCountForOrder($order);
        $this->assertIsInt($result);
    }

    public function testCheckIfTaskAlreadyExistsTrue()
    {
        $dbMock             = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($supportTicketModel));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->checkIfTaskAlreadyExists($client, rand(1, 100), rand(1, 100), rand(1, 100));
        $this->assertTrue($result);
    }

    public function testCheckIfTaskAlreadyExistsFalse()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(false));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->checkIfTaskAlreadyExists($client, rand(1, 100), rand(1, 100), 'Task');
        $this->assertFalse($result);
    }

    public function closeTicketProvider()
    {
        return array(
            array(new \Model_Admin()),
            array(new \Model_Client())
        );
    }

    /**
     * @dataProvider closeTicketProvider
     */
    public function testCloseTicket($identity)
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->closeTicket($ticket, $identity);
        $this->assertTrue($result);
    }

    public function testAutoClose()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->autoClose($ticket);
        $this->assertTrue($result);
    }

    public function testCanBeReopenedNotClosed()
    {
        $helpdesk = New \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('getExistingModelById')
            ->will($this->returnValue($helpdesk));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->canBeReopened($ticket);
        $this->assertTrue($result);
    }

    public function testCanBeReopened()
    {
        $helpdesk = New \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \RedBeanPHP\OODBBean());
        $helpdesk->support_helpdesk_id = rand(1, 100);
        $helpdesk->can_reopen          = true;

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($helpdesk));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());
        $ticket->status = \Model_SupportTicket::CLOSED;

        $result = $this->service->canBeReopened($ticket);
        $this->assertTrue($result);
    }

    public function testRmByClient()
    {
        $model = new \Model_SupportTicket();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = rand(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($model)));
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());


        $result = $this->service->rmByClient($client);
        $this->assertNull($result);
    }

    public function testRm()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->exactly(2))
            ->method('find')
            ->withConsecutive(['SupportTicketNote'], ['SupportTicketMessage'])
            ->willReturnOnConsecutiveCalls(
                    [new \Model_SupportTicketNote(), new \Model_SupportTicketNote()],
                    [new \Model_SupportTicketMessage(), new \Model_SupportTicketMessage()]
            );

        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->rm($ticket);
        $this->assertTrue($result);
    }

    public function testToApiArray()
    {
        $supportTicketMessageModel = new \Model_SupportTicketMessage();
        $supportTicketMessageModel->loadBean(new \RedBeanPHP\OODBBean());
        $helpdesk = New \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($supportTicketMessageModel));
        $dbMock->expects($this->atleastOnce())
            ->method('load')
            ->withConsecutive(['SupportHelpdesk'], ['Client'])
            ->willReturnOnConsecutiveCalls($helpdesk, new \Model_Client());
        
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array(new \Model_SupportTicketNote())));

        $ticketMessages = array(new \Model_SupportTicketMessage(), new \Model_SupportTicketMessage());
        $serviceMock    = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('messageGetRepliesCount', 'messageToApiArray', 'helpdeskToApiArray', 'messageGetTicketMessages', 'noteToApiArray', 'getClientApiArrayForTicket'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('messageGetRepliesCount')
            ->will($this->returnValue(rand(1, 100)));
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

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

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
            ->withConsecutive(['SupportHelpdesk'], ['Client'])
            ->willReturnOnConsecutiveCalls(new \Model_SupportHelpdesk(), new \Model_Client());
       
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array(new \Model_SupportTicketNote())));

        $ticketMessages = array(new \Model_SupportTicketMessage(), new \Model_SupportTicketMessage());
        $serviceMock    = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('messageGetRepliesCount', 'messageToApiArray', 'helpdeskToApiArray', 'messageGetTicketMessages', 'noteToApiArray', 'getClientApiArrayForTicket'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('messageGetRepliesCount')
            ->will($this->returnValue(rand(1, 100)));
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

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());
        $ticket->rel_id   = rand(1, 100);
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

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')
            ->setMethods(array('toApiArray'))->getMock();
        $clientServiceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['logger']      = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod_service'] = $di->protect(function () use ($clientServiceMock) {
            return $clientServiceMock;
        });
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->getClientApiArrayForTicket($ticket);

        $this->assertIsArray($result);
    }

    public function testGetClientApiArrayForTicketClientNotExists()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue(null));

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')
            ->setMethods(array('toApiArray'))->getMock();
        $clientServiceMock->expects($this->never())->method('toApiArray')
            ->will($this->returnValue(array()));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['logger']      = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod_service'] = $di->protect(function () use ($clientServiceMock) {
            return $clientServiceMock;
        });
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->getClientApiArrayForTicket($ticket);

        $this->assertIsArray($result);
    }

    public function testNoteGetAuthorDetails()
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());
        $admin->name = 'AdminName';

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($admin));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \RedBeanPHP\OODBBean());

        $this->service->noteGetAuthorDetails($note);
    }

    public function testNoteRm()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->noteRm($note);
        $this->assertTrue($result);
    }

    public function testNoteToApiArray()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('noteGetAuthorDetails'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('noteGetAuthorDetails')
            ->will($this->returnValue(array()));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $serviceMock->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \RedBeanPHP\OODBBean());

        $result = $serviceMock->noteToApiArray($note);
        $this->assertArrayHasKey('author', $result);
        $this->assertIsArray($result['author']);
    }

    public function testHelpdeskGetSearchQuery()
    {
        $di              = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $data = array(
            'search' => 'SearchQuery'
        );
        list($query, $bindings) = $this->service->helpdeskGetSearchQuery($data);

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


        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $this->service->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \RedBeanPHP\OODBBean());

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

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \RedBeanPHP\OODBBean());
        $helpdesk->id = rand(1, 100);
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

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \RedBeanPHP\OODBBean());
        $helpdesk->id = rand(1, 100);
        $this->expectException(\Box_Exception::class);
        $result       = $this->service->helpdeskRm($helpdesk);
        $this->assertTrue($result);
    }

    public function testHelpdeskToApiArray()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \RedBeanPHP\OODBBean());
        $helpdesk->id = rand(1, 100);
        $result       = $this->service->helpdeskToApiArray($helpdesk);
        $this->assertIsArray($result);
    }

    public function testMessageGetTicketMessages()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array(new \Model_SupportTicketMessage())));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());
        $ticket->id = rand(1, 100);

        $result = $this->service->messageGetTicketMessages($ticket);
        $this->assertIsArray($result);
    }

    public function testMessageGetRepliesCount()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(rand(1, 100)));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());
        $ticket->id = rand(1, 100);

        $result = $this->service->messageGetRepliesCount($ticket);
        $this->assertIsInt($result);
    }

    public function testMessageGetAuthorDetailsAdmin()
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());
        $admin->id = rand(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($admin));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticketMsg = new \Model_SupportTicketMessage();
        $ticketMsg->loadBean(new \RedBeanPHP\OODBBean());
        $ticketMsg->admin_id = rand(1, 100);

        $result = $this->service->messageGetAuthorDetails($ticketMsg);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
    }

    public function testMessageGetAuthorDetailsClient()
    {
        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($client));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticketMsg = new \Model_SupportTicketMessage();
        $ticketMsg->loadBean(new \RedBeanPHP\OODBBean());
        $ticketMsg->client_id = rand(1, 100);

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

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('messageGetAuthorDetails'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('messageGetAuthorDetails')
            ->will($this->returnValue(array()));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $ticketMsg = new \Model_SupportTicketMessage();
        $ticketMsg->loadBean(new \RedBeanPHP\OODBBean());
        $ticketMsg->id = rand(1, 100);

        $result = $serviceMock->messageToApiArray($ticketMsg);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('author', $result);
    }

    public function testTicketUpdate()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['logger']    = $this->getMockBuilder('Box_Log')->getMock();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $data = array(
            'support_helpdesk_id' => rand(1, 100),
            'status'              => \Model_SupportTicket::OPENED,
            'subject'             => 'Subject',
            'priority'            => rand(1, 100),
        );

        $result = $this->service->ticketUpdate($ticket, $data);
        $this->assertTrue($result);
    }

    public function testTicketMessageUpdate()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \RedBeanPHP\OODBBean());

        $data = array(
            'support_helpdesk_id' => rand(1, 100),
            'status'              => \Model_SupportTicket::OPENED,
            'subject'             => 'Subject',
            'priority'            => rand(1, 100),
        );

        $result = $this->service->ticketMessageUpdate($message, $data);
        $this->assertTrue($result);
    }

    public function ticketReplyProvider()
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());
        $admin->id = rand(1, 100);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        return array(
            array($admin),
            array($client)
        );
    }

    /**
     * @dataProvider ticketReplyProvider
     */
    public function testTicketReply($identity)
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \RedBeanPHP\OODBBean());

        $randId = rand(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($message));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $this->getMockBuilder('Box_Request')->getMock();
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());


        $result = $this->service->ticketReply($ticket, $identity, 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testTicketCreateForAdmin()
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \RedBeanPHP\OODBBean());

        $randId = rand(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($message));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $this->getMockBuilder('Box_Request')->getMock();
        $di['events_manager'] = $eventMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \RedBeanPHP\OODBBean());

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());
        $admin->id = rand(1, 100);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

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
        $message->loadBean(new \RedBeanPHP\OODBBean());

        $randId = rand(1, 100);
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

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isEmailValid');

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['validator']      = $validatorMock;
        $di['request']        = $this->getMockBuilder('Box_Request')->getMock();
        $di['events_manager'] = $eventMock;
        $di['array_get']      = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \RedBeanPHP\OODBBean());

        $data = array(
            'name'    => 'Name',
            'email'   => 'email@example.com',
            'subject' => 'Subject',
            'message' => 'message'
        );

        $result = $this->service->ticketCreateForGuest($data);
        $this->assertIsString($result);
        $this->assertEquals(strlen($result), 40);
    }

    public function testTicketCreateForClient()
    {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $randId = rand(1, 100);
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
            'autorespond_message_id' => rand(1, 100)
        );
        $supportModMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()
            ->setMethods(array('getConfig'))->getMock();
        $supportModMock->expects($this->atLeastOnce())->method('getConfig')
            ->will($this->returnValue($config));

        $staffServiceMock = $this->getMockBuilder('\Box\Mod\Staff\Service')
            ->setMethods(array('getCronAdmin'))->getMock();
        $staffServiceMock->expects($this->atLeastOnce())->method('getCronAdmin')
            ->will($this->returnValue(new \Model_Admin()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('ticketReply', 'messageCreateForTicket', 'cannedToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketReply')
            ->will($this->returnValue(new \Model_Admin()));
        $serviceMock->expects($this->atLeastOnce())->method('messageCreateForTicket')
            ->will($this->returnValue(new \Model_Admin()));
        $serviceMock->expects($this->atLeastOnce())->method('cannedToApiArray')
            ->will($this->returnValue(array('content' => 'Content')));

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $this->getMockBuilder('Box_Request')->getMock();
        $di['events_manager'] = $eventMock;
        $di['mod']            = $di->protect(function () use ($supportModMock) {
            return $supportModMock;
        });
        $di['mod_service']    = $di->protect(function () use ($staffServiceMock) {
            return $staffServiceMock;
        });
        $di['array_get']      = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $serviceMock->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \RedBeanPHP\OODBBean());

        $guest     = new \Model_Guest();
        $guest->id = rand(1, 100);

        $data = array(
            'name'    => 'Name',
            'email'   => 'email@example.com',
            'subject' => 'Subject',
            'content' => 'content'
        );

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \RedBeanPHP\OODBBean());

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $result = $serviceMock->ticketCreateForClient($client, $helpdesk, $data);
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testTicketCreateForClientTaskAlreadyExistsException()
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('checkIfTaskAlreadyExists'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('checkIfTaskAlreadyExists')
            ->will($this->returnValue(true));

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \RedBeanPHP\OODBBean());

        $guest     = new \Model_Guest();
        $guest->id = rand(1, 100);

        $data = array(
            'rel_id'        => rand(1, 100),
            'rel_type'      => 'Type',
            'rel_task'      => 'Task',
            'rel_new_value' => 'New value',
        );

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \RedBeanPHP\OODBBean());

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $di              = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $serviceMock->setDi($di);

        $this->expectException(\Box_Exception::class);
        $serviceMock->ticketCreateForClient($client, $helpdesk, $data);
    }

    public function messageCreateForTicketProvider()
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());
        $admin->id = rand(1, 100);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        return array(
            array(
                $admin
            ),
            array(
                $client
            ),
        );
    }

    /**
     * @dataProvider messageCreateForTicketProvider
     */
    public function testMessageCreateForTicket($identity)
    {
        $randId               = rand(1, 100);
        $supportTicketMessage = new \Model_SupportTicketMessage();
        $supportTicketMessage->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($supportTicketMessage));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di            = new \Box_Di();
        $di['db']      = $dbMock;
        $di['logger']  = $this->getMockBuilder('Box_Log')->getMock();
        $di['request'] = $this->getMockBuilder('Box_Request')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->messageCreateForTicket($ticket, $identity, 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testMessageCreateForTicketIdentityException()
    {
        $randId               = rand(1, 100);
        $supportTicketMessage = new \Model_SupportTicketMessage();
        $supportTicketMessage->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($supportTicketMessage));
        $dbMock->expects($this->never())
            ->method('store')
            ->will($this->returnValue($randId));

        $di            = new \Box_Di();
        $di['db']      = $dbMock;
        $di['logger']  = $this->getMockBuilder('Box_Log')->getMock();
        $di['request'] = $this->getMockBuilder('Box_Request')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $this->expectException(\Box_Exception::class);
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

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $result = $this->service->publicFindOneByHash(sha1(uniqid()));
        $this->assertInstanceOf('Model_SupportPTicket', $result);
    }

    public function testPublicFindOneByHashNotFoundException()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $this->expectException(\Box_Exception::class);
        $result = $this->service->publicFindOneByHash(sha1(uniqid()));
        $this->assertInstanceOf('Model_SupportPTicket', $result);
    }

    public function publicGetSearchQueryProvider()
    {
        return array(
            array(
                array(
                    'search'  => 'Query',
                    'id'      => rand(1, 100),
                    'status'  => \Model_SupportPTicket::OPENED,
                    'name'    => 'Name',
                    'email'   => 'email@example.com',
                    'subject' => 'Subject',
                    'content' => 'Content',
                )
            ),
            array(
                array(
                    'search'  => rand(1, 100),
                    'search'  => rand(1, 100),
                    'id'      => rand(1, 100),
                    'status'  => \Model_SupportPTicket::OPENED,
                    'name'    => 'Name',
                    'email'   => 'email@example.com',
                    'subject' => 'Subject',
                    'content' => 'Content',
                )
            ),
        );
    }


    /**
     * @dataProvider publicGetSearchQueryProvider
     */
    public function testPublicGetSearchQuery($data)
    {
        $di              = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        list($query, $bindings) = $this->service->publicgetSearchQuery($data);
        $this->assertIsString($query);
        $this->assertIsArray($bindings);
    }

    public function testPublicCounter()
    {
        $arr    = array(
            \Model_SupportPTicket::OPENED => rand(1, 100),
            \Model_SupportPTicket::ONHOLD => rand(1, 100),
            \Model_SupportPTicket::CLOSED => rand(1, 100),
        );
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue($arr));

        $di       = new \Box_Di();
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

        $di       = new \Box_Di();
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
            ->will($this->returnValue(rand(1, 100)));

        $di       = new \Box_Di();
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

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->publicGetExpired();
        $this->assertIsArray($result);
        $this->assertInstanceOf('Model_SupportPTicket', $result[0]);
    }

    public function publicCloseTicketProvider()
    {
        return array(
            array(new \Model_Admin()),
            array(new \Model_Guest())
        );
    }

    /**
     * @dataProvider publicCloseTicketProvider
     */
    public function testPublicCloseTicket($identity)
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->publicCloseTicket($ticket, $identity);
        $this->assertTrue($result);
    }

    public function testPublicAutoClose()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

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

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $canned = new \Model_SupportPTicket();
        $canned->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->publicRm($canned);
        $this->assertTrue($result);
    }

    public function publicToApiArrayProvider()
    {
        return array(
            array(
                new \Model_SupportPTicketMessage(),
                $this->atLeastOnce()
            ),
            array(
                null,
                $this->never()
            )
        );
    }

    /**
     * @dataProvider publicToApiArrayProvider
     */
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

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('publicMessageToApiArray', 'publicMessageGetAuthorDetails'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicMessageToApiArray')
            ->will($this->returnValue(array()));
        $serviceMock->expects($this->atLeastOnce())->method('publicMessageGetAuthorDetails')
            ->will($this->returnValue(array('name' => 'Name', 'email' => 'email#example.com')));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $result = $serviceMock->publicToApiArray($ticket, true);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('author', $result);
        $this->assertArrayHasKey('messages', $result);

        $this->assertEquals(count($result['messages']), count($ticketMessages));
    }

    public function testPublicMessageGetAuthorDetailsAdmin()
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());
        $admin->id = rand(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($admin));


        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticketMsg = new \Model_SupportPTicketMessage();
        $ticketMsg->loadBean(new \RedBeanPHP\OODBBean());
        $ticketMsg->admin_id = rand(1, 100);

        $result = $this->service->publicMessageGetAuthorDetails($ticketMsg);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
    }

    public function testPublicMessageGetAuthorDetailsNotAdmin()
    {
        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());
        $ticket->author_name  = "Name";
        $ticket->author_email = "Email@example.com";

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($ticket));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticketMsg = new \Model_SupportPTicketMessage();
        $ticketMsg->loadBean(new \RedBeanPHP\OODBBean());
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

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('publicMessageGetAuthorDetails'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicMessageGetAuthorDetails')
            ->will($this->returnValue(array()));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $ticketMsg = new \Model_SupportPTicketMessage();
        $ticketMsg->loadBean(new \RedBeanPHP\OODBBean());
        $ticketMsg->id = rand(1, 100);

        $result = $serviceMock->publicMessageToApiArray($ticketMsg);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('author', $result);
    }

    public function testPublicTicketCreate()
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \RedBeanPHP\OODBBean());

        $randId = rand(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($message));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('isEmailValid');

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $this->getMockBuilder('Box_Request')->getMock();
        $di['validator']      = $validatorMock;
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());
        $admin->id = rand(1, 100);

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
            ->will($this->returnValue(rand(1, 100)));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['logger']    = $this->getMockBuilder('Box_Log')->getMock();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $data = array(
            'support_helpdesk_id' => rand(1, 100),
            'status'              => \Model_SupportTicket::OPENED,
            'subject'             => 'Subject',
            'priority'            => rand(1, 100),
        );

        $result = $this->service->publicTicketUpdate($ticket, $data);
        $this->assertTrue($result);
    }

    public function testPublicTicketReply()
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \RedBeanPHP\OODBBean());

        $randId = rand(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($message));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $this->getMockBuilder('Box_Request')->getMock();
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->publicTicketReply($ticket, $admin, 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testPublicTicketReplyForGuest()
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \RedBeanPHP\OODBBean());

        $randId = rand(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($message));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['logger']         = $this->getMockBuilder('Box_Log')->getMock();
        $di['request']        = $this->getMockBuilder('Box_Request')->getMock();
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());
        $ticket->hash = sha1(uniqid());

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->publicTicketReplyForGuest($ticket, 'Message');
        $this->assertIsString($result);
        $this->assertEquals(strlen($result), 40);
    }

    public function testHelpdeskUpdate()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['logger']    = $this->getMockBuilder('Box_Log')->getMock();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \RedBeanPHP\OODBBean());

        $data = array(
            'name'        => 'Name',
            'email'       => 'email@example.com',
            'can_reopen'  => 1,
            'close_after' => rand(1, 100),
            'signature'   => 'Signature',
        );

        $result = $this->service->helpdeskUpdate($helpdesk, $data);
        $this->assertTrue($result);
    }

    public function testHelpdeskCreate()
    {
        $randId        = rand(1, 100);
        $helpDeskModel = new \Model_SupportHelpdesk();
        $helpDeskModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($helpDeskModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['logger']    = $this->getMockBuilder('Box_Log')->getMock();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $ticket = new \Model_SupportHelpdesk();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $data = array(
            'name'        => 'Name',
            'email'       => 'email@example.com',
            'can_reopen'  => 1,
            'close_after' => rand(1, 100),
            'signature'   => 'Signature',
        );

        $result = $this->service->helpdeskCreate($data);
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testCannedGetSearchQuery()
    {
        $di              = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $data = array(
            'search' => 'query',
        );

        list($query, $bindings) = $this->service->cannedGetSearchQuery($data);
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

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

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

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $canned = new \Model_SupportPr();
        $canned->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->cannedRm($canned);
        $this->assertTrue($result);
    }

    public function testCannedToApiArray()
    {
        $category = new \Model_SupportPrCategory();
        $category->loadBean(new \RedBeanPHP\OODBBean());
        $category->id    = rand(1, 100);
        $category->title = 'General';

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($category));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);


        $canned = new \Model_SupportPr();
        $canned->loadBean(new \RedBeanPHP\OODBBean());

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

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);


        $canned = new \Model_SupportPr();
        $canned->loadBean(new \RedBeanPHP\OODBBean());

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


        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $this->service->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->cannedCategoryGetPairs();
        $this->assertIsArray($result);
    }

    public function testCannedCategoryRm()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $canned = new \Model_SupportPrCategory();
        $canned->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->cannedCategoryRm($canned);
        $this->assertTrue($result);
    }

    public function testCannedCategoryToApiArray()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $canned = new \Model_SupportPrCategory();
        $canned->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->cannedCategoryToApiArray($canned);
        $this->assertIsArray($result);
    }

    public function testCannedCreate()
    {
        $randId        = rand(1, 100);
        $helpDeskModel = new \Model_SupportHelpdesk();
        $helpDeskModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($helpDeskModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $settingsServiceMock = $this->getMockBuilder('\Box\Mod\Email\Service')
            ->setMethods(array('checkLimits'))->getMock();
        $settingsServiceMock->expects($this->atLeastOnce())->method('checkLimits')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($settingsServiceMock) {
            return $settingsServiceMock;
        });
        $di['db']          = $dbMock;
        $di['logger']      = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportHelpdesk();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $data = array(
            'name'        => 'Name',
            'email'       => 'email@example.com',
            'can_reopen'  => 1,
            'close_after' => rand(1, 100),
            'signature'   => 'Signature',
        );

        $result = $this->service->cannedCreate($data, rand(1, 100), 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testCannedUpdate()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(rand(1, 100)));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['logger']    = $this->getMockBuilder('Box_Log')->getMock();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $model = new \Model_SupportPr();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $data = array(
            'category_id' => rand(1, 100),
            'title'       => 'email@example.com',
            'content'     => 1,
        );

        $result = $this->service->cannedUpdate($model, $data);
        $this->assertTrue($result);
    }

    public function testCannedCategoryCreate()
    {
        $randId                 = rand(1, 100);
        $supportPrCategoryModel = new \Model_SupportPrCategory();
        $supportPrCategoryModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($supportPrCategoryModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $data = array(
            'name'        => 'Name',
            'email'       => 'email@example.com',
            'can_reopen'  => 1,
            'close_after' => rand(1, 100),
            'signature'   => 'Signature',
        );

        $result = $this->service->cannedCategoryCreate($data, rand(1, 100), 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testCannedCategoryUpdate()
    {
        $randId = rand(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $model = new \Model_SupportPrCategory();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->cannedCategoryUpdate($model, 'Title');
        $this->assertTrue($result);
    }

    public function testNoteCreate()
    {
        $randId                 = rand(1, 100);
        $supportPrCategoryModel = new \Model_SupportPrCategory();
        $supportPrCategoryModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($supportPrCategoryModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $data = array(
            'name'        => 'Name',
            'email'       => 'email@example.com',
            'can_reopen'  => 1,
            'close_after' => rand(1, 100),
            'signature'   => 'Signature',
        );

        $admin = new \Model_Admin();
        $admin->loadBean(new \RedBeanPHP\OODBBean());

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->noteCreate($ticket, $admin, 'Note');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testTicketTaskComplete()
    {
        $randId = rand(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $model = new \Model_SupportTicket();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->ticketTaskComplete($model);
        $this->assertTrue($result);
    }

    public function canClientSubmitNewTicketProvider()
    {

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());
        $ticket->client_id  = 5;
        $ticket->created_at = date('Y-m-d H:i:s');

        $ticket2 = new \Model_SupportTicket();
        $ticket2->loadBean(new \RedBeanPHP\OODBBean());
        $ticket2->client_id  = 5;
        $ticket2->created_at = date('Y-m-d H:i:s', strtotime("-2 days"));;

        return array(
            array($ticket, 24, false), //Ticket is created today, exception should be thrown
            array(null, 24, true), //No previously created tickets found, can submit a ticket
            array($ticket2, 24, true) //Last ticket submitted 2 days ago, can submit a ticket
        );
    }

    /**
     * @dataProvider canClientSubmitNewTicketProvider
     */
    public function testCanClientSubmitNewTicket($ticket, $hours, $expected)
    {
        if (!$expected) {
            $this->expectException(\Box_Exception::class);
        }
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($ticket));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);
        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = 5;

        $config = array('wait_hours' => $hours);

        $result = $this->service->canClientSubmitNewTicket($client, $config);
        $this->assertTrue($result);

    }
}
 