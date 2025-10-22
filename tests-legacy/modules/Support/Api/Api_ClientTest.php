<?php

namespace Box\Tests\Mod\Support\Api;

class Api_ClientTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Support\Api\Client
     */
    protected $clientApi;

    public function setup(): void
    {
        $this->clientApi = new \Box\Mod\Support\Api\Client();
    }

    public function testTicketGetList(): void
    {
        $simpleResultArr = [
            'list' => [
                ['id' => 1],
            ],
        ];
        $paginatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($simpleResultArr);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['getSearchQuery', 'toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSearchQuery')
            ->willReturn(['query', []]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $model = new \Model_SupportTicket();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data = [];
        $result = $this->clientApi->ticket_get_list($data);

        $this->assertIsArray($result);
    }

    public function testTicketGet(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['findOneByClient', 'toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('findOneByClient')
            ->willReturn(new \Model_SupportTicket());
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data = [
            'id' => random_int(1, 100),
        ];
        $result = $this->clientApi->ticket_get($data);

        $this->assertIsArray($result);
    }

    public function testHelpdeskGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskGetPairs'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskGetPairs')
            ->willReturn([0 => 'General']);

        $this->clientApi->setService($serviceMock);

        $result = $this->clientApi->helpdesk_get_pairs();

        $this->assertIsArray($result);
    }

    public function testTicketCreate(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketCreateForClient'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketCreateForClient')
            ->willReturn(random_int(1, 100));

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportHelpdesk());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data = [
            'content' => 'Content',
            'subject' => 'Subject',
            'support_helpdesk_id' => random_int(1, 100),
        ];
        $result = $this->clientApi->ticket_create($data);

        $this->assertIsInt($result);
    }

    public function testTicketReply(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['canBeReopened', 'ticketReply'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('canBeReopened')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('ticketReply')
            ->willReturn(random_int(1, 100));

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportTicket());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data = [
            'content' => 'Content',
            'id' => random_int(1, 100),
        ];
        $result = $this->clientApi->ticket_reply($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testTicketReplyCanNotBeReopenedException(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['canBeReopened', 'ticketReply'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('canBeReopened')
            ->willReturn(false);
        $serviceMock->expects($this->never())->method('ticketReply')
            ->willReturn(random_int(1, 100));

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportTicket());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data = [
            'content' => 'Content',
            'id' => random_int(1, 100),
        ];
        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->clientApi->ticket_reply($data);

        $this->assertIsInt($result);
    }

    public function testTicketClose(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Support\Service::class)
            ->onlyMethods(['findOneByClient', 'closeTicket'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('findOneByClient')
            ->willReturn(new \Model_SupportTicket());
        $serviceMock->expects($this->atLeastOnce())->method('closeTicket')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = random_int(1, 100);

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data = [
            'content' => 'Content',
            'id' => random_int(1, 100),
        ];
        $result = $this->clientApi->ticket_close($data);

        $this->assertTrue($result);
    }
}
