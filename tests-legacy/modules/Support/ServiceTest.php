<?php

namespace Box\Tests\Mod\Support;

use Symfony\Component\HttpFoundation\Request;

class ServiceTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Support\Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Support\Service();
    }

    public function testDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testOnAfterClientOpenTicket(): void
    {
        $toApiArrayReturn = [
            'client' => [
                'id' => random_int(1, 100),
            ],
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['getTicketById', 'toApiArray'])->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getTicketById')
            ->willReturn($supportTicketModel);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($toApiArrayReturn);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'support') {
                return $serviceMock;
            }
        });
        $di['loggedin_client'] = new \Model_Client();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $serviceMock->onAfterClientOpenTicket($eventMock);
        $this->assertNull($result);
    }

    public function testOnAfterAdminOpenTicket(): void
    {
        $toApiArrayReturn = [
            'client' => [
                'id' => random_int(1, 100),
            ],
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['getTicketById', 'toApiArray'])->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getTicketById')
            ->willReturn($supportTicketModel);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($toApiArrayReturn);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'support') {
                return $serviceMock;
            }
        });
        $di['loggedin_admin'] = new \Model_Admin();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $serviceMock->onAfterAdminOpenTicket($eventMock);
        $this->assertNull($result);
    }

    public function testOnAfterAdminCloseTicket(): void
    {
        $toApiArrayReturn = [
            'client' => [
                'id' => random_int(1, 100),
            ],
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['getTicketById', 'toApiArray'])->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getTicketById')
            ->willReturn($supportTicketModel);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($toApiArrayReturn);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'support') {
                return $serviceMock;
            }
        });
        $di['loggedin_admin'] = new \Model_Admin();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $serviceMock->onAfterAdminCloseTicket($eventMock);
        $this->assertNull($result);
    }

    public function testOnAfterAdminReplyTicket(): void
    {
        $toApiArrayReturn = [
            'client' => [
                'id' => random_int(1, 100),
            ],
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['getTicketById', 'toApiArray'])->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getTicketById')
            ->willReturn($supportTicketModel);
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn($toApiArrayReturn);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'support') {
                return $serviceMock;
            }
        });
        $di['loggedin_admin'] = new \Model_Admin();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $serviceMock->onAfterAdminReplyTicket($eventMock);
        $this->assertNull($result);
    }

    public function testOnAfterGuestPublicTicketOpen(): void
    {
        $toApiArrayReturn = [
            'author_email' => 'email@example.com',
            'author_name' => 'Name',
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['getPublicTicketById', 'publicToApiArray'])->getMock();
        $supportPTicketModel = new \Model_SupportPTicket();
        $supportPTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getPublicTicketById')
            ->willReturn($supportPTicketModel);
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->willReturn($toApiArrayReturn);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'support') {
                return $serviceMock;
            }
        });
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $serviceMock->onAfterGuestPublicTicketOpen($eventMock);
        $this->assertNull($result);
    }

    public function testOnAfterAdminPublicTicketOpen(): void
    {
        $toApiArrayReturn = [
            'author_email' => 'email@example.com',
            'author_name' => 'Name',
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['getPublicTicketById', 'publicToApiArray'])->getMock();
        $supportPTicketModel = new \Model_SupportPTicket();
        $supportPTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getPublicTicketById')
            ->willReturn($supportPTicketModel);
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->willReturn($toApiArrayReturn);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'support') {
                return $serviceMock;
            }
        });
        $di['loggedin_admin'] = new \Model_Admin();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $serviceMock->onAfterAdminPublicTicketOpen($eventMock);
        $this->assertNull($result);
    }

    public function testOnAfterAdminPublicTicketReply(): void
    {
        $toApiArrayReturn = [
            'author_email' => 'email@example.com',
            'author_name' => 'Name',
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['getPublicTicketById', 'publicToApiArray'])->getMock();
        $supportPTicketModel = new \Model_SupportPTicket();
        $supportPTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getPublicTicketById')
            ->willReturn($supportPTicketModel);
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->willReturn($toApiArrayReturn);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'support') {
                return $serviceMock;
            }
        });
        $di['loggedin_admin'] = new \Model_Admin();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $serviceMock->onAfterAdminPublicTicketReply($eventMock);
        $this->assertNull($result);
    }

    public function testOnAfterAdminPublicTicketClose(): void
    {
        $toApiArrayReturn = [
            'author_email' => 'email@example.com',
            'author_name' => 'Name',
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['getPublicTicketById', 'publicToApiArray'])->getMock();
        $supportPTicketModel = new \Model_SupportPTicket();
        $supportPTicketModel->loadBean(new \DummyBean());
        $serviceMock->expects($this->atLeastOnce())->method('getPublicTicketById')
            ->willReturn($supportPTicketModel);
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->willReturn($toApiArrayReturn);

        $emailServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Email\Service::class)
            ->onlyMethods(['sendTemplate'])->getMock();
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
            if ($serviceName == 'email') {
                return $emailServiceMock;
            }
            if ($serviceName == 'support') {
                return $serviceMock;
            }
        });
        $di['loggedin_admin'] = new \Model_Admin();
        $serviceMock->setDi($di);

        $eventMock = $this->getMockBuilder('\Box_Event')->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $result = $serviceMock->onAfterAdminPublicTicketClose($eventMock);
        $this->assertNull($result);
    }

    public function testGetTicketById(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicket());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getTicketById(random_int(1, 100));
        $this->assertInstanceOf('Model_SupportTicket', $result);
    }

    public function testGetPublicTicketById(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($supportTicketModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getPublicTicketById(random_int(1, 100));
        $this->assertInstanceOf('Model_SupportTicket', $result);
    }

    public function testGetStatuses(): void
    {
        $result = $this->service->getStatuses();
        $this->assertIsArray($result);
    }

    public function testFindOneByClient(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($supportTicketModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $result = $this->service->findOneByClient($client, random_int(1, 100));
        $this->assertInstanceOf('Model_SupportTicket', $result);
    }

    public function testFindOneByClientNotFoundException(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->findOneByClient($client, random_int(1, 100));
        $this->assertInstanceOf('Model_SupportTicket', $result);
    }

    public static function getSearchQueryProvider(): array
    {
        return [
            [
                [
                    'search' => 'query',
                    'id' => random_int(1, 100),
                    'status' => 'open',
                    'client_id' => random_int(1, 100),
                    'client' => 'Client name',
                    'order_id' => random_int(1, 100),
                    'subject' => 'subject',
                    'content' => 'Content',
                    'support_helpdesk_id' => random_int(1, 100),
                    'created_at' => date('Y-m-d H:i:s'),
                    'date_from' => date('Y-m-d H:i:s'),
                    'date_to' => date('Y-m-d H:i:s'),
                    'priority' => random_int(1, 100),
                ],
            ],
            [
                [
                    'search' => random_int(1, 100),
                    'id' => random_int(1, 100),
                    'status' => 'open',
                    'client_id' => random_int(1, 100),
                    'client' => 'Client name',
                    'order_id' => random_int(1, 100),
                    'subject' => 'subject',
                    'content' => 'Content',
                    'support_helpdesk_id' => random_int(1, 100),
                    'created_at' => date('Y-m-d H:i:s'),
                    'date_from' => date('Y-m-d H:i:s'),
                    'date_to' => date('Y-m-d H:i:s'),
                    'priority' => random_int(1, 100),
                ],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getSearchQueryProvider')]
    public function testGetSearchQuery(array $data): void
    {
        $di = new \Pimple\Container();

        $this->service->setDi($di);
        [$query, $bindings] = $this->service->getSearchQuery($data);
        $this->assertIsString($query);
        $this->assertIsArray($bindings);
    }

    public function testCounter(): void
    {
        $arr = [
            \Model_SupportTicket::OPENED => random_int(1, 100),
            \Model_SupportTicket::ONHOLD => random_int(1, 100),
            \Model_SupportTicket::CLOSED => random_int(1, 100),
        ];
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn($arr);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->counter();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(array_sum($arr), $result['total']);
    }

    public function testGetLatest(): void
    {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$ticket, $ticket]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getLatest();
        $this->assertIsArray($result);
        $this->assertInstanceOf('Model_SupportTicket', $result[0]);
    }

    public function testGetExpired(): void
    {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([['id' => 1]]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getExpired();
        $this->assertIsArray($result);
        $this->assertIsArray($result[0]);
    }

    public function testCountByStatus(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->countByStatus('open');
        $this->assertIsInt($result);
    }

    public function testGetActiveTicketsCountForOrder(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $result = $this->service->getActiveTicketsCountForOrder($order);
        $this->assertIsInt($result);
    }

    public function testCheckIfTaskAlreadyExistsTrue(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $supportTicketModel = new \Model_SupportTicket();
        $supportTicketModel->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($supportTicketModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $result = $this->service->checkIfTaskAlreadyExists($client, random_int(1, 100), random_int(1, 100), random_int(1, 100));
        $this->assertTrue($result);
    }

    public function testCheckIfTaskAlreadyExistsFalse(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $result = $this->service->checkIfTaskAlreadyExists($client, random_int(1, 100), random_int(1, 100), 'Task');
        $this->assertFalse($result);
    }

    public static function closeTicketProvider(): array
    {
        return [
            [new \Model_Admin()],
            [new \Model_Client()],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('closeTicketProvider')]
    public function testCloseTicket(\Model_Admin|\Model_Client $identity): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->closeTicket($ticket, $identity);
        $this->assertTrue($result);
    }

    public function testAutoClose(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->autoClose($ticket);
        $this->assertTrue($result);
    }

    public function testCanBeReopenedNotClosed(): void
    {
        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->never())
            ->method('getExistingModelById')
            ->willReturn($helpdesk);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->canBeReopened($ticket);
        $this->assertTrue($result);
    }

    public function testCanBeReopened(): void
    {
        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());
        $helpdesk->support_helpdesk_id = random_int(1, 100);
        $helpdesk->can_reopen = true;

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($helpdesk);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->status = \Model_SupportTicket::CLOSED;

        $result = $this->service->canBeReopened($ticket);
        $this->assertTrue($result);
    }

    public function testRmByClient(): void
    {
        $model = new \Model_SupportTicket();
        $model->loadBean(new \DummyBean());
        $model->id = random_int(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$model]);
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $result = $this->service->rmByClient($client);
        $this->assertNull($result);
    }

    public function testRm(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(fn (...$args): \Model_SupportTicketNote|\Model_SupportTicketMessage => match ($args[0]) {
                'SupportTicketNote' => new \Model_SupportTicketNote(),
                'SupportTicketMessage' => new \Model_SupportTicketMessage(),
            });

        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->rm($ticket);
        $this->assertTrue($result);
    }

    public function testToApiArray(): void
    {
        $supportTicketMessageModel = new \Model_SupportTicketMessage();
        $supportTicketMessageModel->loadBean(new \DummyBean());
        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($supportTicketMessageModel);
        $dbMock->expects($this->atleastOnce())
            ->method('load')
            ->willReturnCallback(fn (...$args): \Model_SupportHelpdesk|\Model_Client => match ($args[0]) {
                'SupportHelpdesk' => $helpdesk,
                'Client' => new \Model_Client(),
            });

        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new \Model_SupportTicketNote()]);

        $ticketMessages = [new \Model_SupportTicketMessage(), new \Model_SupportTicketMessage()];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['messageGetRepliesCount', 'messageToApiArray', 'helpdeskToApiArray', 'messageGetTicketMessages', 'noteToApiArray', 'getClientApiArrayForTicket'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('messageGetRepliesCount')
            ->willReturn(random_int(1, 100));
        $serviceMock->expects($this->atLeastOnce())->method('messageToApiArray')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskToApiArray')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('messageGetTicketMessages')
            ->willReturn($ticketMessages);
        $serviceMock->expects($this->atLeastOnce())->method('noteToApiArray')
            ->willReturn(null);
        $serviceMock->expects($this->atLeastOnce())->method('getClientApiArrayForTicket')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
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

    public function testToApiArrayWithRelDetails(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportTicketMessage());
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
            ->willReturn([new \Model_SupportTicketNote()]);

        $ticketMessages = [new \Model_SupportTicketMessage(), new \Model_SupportTicketMessage()];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['messageGetRepliesCount', 'messageToApiArray', 'helpdeskToApiArray', 'messageGetTicketMessages', 'noteToApiArray', 'getClientApiArrayForTicket'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('messageGetRepliesCount')
            ->willReturn(random_int(1, 100));
        $serviceMock->expects($this->atLeastOnce())->method('messageToApiArray')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskToApiArray')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('messageGetTicketMessages')
            ->willReturn($ticketMessages);
        $serviceMock->expects($this->atLeastOnce())->method('noteToApiArray')
            ->willReturn(null);
        $serviceMock->expects($this->atLeastOnce())->method('getClientApiArrayForTicket')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $serviceMock->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->rel_id = random_int(1, 100);
        $ticket->rel_type = 'Type';

        $result = $serviceMock->toApiArray($ticket, true, new \Model_Admin());
        $this->assertIsArray($result);
        $this->assertArrayHasKey('replies', $result);
        $this->assertArrayHasKey('helpdesk', $result);
        $this->assertArrayHasKey('messages', $result);

        $this->assertEquals(count($result['messages']), count($ticketMessages));
    }

    public function testGetClientApiArrayForTicket(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(new \Model_Client());

        $clientServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)
            ->onlyMethods(['toApiArray'])->getMock();
        $clientServiceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $clientServiceMock);
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->getClientApiArrayForTicket($ticket);

        $this->assertIsArray($result);
    }

    public function testGetClientApiArrayForTicketClientNotExists(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(null);

        $clientServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)
            ->onlyMethods(['toApiArray'])->getMock();
        $clientServiceMock->expects($this->never())->method('toApiArray')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $clientServiceMock);
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->getClientApiArrayForTicket($ticket);

        $this->assertIsArray($result);
    }

    public function testNoteGetAuthorDetails(): void
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->name = 'AdminName';

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($admin);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \DummyBean());

        $this->service->noteGetAuthorDetails($note);
    }

    public function testNoteRm(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \DummyBean());

        $result = $this->service->noteRm($note);
        $this->assertTrue($result);
    }

    public function testNoteToApiArray(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['noteGetAuthorDetails'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('noteGetAuthorDetails')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $serviceMock->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \DummyBean());

        $result = $serviceMock->noteToApiArray($note);
        $this->assertArrayHasKey('author', $result);
        $this->assertIsArray($result['author']);
    }

    public function testHelpdeskGetSearchQuery(): void
    {
        $di = new \Pimple\Container();

        $this->service->setDi($di);

        $data = [
            'search' => 'SearchQuery',
        ];
        [$query, $bindings] = $this->service->helpdeskGetSearchQuery($data);

        $expectedBindings = [
            ':name' => '%SearchQuery%',
            ':email' => '%SearchQuery%',
            ':signature' => '%SearchQuery%',
        ];

        $this->assertIsString($query);
        $this->assertIsArray($bindings);

        $this->assertEquals($expectedBindings, $bindings);
    }

    public function testHelpdeskGetPairs(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn([0 => 'General']);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $this->service->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \DummyBean());

        $result = $this->service->helpdeskGetPairs();
        $this->assertIsArray($result);
    }

    public function testHelpdeskRm(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([]);

        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());
        $helpdesk->id = random_int(1, 100);
        $result = $this->service->helpdeskRm($helpdesk);
        $this->assertTrue($result);
    }

    public function testHelpdeskRmHAsTicketsException(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new \Model_SupportTicket()]);

        $dbMock->expects($this->never())
            ->method('trash')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());
        $helpdesk->id = random_int(1, 100);
        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->helpdeskRm($helpdesk);
        $this->assertTrue($result);
    }

    public function testHelpdeskToApiArray(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());
        $helpdesk->id = random_int(1, 100);
        $result = $this->service->helpdeskToApiArray($helpdesk);
        $this->assertIsArray($result);
    }

    public function testMessageGetTicketMessages(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new \Model_SupportTicketMessage()]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->id = random_int(1, 100);

        $result = $this->service->messageGetTicketMessages($ticket);
        $this->assertIsArray($result);
    }

    public function testMessageGetRepliesCount(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->id = random_int(1, 100);

        $result = $this->service->messageGetRepliesCount($ticket);
        $this->assertIsInt($result);
    }

    public function testMessageGetAuthorDetailsAdmin(): void
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->id = random_int(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($admin);

        $di = new \Pimple\Container();
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

    public function testMessageGetAuthorDetailsClient(): void
    {
        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($client);

        $di = new \Pimple\Container();
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

    public function testMessageToApiArray(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['messageGetAuthorDetails'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('messageGetAuthorDetails')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $ticketMsg = new \Model_SupportTicketMessage();
        $ticketMsg->loadBean(new \DummyBean());
        $ticketMsg->id = random_int(1, 100);

        $result = $serviceMock->messageToApiArray($ticketMsg);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('author', $result);
    }

    public function testTicketUpdate(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $data = [
            'support_helpdesk_id' => random_int(1, 100),
            'status' => \Model_SupportTicket::OPENED,
            'subject' => 'Subject',
            'priority' => random_int(1, 100),
        ];

        $result = $this->service->ticketUpdate($ticket, $data);
        $this->assertTrue($result);
    }

    public function testTicketMessageUpdate(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $data = [
            'support_helpdesk_id' => random_int(1, 100),
            'status' => \Model_SupportTicket::OPENED,
            'subject' => 'Subject',
            'priority' => random_int(1, 100),
        ];

        $result = $this->service->ticketMessageUpdate($message, $data);
        $this->assertTrue($result);
    }

    public static function ticketReplyProvider(): array
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->id = random_int(1, 100);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        return [
            [$admin],
            [$client],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ticketReplyProvider')]
    public function testTicketReply(\Model_Admin|\Model_Client $identity): void
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($message);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['request'] = Request::createFromGlobals();
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->ticketReply($ticket, $identity, 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testTicketCreateForAdmin(): void
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($message);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['request'] = Request::createFromGlobals();
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

        $data = [
            'subject' => 'Subject',
            'content' => 'Content',
        ];

        $result = $this->service->ticketCreateForAdmin($client, $helpdesk, $data, $admin);
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testTicketCreateForClient(): void
    {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->with('SupportTicket')
            ->willReturn($ticket);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPr());

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $config = [
            'autorespond_enable' => 1,
            'autorespond_message_id' => random_int(1, 100),
        ];
        $supportModMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()
            ->onlyMethods(['getConfig'])->getMock();
        $supportModMock->expects($this->atLeastOnce())->method('getConfig')
            ->willReturn($config);

        $staffServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)
            ->onlyMethods(['getCronAdmin'])->getMock();
        $staffServiceMock->expects($this->atLeastOnce())->method('getCronAdmin')
            ->willReturn(new \Model_Admin());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketReply', 'messageCreateForTicket', 'cannedToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketReply')
            ->willReturn(new \Model_Admin());
        $serviceMock->expects($this->atLeastOnce())->method('messageCreateForTicket')
            ->willReturn(new \Model_Admin());
        $serviceMock->expects($this->atLeastOnce())->method('cannedToApiArray')
            ->willReturn(['content' => 'Content']);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['request'] = $this->getMockBuilder('\\' . \FOSSBilling\Request::class)->getMock();
        $di['events_manager'] = $eventMock;
        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $supportModMock);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $staffServiceMock);

        $serviceMock->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $guest = new \Model_Guest();
        $guest->id = random_int(1, 100);

        $data = [
            'name' => 'Name',
            'email' => 'email@example.com',
            'subject' => 'Subject',
            'content' => 'content',
        ];

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $result = $serviceMock->ticketCreateForClient($client, $helpdesk, $data);
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testTicketCreateForClientTaskAlreadyExistsException(): void
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['checkIfTaskAlreadyExists'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('checkIfTaskAlreadyExists')
            ->willReturn(true);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $guest = new \Model_Guest();
        $guest->id = random_int(1, 100);

        $data = [
            'rel_id' => random_int(1, 100),
            'rel_type' => 'Type',
            'rel_task' => 'Task',
            'rel_new_value' => 'New value',
        ];

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $di = new \Pimple\Container();

        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $serviceMock->ticketCreateForClient($client, $helpdesk, $data);
    }

    public static function messageCreateForTicketProvider(): array
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->id = random_int(1, 100);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        return [
            [
                $admin,
            ],
            [
                $client,
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('messageCreateForTicketProvider')]
    public function testMessageCreateForTicket(\Model_Admin|\Model_Client $identity): void
    {
        $randId = random_int(1, 100);
        $supportTicketMessage = new \Model_SupportTicketMessage();
        $supportTicketMessage->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($supportTicketMessage);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['request'] = Request::createFromGlobals();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->messageCreateForTicket($ticket, $identity, 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testMessageCreateForTicketIdentityException(): void
    {
        $randId = random_int(1, 100);
        $supportTicketMessage = new \Model_SupportTicketMessage();
        $supportTicketMessage->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($supportTicketMessage);
        $dbMock->expects($this->never())
            ->method('store')
            ->willReturn($randId);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['request'] = $this->getMockBuilder('B\FOSSBilling\Request')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->messageCreateForTicket($ticket, null, 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testPublicGetStatuses(): void
    {
        $result = $this->service->publicGetStatuses();
        $this->assertIsArray($result);
    }

    public function testPublicFindOneByHash(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportPTicket());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $result = $this->service->publicFindOneByHash(sha1(uniqid()));
        $this->assertInstanceOf('Model_SupportPTicket', $result);
    }

    public function testPublicFindOneByHashNotFoundException(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->publicFindOneByHash(sha1(uniqid()));
        $this->assertInstanceOf('Model_SupportPTicket', $result);
    }

    public static function publicGetSearchQueryProvider(): array
    {
        return [
            [
                [
                    'search' => 'Query',
                    'id' => random_int(1, 100),
                    'status' => \Model_SupportPTicket::OPENED,
                    'name' => 'Name',
                    'email' => 'email@example.com',
                    'subject' => 'Subject',
                    'content' => 'Content',
                ],
            ],
            [
                [
                    'search' => random_int(1, 100),
                    'id' => random_int(1, 100),
                    'status' => \Model_SupportPTicket::OPENED,
                    'name' => 'Name',
                    'email' => 'email@example.com',
                    'subject' => 'Subject',
                    'content' => 'Content',
                ],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicGetSearchQueryProvider')]
    public function testPublicGetSearchQuery(array $data): void
    {
        $di = new \Pimple\Container();

        $this->service->setDi($di);

        [$query, $bindings] = $this->service->publicgetSearchQuery($data);
        $this->assertIsString($query);
        $this->assertIsArray($bindings);
    }

    public function testPublicCounter(): void
    {
        $arr = [
            \Model_SupportPTicket::OPENED => random_int(1, 100),
            \Model_SupportPTicket::ONHOLD => random_int(1, 100),
            \Model_SupportPTicket::CLOSED => random_int(1, 100),
        ];
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn($arr);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->publicCounter();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(array_sum($arr), $result['total']);
    }

    public function testPublicGetLatest(): void
    {
        $ticket = new \Model_SupportPTicket();
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$ticket, $ticket]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->publicGetLatest();
        $this->assertIsArray($result);
        $this->assertInstanceOf('Model_SupportPTicket', $result[0]);
    }

    public function testPublicCountByStatus(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->publicCountByStatus('open');
        $this->assertIsInt($result);
    }

    public function testPublicGetExpired(): void
    {
        $ticket = new \Model_SupportPTicket();
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$ticket, $ticket]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->publicGetExpired();
        $this->assertIsArray($result);
        $this->assertInstanceOf('Model_SupportPTicket', $result[0]);
    }

    public static function publicCloseTicketProvider(): array
    {
        return [
            [new \Model_Admin()],
            [new \Model_Guest()],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicCloseTicketProvider')]
    public function testPublicCloseTicket(\Model_Admin|\Model_Guest $identity): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['events_manager'] = $eventMock;
        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->publicCloseTicket($ticket, $identity);
        $this->assertTrue($result);
    }

    public function testPublicAutoClose(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->publicAutoClose($ticket);
        $this->assertTrue($result);
    }

    public function testPublicRm(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([new \Model_SupportPTicketMessage()]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $canned = new \Model_SupportPTicket();
        $canned->loadBean(new \DummyBean());

        $result = $this->service->publicRm($canned);
        $this->assertTrue($result);
    }

    public static function publicToApiArrayProvider(): array
    {
        $self = new ServiceTest('ServiceTest');

        return [
            [
                new \Model_SupportPTicketMessage(),
                $self->atLeastOnce(),
            ],
            [
                null,
                $self->never(),
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicToApiArrayProvider')]
    public function testPublicToApiArray(?\Model_SupportPTicketMessage $findOne, \PHPUnit\Framework\MockObject\Rule\InvokedAtLeastOnce|\PHPUnit\Framework\MockObject\Rule\InvokedCount $publicMessageGetAuthorDetailsCalled): void
    {
        $ticketMessages = [new \Model_SupportPTicketMessage(), new \Model_SupportPTicketMessage()];

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->any())
            ->method('findOne')
            ->willReturn($findOne);
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);
        $dbMock->expects($this->any())
            ->method('find')
            ->willReturn($ticketMessages);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['publicMessageToApiArray', 'publicMessageGetAuthorDetails'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicMessageToApiArray')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())->method('publicMessageGetAuthorDetails')
            ->willReturn(['name' => 'Name', 'email' => 'email#example.com']);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
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

    public function testPublicMessageGetAuthorDetailsAdmin(): void
    {
        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->id = random_int(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($admin);

        $di = new \Pimple\Container();
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

    public function testPublicMessageGetAuthorDetailsNotAdmin(): void
    {
        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->author_name = 'Name';
        $ticket->author_email = 'Email@example.com';

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($ticket);

        $di = new \Pimple\Container();
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

    public function testPublicMessageToApiArray(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['publicMessageGetAuthorDetails'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicMessageGetAuthorDetails')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $ticketMsg = new \Model_SupportPTicketMessage();
        $ticketMsg->loadBean(new \DummyBean());
        $ticketMsg->id = random_int(1, 100);

        $result = $serviceMock->publicMessageToApiArray($ticketMsg);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('author', $result);
    }

    public function testPublicTicketCreate(): void
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($message);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['events_manager'] = $eventMock;
        $di['tools'] = $toolsMock;
        $di['request'] = Request::createFromGlobals();
        $this->service->setDi($di);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->id = random_int(1, 100);

        $data = [
            'email' => 'email@example.com',
            'name' => 'Name',
            'message' => 'Message',
            'request' => 'Request',
            'subject' => 'Subject',
        ];

        $result = $this->service->publicTicketCreate($data, $admin);
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testPublicTicketUpdate(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());

        $data = [
            'support_helpdesk_id' => random_int(1, 100),
            'status' => \Model_SupportTicket::OPENED,
            'subject' => 'Subject',
            'priority' => random_int(1, 100),
        ];

        $result = $this->service->publicTicketUpdate($ticket, $data);
        $this->assertTrue($result);
    }

    public function testPublicTicketReply(): void
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($message);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['events_manager'] = $eventMock;
        $di['request'] = Request::createFromGlobals();
        $this->service->setDi($di);

        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $result = $this->service->publicTicketReply($ticket, $admin, 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testPublicTicketReplyForGuest(): void
    {
        $message = new \Model_SupportTicketMessage();
        $message->loadBean(new \DummyBean());

        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($message);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['events_manager'] = $eventMock;
        $di['request'] = Request::createFromGlobals();
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

    public function testHelpdeskUpdate(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $this->service->setDi($di);

        $helpdesk = new \Model_SupportHelpdesk();
        $helpdesk->loadBean(new \DummyBean());

        $data = [
            'name' => 'Name',
            'email' => 'email@example.com',
            'can_reopen' => 1,
            'close_after' => random_int(1, 100),
            'signature' => 'Signature',
        ];

        $result = $this->service->helpdeskUpdate($helpdesk, $data);
        $this->assertTrue($result);
    }

    public function testHelpdeskCreate(): void
    {
        $randId = random_int(1, 100);
        $helpDeskModel = new \Model_SupportHelpdesk();
        $helpDeskModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($helpDeskModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $this->service->setDi($di);

        $ticket = new \Model_SupportHelpdesk();
        $ticket->loadBean(new \DummyBean());

        $data = [
            'name' => 'Name',
            'email' => 'email@example.com',
            'can_reopen' => 1,
            'close_after' => random_int(1, 100),
            'signature' => 'Signature',
        ];

        $result = $this->service->helpdeskCreate($data);
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testCannedGetSearchQuery(): void
    {
        $di = new \Pimple\Container();

        $this->service->setDi($di);

        $data = [
            'search' => 'query',
        ];

        [$query, $bindings] = $this->service->cannedGetSearchQuery($data);
        $this->assertIsString($query);
        $this->assertIsArray($bindings);
    }

    public function testCannedGetGroupedPairs(): void
    {
        $pairs = [
            0 => [
                'id' => 1,
                'r_title' => 'R  Title',
                'c_title' => 'General',
            ],
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($pairs);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $expected = [
            'General' => [
                1 => 'R  Title',
            ],
        ];

        $result = $this->service->cannedGetGroupedPairs();
        $this->assertIsArray($result);
        $this->assertEquals($result, $expected);
    }

    public function testCannedRm(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $canned = new \Model_SupportPr();
        $canned->loadBean(new \DummyBean());

        $result = $this->service->cannedRm($canned);
        $this->assertTrue($result);
    }

    public function testCannedToApiArray(): void
    {
        $category = new \Model_SupportPrCategory();
        $category->loadBean(new \DummyBean());
        $category->id = random_int(1, 100);
        $category->title = 'General';

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($category);

        $di = new \Pimple\Container();
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

    public function testCannedToApiArrayCategotyNotFound(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $canned = new \Model_SupportPr();
        $canned->loadBean(new \DummyBean());

        $result = $this->service->cannedToApiArray($canned);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('category', $result);
        $this->assertEquals($result['category'], []);
    }

    public function testCannedCategoryGetPairs(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn([0 => 'General']);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $this->service->setDi($di);

        $note = new \Model_SupportTicketNote();
        $note->loadBean(new \DummyBean());

        $result = $this->service->cannedCategoryGetPairs();
        $this->assertIsArray($result);
    }

    public function testCannedCategoryRm(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $canned = new \Model_SupportPrCategory();
        $canned->loadBean(new \DummyBean());

        $result = $this->service->cannedCategoryRm($canned);
        $this->assertTrue($result);
    }

    public function testCannedCategoryToApiArray(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $canned = new \Model_SupportPrCategory();
        $canned->loadBean(new \DummyBean());

        $result = $this->service->cannedCategoryToApiArray($canned);
        $this->assertIsArray($result);
    }

    public function testCannedCreate(): void
    {
        $randId = random_int(1, 100);
        $helpDeskModel = new \Model_SupportHelpdesk();
        $helpDeskModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($helpDeskModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)
            ->onlyMethods(['checkLimits'])->getMock();
        $systemServiceMock->expects($this->atLeastOnce())->method('checkLimits')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $ticket = new \Model_SupportHelpdesk();
        $ticket->loadBean(new \DummyBean());

        $data = [
            'name' => 'Name',
            'email' => 'email@example.com',
            'can_reopen' => 1,
            'close_after' => random_int(1, 100),
            'signature' => 'Signature',
        ];

        $result = $this->service->cannedCreate($data, random_int(1, 100), 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testCannedUpdate(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(random_int(1, 100));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $this->service->setDi($di);

        $model = new \Model_SupportPr();
        $model->loadBean(new \DummyBean());

        $data = [
            'category_id' => random_int(1, 100),
            'title' => 'email@example.com',
            'content' => 1,
        ];

        $result = $this->service->cannedUpdate($model, $data);
        $this->assertTrue($result);
    }

    public function testCannedCategoryCreate(): void
    {
        $randId = random_int(1, 100);
        $supportPrCategoryModel = new \Model_SupportPrCategory();
        $supportPrCategoryModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($supportPrCategoryModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $data = [
            'name' => 'Name',
            'email' => 'email@example.com',
            'can_reopen' => 1,
            'close_after' => random_int(1, 100),
            'signature' => 'Signature',
        ];

        $result = $this->service->cannedCategoryCreate($data);
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testCannedCategoryUpdate(): void
    {
        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $model = new \Model_SupportPrCategory();
        $model->loadBean(new \DummyBean());

        $result = $this->service->cannedCategoryUpdate($model, 'Title');
        $this->assertTrue($result);
    }

    public function testNoteCreate(): void
    {
        $randId = random_int(1, 100);
        $supportPrCategoryModel = new \Model_SupportPrCategory();
        $supportPrCategoryModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($supportPrCategoryModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $data = [
            'name' => 'Name',
            'email' => 'email@example.com',
            'can_reopen' => 1,
            'close_after' => random_int(1, 100),
            'signature' => 'Signature',
        ];

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $result = $this->service->noteCreate($ticket, $admin, 'Note');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testTicketTaskComplete(): void
    {
        $randId = random_int(1, 100);
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->service->setDi($di);

        $model = new \Model_SupportTicket();
        $model->loadBean(new \DummyBean());

        $result = $this->service->ticketTaskComplete($model);
        $this->assertTrue($result);
    }

    public static function canClientSubmitNewTicketProvider(): array
    {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->client_id = 5;
        $ticket->created_at = date('Y-m-d H:i:s');

        $ticket2 = new \Model_SupportTicket();
        $ticket2->loadBean(new \DummyBean());
        $ticket2->client_id = 5;
        $ticket2->created_at = date('Y-m-d H:i:s', strtotime('-2 days'));

        return [
            [$ticket, 24, false], // Ticket is created today, exception should be thrown
            [null, 24, true], // No previously created tickets found, can submit a ticket
            [$ticket2, 24, true], // Last ticket submitted 2 days ago, can submit a ticket
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('canClientSubmitNewTicketProvider')]
    public function testCanClientSubmitNewTicket(?\Model_SupportTicket $ticket, int $hours, bool $expected): void
    {
        if (!$expected) {
            $this->expectException(\FOSSBilling\Exception::class);
        }
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($ticket);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);
        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 5;

        $config = ['wait_hours' => $hours];

        $result = $this->service->canClientSubmitNewTicket($client, $config);
        $this->assertTrue($result);
    }

    /*
    * Knowledge Base Tests.
    */

    public function testKbSearchArticles(): void
    {
        $service = new \Box\Mod\Support\Service();

        $willReturn = [
            'pages' => 5,
            'page' => 2,
            'per_page' => 2,
            'total' => 10,
            'list' => [],
        ];

        $di = new \Pimple\Container();

        $pager = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($willReturn);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 5;
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

    public function testKbFindActiveArticleById(): void
    {
        $service = new \Box\Mod\Support\Service();

        $model = new \Model_SupportKbArticle();
        $model->loadBean(new \DummyBean());
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbFindActiveArticleById(5);
        $this->assertInstanceOf('Model_SupportKbArticle', $result);
        $this->assertEquals($result, $model);
    }

    public function testKbFindActiveArticleBySlug(): void
    {
        $service = new \Box\Mod\Support\Service();

        $model = new \Model_SupportKbArticle();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbFindActiveArticleBySlug('slug');
        $this->assertInstanceOf('Model_SupportKbArticle', $result);
        $this->assertEquals($result, $model);
    }

    public function testKbFindActive(): void
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([]);
        $di = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbFindActive();
        $this->assertIsArray($result);
    }

    public function testKbHitView(): void
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(5);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $modelKb = new \Model_SupportKbArticle();
        $modelKb->loadBean(new \DummyBean());
        $modelKb->views = 10;

        $result = $service->kbHitView($modelKb);
        $this->assertNull($result);
    }

    public function testKbRm(): void
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('trash')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $modelKb = new \Model_SupportKbArticle();
        $modelKb->loadBean(new \DummyBean());
        $modelKb->id = 1;
        $modelKb->views = 10;

        $result = $service->kbRm($modelKb);
        $this->assertNull($result);
    }

    public static function kbToApiArrayProvider(): array
    {
        $model = new \Model_SupportKbArticle();
        $model->loadBean(new \DummyBean());
        $model->id = random_int(1, 100);
        $model->slug = 'article-slug';
        $model->title = 'Title';
        $model->views = random_int(1, 100);
        $model->content = 'Content';
        $model->created_at = '2013-01-01 12:00:00';
        $model->updated_at = '2014-01-01 12:00:00';
        $model->status = 'active';
        $model->kb_article_category_id = random_int(1, 100);

        $category = new \Model_SupportKbArticleCategory();
        $category->loadBean(new \DummyBean());
        $category->id = random_int(1, 100);
        $category->slug = 'category-slug';
        $category->title = 'category-title';

        return [
            [
                $model,
                [
                    'id' => $model->id,
                    'slug' => $model->slug,
                    'title' => $model->title,
                    'views' => $model->views,
                    'created_at' => $model->created_at,
                    'updated_at' => $model->updated_at,
                    'category' => [
                        'id' => $category->id,
                        'slug' => $category->slug,
                        'title' => $category->title,
                    ],
                    'status' => $model->status,
                ],
                false,
                null,
                $category,
            ],
            [
                $model,
                [
                    'id' => $model->id,
                    'slug' => $model->slug,
                    'title' => $model->title,
                    'views' => $model->views,
                    'created_at' => $model->created_at,
                    'updated_at' => $model->updated_at,
                    'category' => [
                        'id' => $category->id,
                        'slug' => $category->slug,
                        'title' => $category->title,
                    ],
                    'content' => $model->content,
                    'status' => $model->status,
                ],
                true,
                null,
                $category,
            ],
            [
                $model,
                [
                    'id' => $model->id,
                    'slug' => $model->slug,
                    'title' => $model->title,
                    'views' => $model->views,
                    'created_at' => $model->created_at,
                    'updated_at' => $model->updated_at,
                    'category' => [
                        'id' => $category->id,
                        'slug' => $category->slug,
                        'title' => $category->title,
                    ],
                    'content' => $model->content,
                    'status' => $model->status,
                    'kb_article_category_id' => $model->kb_article_category_id,
                ],
                true,
                new \Model_Admin(),
                $category,
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('kbToApiArrayProvider')]
    public function testKbToApiArray(\Model_SupportKbArticle $model, array $expected, bool $deep, ?\Model_Admin $identity, \Model_SupportKbArticleCategory $category): void
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($category);
        $di = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbToApiArray($model, $deep, $identity);
        $this->assertEquals($result, $expected);
    }

    public function testKbCreateArticle(): void
    {
        $service = new \Box\Mod\Support\Service();
        $randId = random_int(1, 100);
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);
        $model = new \Model_SupportKbArticle();
        $model->loadBean(new \DummyBean());
        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);

        $tools = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->onlyMethods(['slug'])->getMock();
        $tools->expects($this->atLeastOnce())
            ->method('slug')
            ->willReturn('article-slug');

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['tools'] = $tools;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $service->setDi($di);
        $result = $service->kbCreateArticle(random_int(1, 100), 'Title', 'Active', 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testKbUpdateArticle(): void
    {
        $service = new \Box\Mod\Support\Service();
        $randId = random_int(1, 100);

        $model = new \Model_SupportKbArticle();
        $model->loadBean(new \DummyBean());

        $kb_article_category_id = random_int(1, 100);
        $title = 'Title';
        $slug = 'article-slug';
        $status = 'active';
        $content = 'content';
        $views = random_int(1, 100);

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $service->setDi($di);
        $result = $service->kbUpdateArticle($randId, $kb_article_category_id, $title, $slug, $status, $content, $views);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testKbUpdateArticleNotFoundException(): void
    {
        $service = new \Box\Mod\Support\Service();
        $randId = random_int(1, 100);

        $kb_article_category_id = random_int(1, 100);
        $title = 'Title';
        $slug = 'article-slug';
        $status = 'active';
        $content = 'content';
        $views = random_int(1, 100);

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);
        $db->expects($this->never())
            ->method('store')
            ->willReturn($randId);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $result = $service->kbUpdateArticle($randId, $kb_article_category_id, $title, $slug, $status, $content, $views);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public static function kbCategoryGetSearchQueryProvider(): array
    {
        return [
            [
                [],
                'SELECT kac.*
                FROM support_kb_article_category kac
                LEFT JOIN support_kb_article ka ON kac.id  = ka.kb_article_category_id GROUP BY kac.id ORDER BY kac.title',
                [],
            ],
            [
                [
                    'article_status' => 'active',
                ],
                'SELECT kac.*
                 FROM support_kb_article_category kac
                 LEFT JOIN support_kb_article ka ON kac.id  = ka.kb_article_category_id
                 WHERE ka.status = :status GROUP BY kac.id ORDER BY kac.title',
                [
                    ':status' => 'active',
                ],
            ],
            [
                [
                    'q' => 'search query',
                ],
                'SELECT kac.*
                 FROM support_kb_article_category kac
                 LEFT JOIN support_kb_article ka ON kac.id  = ka.kb_article_category_id
                 WHERE (ka.title LIKE :title OR ka.content LIKE :content) GROUP BY kac.id ORDER BY kac.title',
                [
                    ':title' => '%search query%',
                    ':content' => '%search query%',
                ],
            ],
            [
                [
                    'q' => 'search query',
                    'article_status' => 'active',
                ],
                'SELECT kac.*
                 FROM support_kb_article_category kac
                 LEFT JOIN support_kb_article ka ON kac.id  = ka.kb_article_category_id
                 WHERE ka.status = :status AND (ka.title LIKE :title OR ka.content LIKE :content) GROUP BY kac.id ORDER BY kac.title',
                [
                    ':title' => '%search query%',
                    ':content' => '%search query%',
                    ':status' => 'active',
                ],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('kbCategoryGetSearchQueryProvider')]
    public function testKbCategoryGetSearchQuery(array $data, string $query, array $bindings): void
    {
        $service = new \Box\Mod\Support\Service();

        $di = new \Pimple\Container();

        $service->setDi($di);

        $result = $service->kbCategoryGetSearchQuery($data);

        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertEquals(trim((string) preg_replace('/\s+/', '', str_replace("\n", ' ', $result[0]))), trim((string) preg_replace('/\s+/', '', str_replace("\n", ' ', $query))));
        $this->assertEquals($result[1], $bindings);
    }

    public function testKbCategoryFindAll(): void
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbCategoryFindAll();
        $this->assertIsArray($result);
    }

    public function testKbCategoryGetPairs(): void
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbCategoryGetPairs();
        $this->assertIsArray($result);
    }

    public function testKbCategoryRm(): void
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(0);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $model = new \Model_SupportKbArticleCategory();
        $model->loadBean(new \DummyBean());
        $model->id = random_int(1, 100);
        $model->KbArticle = new \Model_SupportKbArticleCategory();
        $model->KbArticle->loadBean(new \DummyBean());

        $result = $service->kbCategoryRm($model);
        $this->assertTrue($result);
    }

    public function testKbCategoryRmHasArticlesException(): void
    {
        $service = new \Box\Mod\Support\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(1);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $model = new \Model_SupportKbArticleCategory();
        $model->loadBean(new \DummyBean());
        $model->id = random_int(1, 100);
        $model->KbArticle = new \Model_SupportKbArticle();
        $model->KbArticle->loadBean(new \DummyBean());

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $service->kbCategoryRm($model);
        $this->assertNull($result);
    }

    public function testKbCreateCategory(): void
    {
        $service = new \Box\Mod\Support\Service();

        $randId = random_int(1, 100);

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);
        $articleCategoryModel = new \Model_SupportKbArticleCategory();
        $articleCategoryModel->loadBean(new \DummyBean());

        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($articleCategoryModel);

        $tools = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->onlyMethods(['slug'])->getMock();
        $tools->expects($this->atLeastOnce())
            ->method('slug')
            ->willReturn('article-slug');

        $systemService = $this->getMockBuilder(\Box\Mod\System\Service::class)->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('checkLimits')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['tools'] = $tools;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);
        $service->setDi($di);

        $result = $service->kbCreateCategory('Title', 'Description');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testKbUpdateCategory(): void
    {
        $service = new \Box\Mod\Support\Service();
        $randId = random_int(1, 100);
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($randId);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $model = new \Model_SupportKbArticleCategory();
        $model->loadBean(new \DummyBean());
        $model->id = random_int(1, 100);

        $result = $service->kbUpdateCategory($model, 'New Title', 'new-title', 'Description');
        $this->assertTrue($result);
    }

    public function testKbFindCategoryById(): void
    {
        $service = new \Box\Mod\Support\Service();

        $model = new \Model_SupportKbArticleCategory();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbFindCategoryById(5);
        $this->assertInstanceOf('Model_SupportKbArticleCategory', $result);
        $this->assertEquals($result, $model);
    }

    public function testKbFindCategoryBySlug(): void
    {
        $service = new \Box\Mod\Support\Service();

        $model = new \Model_SupportKbArticleCategory();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->kbFindCategoryBySlug('slug');
        $this->assertInstanceOf('Model_SupportKbArticleCategory', $result);
        $this->assertEquals($result, $model);
    }
}
