<?php
namespace Box\Tests\Mod\Support\Api;

class Api_AdminTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Support\Api\Admin
     */
    protected $adminApi = null;

    public function setup(): void
    {
        $this->adminApi = new \Box\Mod\Support\Api\Admin();
    }

    public function testTicket_get_list()
    {
        $simpleResultArr = array(
            'list' => array(
                array('id' => 1),
            ),
        );
        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getAdvancedResultSet')
            ->will($this->returnValue($simpleResultArr));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('getSearchQuery', 'toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSearchQuery')
            ->will($this->returnValue(array('query', array())));
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $model = new \Model_SupportTicket();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di          = new \Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['db']    = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array();
        $result = $this->adminApi->ticket_get_list($data);

        $this->assertIsArray($result);
    }

    public function testTicket_get()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $this->adminApi->ticket_get($data);

        $this->assertIsArray($result);
    }

    public function testTicket_update()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('ticketUpdate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketUpdate')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $this->adminApi->ticket_update($data);

        $this->assertTrue($result);
    }

    public function testTicket_message_update()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicketMessage()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('ticketMessageUpdate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketMessageUpdate')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id'      => rand(1, 100),
            'content' => 'Content'
        );
        $result = $this->adminApi->ticket_message_update($data);

        $this->assertTrue($result);
    }

    public function testTicket_delete()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('rm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('rm')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->ticket_delete($data);

        $this->assertTrue($result);
    }

    public function testTicket_reply()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('ticketReply'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketReply')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id'      => rand(1, 100),
            'content' => 'Content'
        );
        $result = $this->adminApi->ticket_reply($data);

        $this->assertTrue($result);
    }

    public function testTicket_close()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($ticket));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('closeTicket'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('closeTicket')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->ticket_close($data);

        $this->assertTrue($result);
    }

    public function testTicket_closeAlreadyClosed()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->status = \Model_SupportTicket::CLOSED;

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($ticket));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('closeTicket'))->getMock();
        $serviceMock->expects($this->never())->method('closeTicket')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->ticket_close($data);

        $this->assertTrue($result);
    }

    public function testTicket_create()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $supportHelpdeskModel = new \Model_SupportHelpdesk();
        $supportHelpdeskModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->onConsecutiveCalls($clientModel, $supportHelpdeskModel));

        $randID      = rand(1, 100);
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketCreateForAdmin')
            ->will($this->returnValue($randID));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'client_id'           => rand(1, 100),
            'content'             => 'Content',
            'subject'             => 'Subject',
            'support_helpdesk_id' => rand(1, 100),
        );
        $result = $this->adminApi->ticket_create($data);

        $this->assertIsInt($result);
        $this->assertEquals($randID, $result);
    }

    public function testBatch_ticket_auto_close()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('getExpired', 'autoClose'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getExpired')
            ->will($this->returnValue(array(array('id' => 1), array('id' => 2))));
        $serviceMock->expects($this->atLeastOnce())->method('autoClose')
            ->will($this->returnValue(true));

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->id = rand(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($ticket));

        $this->adminApi->setService($serviceMock);
        $di           = new \Pimple\Container();
        $di['db']     = $dbMock;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->adminApi->setDi($di);
        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->batch_ticket_auto_close(array());

        $this->assertTrue($result);
    }

    public function testBatch_ticket_auto_closeNotClosed()
    {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('getExpired', 'autoClose'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getExpired')
            ->will($this->returnValue(array(array('id' => 1), array('id' => 2))));
        $serviceMock->expects($this->atLeastOnce())->method('autoClose')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($ticket));


        $this->adminApi->setService($serviceMock);
        $di           = new \Pimple\Container();
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);
        $result = $this->adminApi->batch_ticket_auto_close(array());

        $this->assertTrue($result);
    }

    public function testBatch_public_ticket_auto_close()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicGetExpired', 'publicAutoClose'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetExpired')
            ->will($this->returnValue(array(new \Model_SupportPTicket(), new \Model_SupportPTicket())));
        $serviceMock->expects($this->atLeastOnce())->method('publicAutoClose')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->batch_public_ticket_auto_close(array());

        $this->assertTrue($result);
    }

    public function testBatch_public_ticket_auto_closeNotClosed()
    {
        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \DummyBean());
        $ticket->id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicGetExpired', 'publicAutoClose'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetExpired')
            ->will($this->returnValue(array($ticket, $ticket)));
        $serviceMock->expects($this->atLeastOnce())->method('publicAutoClose')
            ->will($this->returnValue(null));

        $this->adminApi->setService($serviceMock);
        $di           = new \Pimple\Container();
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $this->adminApi->setDi($di);
        $result = $this->adminApi->batch_public_ticket_auto_close(array());

        $this->assertTrue($result);
    }

    public function testTicket_get_statuses()
    {
        $statuses    = array(
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        );
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('getStatuses', 'counter'))->getMock();
        $serviceMock->expects($this->never())->method('getStatuses')
            ->will($this->returnValue($statuses));
        $serviceMock->expects($this->atLeastOnce())->method('counter')
            ->will($this->returnValue($statuses));

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->ticket_get_statuses(array());

        $this->assertEquals($result, $statuses);
    }

    public function testTicket_get_statusesTitlesSet()
    {
        $statuses    = array(
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        );
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('getStatuses', 'counter'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getStatuses')
            ->will($this->returnValue($statuses));
        $serviceMock->expects($this->never())->method('counter')
            ->will($this->returnValue($statuses));

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'titles' => true
        );
        $result = $this->adminApi->ticket_get_statuses($data);

        $this->assertEquals($result, $statuses);
    }

    public function testPublic_ticket_get_list()
    {
        $resultSet = array(
            'list' => array(
                0 => array('id' => 1),
            )
        );
        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getAdvancedResultSet')
            ->will($this->returnValue($resultSet));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicGetSearchQuery', 'publicToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetSearchQuery')
            ->will($this->returnValue(array('query', array())));
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue(array('query', array())));

        $model = new \Model_SupportPTicket();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_DAtabase')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di          = new \Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['db']    = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array();
        $result = $this->adminApi->public_ticket_get_list($data);

        $this->assertIsArray($result);
    }

    public function testPublic_ticket_create()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $randID      = rand(1, 100);
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicTicketCreate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketCreate')
            ->will($this->returnValue($randID));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'name'    => 'Name',
            'email'   => 'email@example.com',
            'subject' => 'Subject',
            'message' => 'Message'
        );
        $result = $this->adminApi->public_ticket_create($data);

        $this->assertIsInt($result);
        $this->assertEquals($randID, $result);
    }

    public function testPublic_ticket_get()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPTicket()));

        $randID      = rand(1, 100);
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue(array()));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->public_ticket_get($data);

        $this->assertIsArray($result);
    }

    public function testPublic_ticket_delete()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicRm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicRm')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->public_ticket_delete($data);

        $this->assertTrue($result);
    }

    public function testPublic_ticket_close()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicCloseTicket'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicCloseTicket')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->public_ticket_close($data);

        $this->assertTrue($result);
    }

    public function testPublic_ticket_update()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicTicketUpdate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketUpdate')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->public_ticket_update($data);

        $this->assertTrue($result);
    }

    public function testPublic_ticket_reply()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicTicketReply'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketReply')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id'      => rand(1, 100),
            'content' => 'Content'
        );
        $result = $this->adminApi->public_ticket_reply($data);

        $this->assertTrue($result);
    }

    public function testPublic_ticket_get_statuses()
    {
        $statuses    = array(
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        );
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicGetStatuses', 'publicCounter'))->getMock();
        $serviceMock->expects($this->never())->method('publicGetStatuses')
            ->will($this->returnValue($statuses));
        $serviceMock->expects($this->atLeastOnce())->method('publicCounter')
            ->will($this->returnValue($statuses));

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->public_ticket_get_statuses(array());

        $this->assertEquals($result, $statuses);
    }

    public function testPublic_ticket_get_statusesTitlesSet()
    {
        $statuses    = array(
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        );
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicGetStatuses', 'publicCounter'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetStatuses')
            ->will($this->returnValue($statuses));
        $serviceMock->expects($this->never())->method('publicCounter')
            ->will($this->returnValue($statuses));

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'titles' => true
        );
        $result = $this->adminApi->public_ticket_get_statuses($data);

        $this->assertEquals($result, $statuses);
    }

    public function testHelpdeks_get_list()
    {
        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue(array()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('helpdeskGetSearchQuery'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskGetSearchQuery')
            ->will($this->returnValue(array('query', array())));

        $di          = new \Pimple\Container();
        $di['pager'] = $paginatorMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array();
        $result = $this->adminApi->helpdesk_get_list($data);

        $this->assertIsArray($result);
    }

    public function testHelpdeks_get_pairs()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('helpdeskGetPairs'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskGetPairs')
            ->will($this->returnValue(array()));

        $this->adminApi->setService($serviceMock);

        $data   = array();
        $result = $this->adminApi->helpdesk_get_pairs($data);

        $this->assertIsArray($result);
    }

    public function testHelpdesk_get()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportHelpdesk()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('helpdeskToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskToApiArray')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->helpdesk_get($data);

        $this->assertTrue($result);
    }

    public function testHelpdesk_update()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportHelpdesk()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('helpdeskUpdate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskUpdate')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->helpdesk_update($data);

        $this->assertTrue($result);
    }

    public function testHelpdesk_create()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('helpdeskCreate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskCreate')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->helpdesk_create($data);

        $this->assertTrue($result);
    }

    public function testHelpdesk_delete()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportHelpdesk()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('helpdeskRm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskRm')
            ->will($this->returnValue(true));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => 'General',
        );
        $result = $this->adminApi->helpdesk_delete($data);

        $this->assertTrue($result);
    }

    public function testCanned_get_list()
    {
        $resultSet = array(
            'list' => array(
                0 => array('id' => 1),
            )
        );
        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue($resultSet));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('cannedGetSearchQuery', 'cannedToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedGetSearchQuery')
            ->will($this->returnValue(array('query', array())));
        $serviceMock->expects($this->atLeastOnce())->method('cannedToApiArray')
            ->will($this->returnValue(array()));

        $model = new \Model_SupportPr();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_DAtabase')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di          = new \Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['db']    = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array();
        $result = $this->adminApi->canned_get_list($data);

        $this->assertIsArray($result);
    }

    public function testCannedPairs()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue(array(1 => 'Title')));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $data   = array();
        $result = $this->adminApi->canned_pairs($data);

        $this->assertIsArray($result);
    }

    public function testCanned_get()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPr()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('cannedToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedToApiArray')
            ->will($this->returnValue(array()));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->canned_get($data);

        $this->assertIsArray($result);
    }

    public function testCanned_delete()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPr()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('cannedRm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedRm')
            ->will($this->returnValue(array()));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->canned_delete($data);

        $this->assertIsArray($result);
    }

    public function testCanned_create()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('cannedCreate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCreate')
            ->will($this->returnValue(rand(1, 100)));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'title'       => 'Title',
            'category_id' => 'Title',
            'content'     => 'Content'
        );
        $result = $this->adminApi->canned_create($data);

        $this->assertIsInt($result);
    }

    public function testCanned_update()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('cannedUpdate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedUpdate')
            ->will($this->returnValue(rand(1, 100)));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPr()));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => 'Title',
        );
        $result = $this->adminApi->canned_update($data);

        $this->assertIsInt($result);
    }


    public function testCanned_category_pairs()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue(array(1 => 'Category 1')));

        $di       = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => 'Title',
        );
        $result = $this->adminApi->canned_category_pairs($data);

        $this->assertIsArray($result);
    }

    public function testCanned_category_get()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPrCategory()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('cannedCategoryToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryToApiArray')
            ->will($this->returnValue(array()));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->canned_category_get($data);

        $this->assertIsArray($result);
    }

    public function testCanned_category_update()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $supportCategory = new \Model_SupportPrCategory();
        $supportCategory->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($supportCategory));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('cannedCategoryUpdate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryUpdate')
            ->will($this->returnValue(array()));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->canned_category_update($data);

        $this->assertIsArray($result);
    }

    public function testCanned_category_delete()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $supportCategory = new \Model_SupportPrCategory();
        $supportCategory->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($supportCategory));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('cannedCategoryRm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryRm')
            ->will($this->returnValue(array()));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->canned_category_delete($data);

        $this->assertIsArray($result);
    }

    public function testCanned_category_create()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('cannedCategoryCreate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryCreate')
            ->will($this->returnValue(array()));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'title' => 'Title',
        );
        $result = $this->adminApi->canned_category_create($data);

        $this->assertIsArray($result);
    }

    public function testCanned_note_create()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('noteCreate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('noteCreate')
            ->will($this->returnValue(array()));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'ticket_id' => rand(1, 100),
            'note'      => 'Note',
        );
        $result = $this->adminApi->note_create($data);

        $this->assertIsArray($result);
    }

    public function testCanned_note_delete()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('noteRm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('noteRm')
            ->will($this->returnValue(array()));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicketNote()));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->note_delete($data);

        $this->assertIsArray($result);
    }

    public function testTask_complete()
    {
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('ticketTaskComplete'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketTaskComplete')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $this->adminApi->task_complete($data);

        $this->assertTrue($result);
    }

    public function testBatch_delete()
    {
        $activityMock = $this->getMockBuilder('\Box\Mod\Support\Api\Admin')->onlyMethods(array('ticket_delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->method('ticket_delete')->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(array('ids' => array(1, 2, 3)));
        $this->assertEquals(true, $result);
    }

    public function testBatch_delete_public()
    {
        $activityMock = $this->getMockBuilder('\Box\Mod\Support\Api\Admin')->onlyMethods(array('public_ticket_delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->method('public_ticket_delete')->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_public(array('ids' => array(1, 2, 3)));
        $this->assertEquals(true, $result);
    }

    /*
    * Knowledge Base Tests.
    */

    public function testKb_article_get_list()
    {
        $di = new \Pimple\Container();

        $adminApi = new \Box\Mod\Support\Api\Admin();
        $adminApi->setDi($di);

        $data = array(
            'status' => 'status',
            'search' => 'search',
            'cat'    => 'category'
        );

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbSearchArticles'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbSearchArticles')
            ->will($this->returnValue(array('list' => array())));

        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_get_list($data);
        $this->assertIsArray($result);

    }

    public function testKb_article_get()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = array(
            'id' => rand(1, 100),
        );

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(new \Model_SupportKbArticle()));

        $admin     = new \Model_Admin();
        $admin->loadBean(new \DummyBean());

        $admin->id = 5;

        $di                   = new \Pimple\Container();
        $di['loggedin_admin'] = $admin;
        $di['db']             = $db;
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbToApiArray'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbToApiArray')
            ->will($this->returnValue(array()));
        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_get($data);
        $this->assertIsArray($result);
    }

    public function testKb_article_getNotFoundException()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = array(
            'id' => rand(1, 100),
        );

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(false));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $adminApi->kb_article_get($data);
    }

    public function testKb_article_create()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = array(
            'kb_article_category_id' => rand(1, 100),
            'title'                  => 'Title',
        );

        $id = rand(1, 100);

        $di = new \Pimple\Container();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbCreateArticle'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCreateArticle')
            ->will($this->returnValue($id));
        $adminApi->setService($kbService);
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $result = $adminApi->kb_article_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($result, $id);
    }

    public function testKb_article_update()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = array(
            "id"                     => rand(1, 100),
            "kb_article_category_id" => rand(1, 100),
            "title"                  => "Title",
            "slug"                   => "article-slug",
            "status"                 => "active",
            "content"                => "Content",
            "views"                  => rand(1, 100),
        );

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbUpdateArticle'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbUpdateArticle')
            ->will($this->returnValue(true));
        $di = new \Pimple\Container();

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $adminApi->setDi($di);

        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_update($data);
        $this->assertTrue($result);
    }

    public function testKb_article_delete()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = array(
            "id" => rand(1, 100),
        );

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(new \Model_SupportKbArticle()));

        $di       = new \Pimple\Container();
        $di['db'] = $db;

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbRm'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbRm')
            ->will($this->returnValue(true));
        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_delete($data);
        $this->assertTrue($result);
    }

    public function testKb_article_deleteNotFoundException()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(false));

        $di       = new \Pimple\Container();
        $di['db'] = $db;

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbRm'))->getMock();
        $kbService->expects($this->never())
            ->method('kbRm')
            ->will($this->returnValue(true));
        $adminApi->setService($kbService);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $adminApi->kb_article_delete(array('id' => rand(1, 100)));
        $this->assertTrue($result);
    }

    public function testKb_category_get_list()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $willReturn = array(
            "pages"    => 5,
            "page"     => 2,
            "per_page" => 2,
            "total"    => 10,
            "list"     => array(),
        );

        $pager = $this->getMockBuilder('Box_Pagination')->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('getAdvancedResultSet')
            ->will($this->returnValue($willReturn));

        $di          = new \Pimple\Container();
        $di['pager'] = $pager;

        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbCategoryGetSearchQuery'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryGetSearchQuery')
            ->will($this->returnValue(true));
        $adminApi->setService($kbService);

        $result = $adminApi->kb_category_get_list(array());
        $this->assertIsArray($result);
        $this->assertEquals($result, $willReturn);
    }

    public function testKb_category_get()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbCategoryToApiArray'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryToApiArray')
            ->will($this->returnValue(array()));
        $adminApi->setService($kbService);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $adminApi->kb_category_get($data);
        $this->assertIsArray($result);
    }

    public function testKb_category_getIdNotSetException()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->never())
            ->method('findOne')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Category ID not passed'));
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbCategoryToApiArray'))->getMock();
        $kbService->expects($this->never())
            ->method('kbCategoryToApiArray')
            ->will($this->returnValue(array()));
        $adminApi->setService($kbService);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $adminApi->kb_category_get(array());
        $this->assertIsArray($result);
    }

    public function testKb_category_getNotFoundException()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(false));

        $di       = new \Pimple\Container();
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $di['db'] = $db;
        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbCategoryToApiArray'))->getMock();
        $kbService->expects($this->never())
            ->method('kbCategoryToApiArray')
            ->will($this->returnValue(array()));
        $adminApi->setService($kbService);

        $data = array(
            'id' => rand(1, 100)
        );

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $adminApi->kb_category_get($data);;
        $this->assertIsArray($result);
    }

    public function testKb_category_create()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbCreateCategory'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCreateCategory')
            ->will($this->returnValue(array()));
        $adminApi->setService($kbService);

        $data = array(
            'title'       => 'Title',
            'description' => 'Description',
        );

        $di = new \Pimple\Container();

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $result = $adminApi->kb_category_create($data);
        $this->assertIsArray($result);
    }

    public function testKb_category_update()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbUpdateCategory'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbUpdateCategory')
            ->will($this->returnValue(array()));
        $adminApi->setService($kbService);

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));

        $di       = new \Pimple\Container();
        $di['db'] = $db;

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;

        $adminApi->setDi($di);

        $data = array(
            'id'          => rand(1, 100),
            'title'       => 'Title',
            'slug'        => 'category-slug',
            'description' => 'Description',
        );

        $result = $adminApi->kb_category_update($data);
        $this->assertIsArray($result);
    }

    public function testKb_category_updateIdNotSet()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbUpdateCategory'))->getMock();
        $kbService->expects($this->never())
            ->method('kbUpdateCategory')
            ->will($this->returnValue(array()));
        $adminApi->setService($kbService);

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->never())
            ->method('findOne')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Category ID not passed'));
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $data = array();

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $adminApi->kb_category_update($data);
        $this->assertIsArray($result);
    }

    public function testKb_category_updateNotFound()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbUpdateCategory'))->getMock();
        $kbService->expects($this->never())
            ->method('kbUpdateCategory')
            ->will($this->returnValue(array()));
        $adminApi->setService($kbService);

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(false));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);


        $data = array(
            'id'          => rand(1, 100),
            'title'       => 'Title',
            'slug'        => 'category-slug',
            'description' => 'Description',
        );

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $adminApi->kb_category_update($data);
        $this->assertIsArray($result);
    }

    public function testKb_category_delete()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbCategoryRm'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryRm')
            ->will($this->returnValue(array()));
        $adminApi->setService($kbService);


        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $data   = array(
            'id' => rand(1, 100),
        );
        $result = $adminApi->kb_category_delete($data);
        $this->assertIsArray($result);
    }

    public function testKb_category_deleteIdNotSet()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbCategoryRm'))->getMock();
        $kbService->expects($this->never())
            ->method('kbCategoryRm')
            ->will($this->returnValue(array()));
        $adminApi->setService($kbService);

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->never())
            ->method('findOne')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Category ID not passed'));
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $data   = array();
        $this->expectException(\FOSSBilling\Exception::class);
        $result = $adminApi->kb_category_delete($data);
        $this->assertIsArray($result);
    }

    public function testKb_category_deleteNotFound()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbCategoryRm'))->getMock();
        $kbService->expects($this->never())
            ->method('kbCategoryRm')
            ->will($this->returnValue(array()));
        $adminApi->setService($kbService);


        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(false));

        $di       = new \Pimple\Container();
        $di['db'] = $db;
        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $adminApi->setDi($di);

        $data   = array(
            'id' => rand(1, 100)
        );

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $adminApi->kb_category_delete($data);
        $this->assertIsArray($result);
    }

    public function testKb_category_get_pairs()
    {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbCategoryGetPairs'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryGetPairs')
            ->will($this->returnValue(array()));
        $adminApi->setService($kbService);

        $result = $adminApi->kb_category_get_pairs(array());
        $this->assertIsArray($result);
    }
}
