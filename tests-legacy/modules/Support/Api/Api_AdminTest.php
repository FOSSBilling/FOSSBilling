<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Support\Api;
use PHPUnit\Framework\Attributes\DataProvider; 
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Api_AdminTest extends \BBTestCase
{
    protected ?\Box\Mod\Support\Api\Admin $adminApi;

    public function setUp(): void
    {
        $this->adminApi = new \Box\Mod\Support\Api\Admin();
    }

    public function testTicketGetList(): void
    {
        $simpleResultArr = [
            'list' => [
                ['id' => 1],
            ],
        ];
        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($simpleResultArr);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['getSearchQuery', 'toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSearchQuery')
            ->willReturn(['query', []]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $model = new \Model_SupportTicket();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = $this->getDi();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];
        $result = $this->adminApi->ticket_get_list($data);

        $this->assertIsArray($result);
    }

    public function testTicketGet(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->ticket_get($data);

        $this->assertIsArray($result);
    }

    public function testTicketUpdate(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketUpdate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketUpdate')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->ticket_update($data);

        $this->assertTrue($result);
    }

    public function testTicketMessageUpdate(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicketMessage());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketMessageUpdate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketMessageUpdate')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
            'content' => 'Content',
        ];
        $result = $this->adminApi->ticket_message_update($data);

        $this->assertTrue($result);
    }

    public function testTicketDelete(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['rm'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('rm')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->ticket_delete($data);

        $this->assertTrue($result);
    }

    public function testTicketReply(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketReply'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketReply')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
            'content' => 'Content',
        ];
        $result = $this->adminApi->ticket_reply($data);

        $this->assertIsInt($result);
    }

    public function testTicketClose(): void
    {

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($ticket);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['closeTicket'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('closeTicket')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->ticket_close($data);

        $this->assertTrue($result);
    }

    public function testTicketCloseAlreadyClosed(): void
    {

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->status = \Model_SupportTicket::CLOSED;

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($ticket);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['closeTicket'])->getMock();
        $serviceMock->expects($this->never())->method('closeTicket')
            ;

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->ticket_close($data);

        $this->assertTrue($result);
    }

    public function testTicketCreate(): void
    {

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $supportHelpdeskModel = new \Model_SupportHelpdesk();
        $supportHelpdeskModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->onConsecutiveCalls($clientModel, $supportHelpdeskModel));

        $randID = 1;
        $serviceMock = $this->createMock(\Box\Mod\Support\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('ticketCreateForAdmin')
            ->willReturn($randID);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'client_id' => 1,
            'content' => 'Content',
            'subject' => 'Subject',
            'support_helpdesk_id' => 1,
        ];
        $result = $this->adminApi->ticket_create($data);

        $this->assertIsInt($result);
        $this->assertEquals($randID, $result);
    }

    public function testBatchTicketAutoClose(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['getExpired', 'autoClose'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getExpired')
            ->willReturn([['id' => 1], ['id' => 2]]);
        $serviceMock->expects($this->atLeastOnce())->method('autoClose')
            ->willReturn(true);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->id = 1;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($ticket);

        $this->adminApi->setService($serviceMock);
        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->adminApi->setDi($di);
        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->batch_ticket_auto_close([]);

        $this->assertTrue($result);
    }

    public function testBatchTicketAutoCloseNotClosed(): void
    {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->id = 1;

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['getExpired', 'autoClose'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getExpired')
            ->willReturn([['id' => 1], ['id' => 2]]);
        $serviceMock->expects($this->atLeastOnce())->method('autoClose')
            ;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($ticket);

        $this->adminApi->setService($serviceMock);
        $di = $this->getDi();
        $di['logger'] = $this->createMock('Box_Log');
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);
        $result = $this->adminApi->batch_ticket_auto_close([]);

        $this->assertTrue($result);
    }

    public function testBatchPublicTicketAutoClose(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicGetExpired', 'publicAutoClose'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetExpired')
            ->willReturn([new \Model_SupportPTicket(), new \Model_SupportPTicket()]);
        $serviceMock->expects($this->atLeastOnce())->method('publicAutoClose')
            ->willReturn(true);

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->batch_public_ticket_auto_close([]);

        $this->assertTrue($result);
    }

    public function testBatchPublicTicketAutoCloseNotClosed(): void
    {
        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->id = 1;

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicGetExpired', 'publicAutoClose'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetExpired')
            ->willReturn([$ticket, $ticket]);
        $serviceMock->expects($this->atLeastOnce())->method('publicAutoClose')
            ;

        $this->adminApi->setService($serviceMock);
        $di = $this->getDi();
        $di['logger'] = $this->createMock('Box_Log');
        $this->adminApi->setDi($di);
        $result = $this->adminApi->batch_public_ticket_auto_close([]);

        $this->assertTrue($result);
    }

    public function testTicketGetStatuses(): void
    {
        $statuses = [
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        ];
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['getStatuses', 'counter'])->getMock();
        $serviceMock->expects($this->never())->method('getStatuses')
            ->willReturn($statuses);
        $serviceMock->expects($this->atLeastOnce())->method('counter')
            ->willReturn($statuses);

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->ticket_get_statuses([]);

        $this->assertEquals($result, $statuses);
    }

    public function testTicketGetStatusesTitlesSet(): void
    {
        $statuses = [
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        ];
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['getStatuses', 'counter'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getStatuses')
            ->willReturn($statuses);
        $serviceMock->expects($this->never())->method('counter')
            ->willReturn($statuses);

        $this->adminApi->setService($serviceMock);

        $data = [
            'titles' => true,
        ];
        $result = $this->adminApi->ticket_get_statuses($data);

        $this->assertEquals($result, $statuses);
    }

    public function testPublicTicketGetList(): void
    {
        $resultSet = [
            'list' => [
                0 => ['id' => 1],
            ],
        ];
        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($resultSet);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicGetSearchQuery', 'publicToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetSearchQuery')
            ->willReturn(['query', []]);
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->willReturn(['query', []]);

        $model = new \Model_SupportPTicket();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_DAtabase');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = $this->getDi();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];
        $result = $this->adminApi->public_ticket_get_list($data);

        $this->assertIsArray($result);
    }

    public function testPublicTicketCreate(): void
    {

        $randID = 1;
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicTicketCreate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketCreate')
            ->willReturn($randID);

        $di = $this->getDi();
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'name' => 'Name',
            'email' => 'email@example.com',
            'subject' => 'Subject',
            'message' => 'Message',
        ];

        $result = $this->adminApi->public_ticket_create($data);

        $this->assertIsInt($result);
        $this->assertEquals($randID, $result);
    }

    public function testPublicTicketGet(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPTicket());

        $randID = 1;
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->public_ticket_get($data);

        $this->assertIsArray($result);
    }

    public function testPublicTicketDelete(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicRm'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicRm')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->public_ticket_delete($data);

        $this->assertTrue($result);
    }

    public function testPublicTicketClose(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicCloseTicket'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicCloseTicket')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->public_ticket_close($data);

        $this->assertTrue($result);
    }

    public function testPublicTicketUpdate(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicTicketUpdate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketUpdate')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->public_ticket_update($data);

        $this->assertTrue($result);
    }

    public function testPublicTicketReply(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicTicketReply'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketReply')
            ->willReturn(1);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
            'content' => 'Content',
        ];
        $result = $this->adminApi->public_ticket_reply($data);

        $this->assertIsInt($result);
    }

    public function testPublicTicketGetStatuses(): void
    {
        $statuses = [
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        ];
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicGetStatuses', 'publicCounter'])->getMock();
        $serviceMock->expects($this->never())->method('publicGetStatuses')
            ->willReturn($statuses);
        $serviceMock->expects($this->atLeastOnce())->method('publicCounter')
            ->willReturn($statuses);

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->public_ticket_get_statuses([]);

        $this->assertEquals($result, $statuses);
    }

    public function testPublicTicketGetStatusesTitlesSet(): void
    {
        $statuses = [
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        ];
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicGetStatuses', 'publicCounter'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetStatuses')
            ->willReturn($statuses);
        $serviceMock->expects($this->never())->method('publicCounter')
            ->willReturn($statuses);

        $this->adminApi->setService($serviceMock);

        $data = [
            'titles' => true,
        ];
        $result = $this->adminApi->public_ticket_get_statuses($data);

        $this->assertEquals($result, $statuses);
    }

    public function testHelpdeksGetList(): void
    {
        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn([]);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskGetSearchQuery'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskGetSearchQuery')
            ->willReturn(['query', []]);

        $di = $this->getDi();
        $di['pager'] = $paginatorMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];
        $result = $this->adminApi->helpdesk_get_list($data);

        $this->assertIsArray($result);
    }

    public function testHelpdeksGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskGetPairs'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskGetPairs')
            ->willReturn([]);

        $this->adminApi->setService($serviceMock);

        $data = [];
        $result = $this->adminApi->helpdesk_get_pairs($data);

        $this->assertIsArray($result);
    }

    public function testHelpdeskGet(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportHelpdesk());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskToApiArray')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->helpdesk_get($data);

        $this->assertIsArray($result);
    }

    public function testHelpdeskUpdate(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportHelpdesk());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskUpdate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskUpdate')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->helpdesk_update($data);

        $this->assertTrue($result);
    }

    public function testHelpdeskCreate(): void
    {

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskCreate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskCreate')
            ->willReturn(1);

        $di = $this->getDi();
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->helpdesk_create($data);

        $this->assertIsInt($result);
    }

    public function testHelpdeskDelete(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportHelpdesk());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskRm'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskRm')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 'General',
        ];
        $result = $this->adminApi->helpdesk_delete($data);

        $this->assertTrue($result);
    }

    public function testCannedGetList(): void
    {
        $resultSet = [
            'list' => [
                0 => ['id' => 1],
            ],
        ];
        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($resultSet);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedGetSearchQuery', 'cannedToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedGetSearchQuery')
            ->willReturn(['query', []]);
        $serviceMock->expects($this->atLeastOnce())->method('cannedToApiArray')
            ->willReturn([]);

        $model = new \Model_SupportPr();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_DAtabase');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = $this->getDi();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];
        $result = $this->adminApi->canned_get_list($data);

        $this->assertIsArray($result);
    }

    public function testCannedPairs(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn([1 => 'Title']);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $data = [];
        $result = $this->adminApi->canned_pairs();

        $this->assertIsArray($result);
    }

    public function testCannedGet(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPr());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedToApiArray')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->canned_get($data);

        $this->assertIsArray($result);
    }

    public function testCannedDelete(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPr());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedRm'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedRm')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->canned_delete($data);

        $this->assertTrue($result);
    }

    public function testCannedCreate(): void
    {

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedCreate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCreate')
            ->willReturn(1);

        $di = $this->getDi();
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'title' => 'Title',
            'category_id' => random_int(1, 100),
            'content' => 'Content',
        ];
        $result = $this->adminApi->canned_create($data);

        $this->assertIsInt($result);
    }

    public function testCannedUpdate(): void
    {

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedUpdate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedUpdate')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPr());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 'Title',
        ];
        $result = $this->adminApi->canned_update($data);

        $this->assertTrue($result);
    }

    public function testCannedCategoryPairs(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn([1 => 'Category 1']);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 'Title',
        ];
        $result = $this->adminApi->canned_category_pairs($data);

        $this->assertIsArray($result);
    }

    public function testCannedCategoryGet(): void
    {

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPrCategory());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedCategoryToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryToApiArray')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->canned_category_get($data);

        $this->assertIsArray($result);
    }

    public function testCannedCategoryUpdate(): void
    {

        $supportCategory = new \Model_SupportPrCategory();
        $supportCategory->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($supportCategory);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedCategoryUpdate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryUpdate')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
            'title' => 'Updated Title',
        ];
        $result = $this->adminApi->canned_category_update($data);

        $this->assertTrue($result);
    }

    public function testCannedCategoryDelete(): void
    {

        $supportCategory = new \Model_SupportPrCategory();
        $supportCategory->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($supportCategory);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedCategoryRm'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryRm')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->canned_category_delete($data);

        $this->assertTrue($result);
    }

    public function testCannedCategoryCreate(): void
    {

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedCategoryCreate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryCreate')
            ->willReturn(1);

        $di = $this->getDi();
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'title' => 'Title',
        ];
        $result = $this->adminApi->canned_category_create($data);

        $this->assertIsInt($result);
    }

    public function testCannedNoteCreate(): void
    {

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['noteCreate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('noteCreate')
            ->willReturn(1);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicket());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'ticket_id' => 1,
            'note' => 'Note',
        ];
        $result = $this->adminApi->note_create($data);

        $this->assertIsInt($result);
    }

    public function testCannedNoteDelete(): void
    {

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['noteRm'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('noteRm')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicketNote());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->note_delete($data);

        $this->assertTrue($result);
    }

    public function testTaskComplete(): void
    {

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketTaskComplete'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketTaskComplete')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicket());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->task_complete($data);

        $this->assertTrue($result);
    }

    public function testBatchDelete(): void
    {
        $activityMock = $this->getMockBuilder(\Box\Mod\Support\Api\Admin::class)->onlyMethods(['ticket_delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->method('ticket_delete')->willReturn(true);


        $di = $this->getDi();
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
        $this->assertTrue($result);
    }

    public function testBatchDeletePublic(): void
    {
        $activityMock = $this->getMockBuilder(\Box\Mod\Support\Api\Admin::class)->onlyMethods(['public_ticket_delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->method('public_ticket_delete')->willReturn(true);


        $di = $this->getDi();
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_public(['ids' => [1, 2, 3]]);
        $this->assertTrue($result);
    }

    /*
    * Knowledge Base Tests.
    */

    public function testKbArticleGetList(): void
    {
        $di = $this->getDi();

        $adminApi = new \Box\Mod\Support\Api\Admin();
        $adminApi->setDi($di);

        $data = [
            'status' => 'status',
            'search' => 'search',
            'cat' => 'category',
        ];

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbSearchArticles'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbSearchArticles')
            ->willReturn(['list' => []]);

        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_get_list($data);
        $this->assertIsArray($result);
    }

    public function testKbArticleGet(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportKbArticle());

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $admin->id = 5;

        $di = $this->getDi();
        $di['loggedin_admin'] = $admin;
        $di['db'] = $db;
        
        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbToApiArray'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbToApiArray')
            ->willReturn([]);
        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_get($data);
        $this->assertIsArray($result);
    }

    public function testKbArticleGetNotFoundException(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

        $di = $this->getDi();
        $di['db'] = $db;
        
        $adminApi->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $adminApi->kb_article_get($data);
    }

    public function testKbArticleCreate(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'kb_article_category_id' => 1,
            'title' => 'Title',
        ];

        $id = 1;

        $di = $this->getDi();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCreateArticle'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCreateArticle')
            ->willReturn($id);
        $adminApi->setService($kbService);
        
        $adminApi->setDi($di);

        $result = $adminApi->kb_article_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($result, $id);
    }

    public function testKbArticleUpdate(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'id' => 1,
            'kb_article_category_id' => 1,
            'title' => 'Title',
            'slug' => 'article-slug',
            'status' => 'active',
            'content' => 'Content',
            'views' => 1,
        ];

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbUpdateArticle'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbUpdateArticle')
            ->willReturn(true);
        $di = $this->getDi();

        $adminApi->setDi($di);

        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_update($data);
        $this->assertTrue($result);
    }

    public function testKbArticleDelete(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportKbArticle());

        $di = $this->getDi();
        $di['db'] = $db;

        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbRm'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbRm')
            ;
        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_delete($data);
        $this->assertTrue($result);
    }

    public function testKbArticleDeleteNotFoundException(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

        $di = $this->getDi();
        $di['db'] = $db;

        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbRm'])->getMock();
        $kbService->expects($this->never())
            ->method('kbRm')
            ;
        $adminApi->setService($kbService);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $adminApi->kb_article_delete(['id' => 1]);
        $this->assertTrue($result);
    }

    public function testKbCategoryGetList(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $willReturn = [
            'pages' => 5,
            'page' => 2,
            'per_page' => 2,
            'total' => 10,
            'list' => [],
        ];

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryGetSearchQuery'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryGetSearchQuery')
            ->willReturn(['String', []]);

        $pager = $this->getMockBuilder(\FOSSBilling\Pagination::class)
            ->onlyMethods(['getPaginatedResultSet'])
            ->getMock();

        $pager->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($willReturn);

        $di = $this->getDi();
        $di['pager'] = $pager;

        $adminApi->setDi($di);
        $adminApi->setService($kbService);

        $result = $adminApi->kb_category_get_list([]);
        $this->assertIsArray($result);
        $this->assertEquals($result, $willReturn);
    }

    public function testKbCategoryGet(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportKbArticleCategory());

        $di = $this->getDi();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryToApiArray'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryToApiArray')
            ->willReturn([]);
        $adminApi->setService($kbService);

        $data = [
            'id' => 1,
        ];
        $result = $adminApi->kb_category_get($data);
        $this->assertIsArray($result);
    }

    public function testKbCategoryGetIdNotSetException(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = $this->createMock('Box_Database');
        $db->expects($this->never())
            ->method('findOne')
            ->willReturn(new \Model_SupportKbArticleCategory());

        $di = $this->getDi();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryToApiArray'])->getMock();
        $kbService->expects($this->never())
            ->method('kbCategoryToApiArray')
            ->willReturn([]);
        $adminApi->setService($kbService);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->validateRequiredParams($adminApi, 'kb_category_get', []);
        $result = $adminApi->kb_category_get([]);
        $this->assertIsArray($result);
    }

    public function testKbCategoryGetNotFoundException(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

        $di = $this->getDi();

        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();
        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryToApiArray'])->getMock();
        $kbService->expects($this->never())
            ->method('kbCategoryToApiArray')
            ->willReturn([]);
        $adminApi->setService($kbService);

        $data = [
            'id' => 1,
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $adminApi->kb_category_get($data);
        $this->assertIsArray($result);
    }

    public function testKbCategoryCreate(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCreateCategory'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCreateCategory')
            ->willReturn(1);
        $adminApi->setService($kbService);

        $data = [
            'title' => 'Title',
            'description' => 'Description',
        ];

        $di = $this->getDi();

        $adminApi->setDi($di);

        $result = $adminApi->kb_category_create($data);
        $this->assertIsInt($result);
    }

    public function testKbCategoryUpdate(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbUpdateCategory'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbUpdateCategory')
            ->willReturn(true);
        $adminApi->setService($kbService);

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportKbArticleCategory());

        $di = $this->getDi();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $data = [
            'id' => 1,
            'title' => 'Title',
            'slug' => 'category-slug',
            'description' => 'Description',
        ];

        $result = $adminApi->kb_category_update($data);
        $this->assertTrue($result);
    }

    public function testKbCategoryUpdateIdNotSet(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbUpdateCategory'])->getMock();
        $kbService->expects($this->never())
            ->method('kbUpdateCategory')
            ->willReturn(true);
        $adminApi->setService($kbService);

        $db = $this->createMock('Box_Database');
        $db->expects($this->never())
            ->method('findOne')
            ->willReturn(new \Model_SupportKbArticleCategory());

        $di = $this->getDi();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->validateRequiredParams($adminApi, 'kb_category_update', $data);
        $result = $adminApi->kb_category_update($data);
        $this->assertIsArray($result);
    }

    public function testKbCategoryUpdateNotFound(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbUpdateCategory'])->getMock();
        $kbService->expects($this->never())
            ->method('kbUpdateCategory')
            ->willReturn(true);
        $adminApi->setService($kbService);

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

        $di = $this->getDi();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $data = [
            'id' => 1,
            'title' => 'Title',
            'slug' => 'category-slug',
            'description' => 'Description',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $adminApi->kb_category_update($data);
        $this->assertIsArray($result);
    }

    public function testKbCategoryDelete(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryRm'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryRm')
            ->willReturn(true);
        $adminApi->setService($kbService);

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportKbArticleCategory());

        $di = $this->getDi();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $data = [
            'id' => 1,
        ];
        $result = $adminApi->kb_category_delete($data);
        $this->assertTrue($result);
    }

    public function testKbCategoryDeleteIdNotSet(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryRm'])->getMock();
        $kbService->expects($this->never())
            ->method('kbCategoryRm')
            ->willReturn(true);
        $adminApi->setService($kbService);

        $db = $this->createMock('Box_Database');
        $db->expects($this->never())
            ->method('findOne')
            ->willReturn(new \Model_SupportKbArticleCategory());

        $di = $this->getDi();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $data = [];
        $this->expectException(\FOSSBilling\Exception::class);
        $this->validateRequiredParams($adminApi, 'kb_category_delete', $data);
        $result = $adminApi->kb_category_delete($data);
        $this->assertIsArray($result);
    }

    public function testKbCategoryDeleteNotFound(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryRm'])->getMock();
        $kbService->expects($this->never())
            ->method('kbCategoryRm')
            ->willReturn(true);
        $adminApi->setService($kbService);

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

        $di = $this->getDi();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $data = [
            'id' => 1,
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $adminApi->kb_category_delete($data);
        $this->assertIsArray($result);
    }

    public function testKbCategoryGetPairs(): void
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryGetPairs'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryGetPairs')
            ->willReturn([]);
        $adminApi->setService($kbService);

        $result = $adminApi->kb_category_get_pairs([]);
        $this->assertIsArray($result);
    }
}