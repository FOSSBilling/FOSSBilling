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
            ->setMethods(array('getSearchQuery', 'toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSearchQuery')
            ->will($this->returnValue(array('query', array())));
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $model = new \Model_SupportTicket();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di          = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['db']    = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array();
        $result = $this->adminApi->ticket_get_list($data);

        $this->assertIsArray($result);
    }

    public function testTicket_get()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('ticketUpdate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketUpdate')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicketMessage()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('ticketMessageUpdate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketMessageUpdate')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('rm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('rm')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('ticketReply'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketReply')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($ticket));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('closeTicket'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('closeTicket')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());
        $ticket->status = \Model_SupportTicket::CLOSED;

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($ticket));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('closeTicket'))->getMock();
        $serviceMock->expects($this->never())->method('closeTicket')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $supportHelpdeskModel = new \Model_SupportHelpdesk();
        $supportHelpdeskModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->onConsecutiveCalls($clientModel, $supportHelpdeskModel));

        $randID      = rand(1, 100);
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketCreateForAdmin')
            ->will($this->returnValue($randID));

        $di              = new \Box_Di();
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
            ->setMethods(array('getExpired', 'autoClose'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getExpired')
            ->will($this->returnValue(array(array('id' => 1), array('id' => 2))));
        $serviceMock->expects($this->atLeastOnce())->method('autoClose')
            ->will($this->returnValue(true));

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \RedBeanPHP\OODBBean());
        $ticket->id = rand(1, 100);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($ticket));

        $this->adminApi->setService($serviceMock);
        $di           = new \Box_Di();
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
        $ticket->loadBean(new \RedBeanPHP\OODBBean());
        $ticket->id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('getExpired', 'autoClose'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getExpired')
            ->will($this->returnValue(array(array('id' => 1), array('id' => 2))));
        $serviceMock->expects($this->atLeastOnce())->method('autoClose')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($ticket));


        $this->adminApi->setService($serviceMock);
        $di           = new \Box_Di();
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['db']     = $dbMock;
        $this->adminApi->setDi($di);
        $result = $this->adminApi->batch_ticket_auto_close(array());

        $this->assertTrue($result);
    }

    public function testBatch_public_ticket_auto_close()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('publicGetExpired', 'publicAutoClose'))->getMock();
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
        $ticket->loadBean(new \RedBeanPHP\OODBBean());
        $ticket->id = rand(1, 100);

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('publicGetExpired', 'publicAutoClose'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetExpired')
            ->will($this->returnValue(array($ticket, $ticket)));
        $serviceMock->expects($this->atLeastOnce())->method('publicAutoClose')
            ->will($this->returnValue(null));

        $this->adminApi->setService($serviceMock);
        $di           = new \Box_Di();
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
            ->setMethods(array('getStatuses', 'counter'))->getMock();
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
            ->setMethods(array('getStatuses', 'counter'))->getMock();
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
            ->setMethods(array('publicGetSearchQuery', 'publicToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetSearchQuery')
            ->will($this->returnValue(array('query', array())));
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue(array('query', array())));

        $model = new \Model_SupportPTicket();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_DAtabase')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di          = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['db']    = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array();
        $result = $this->adminApi->public_ticket_get_list($data);

        $this->assertIsArray($result);
    }

    public function testPublic_ticket_create()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $randID      = rand(1, 100);
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('publicTicketCreate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketCreate')
            ->will($this->returnValue($randID));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPTicket()));

        $randID      = rand(1, 100);
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('publicToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue(array()));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('publicRm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicRm')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('publicCloseTicket'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicCloseTicket')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('publicTicketUpdate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketUpdate')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPTicket()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('publicTicketReply'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketReply')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
            ->setMethods(array('publicGetStatuses', 'publicCounter'))->getMock();
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
            ->setMethods(array('publicGetStatuses', 'publicCounter'))->getMock();
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
            ->setMethods(array('helpdeskGetSearchQuery'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskGetSearchQuery')
            ->will($this->returnValue(array('query', array())));

        $di          = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array();
        $result = $this->adminApi->helpdesk_get_list($data);

        $this->assertIsArray($result);
    }

    public function testHelpdeks_get_pairs()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('helpdeskGetPairs'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskGetPairs')
            ->will($this->returnValue(array()));

        $this->adminApi->setService($serviceMock);

        $data   = array();
        $result = $this->adminApi->helpdesk_get_pairs($data);

        $this->assertIsArray($result);
    }

    public function testHelpdesk_get()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportHelpdesk()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('helpdeskToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskToApiArray')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportHelpdesk()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('helpdeskUpdate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskUpdate')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('helpdeskCreate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskCreate')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportHelpdesk()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('helpdeskRm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskRm')
            ->will($this->returnValue(true));

        $di              = new \Box_Di();
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
            ->setMethods(array('cannedGetSearchQuery', 'cannedToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedGetSearchQuery')
            ->will($this->returnValue(array('query', array())));
        $serviceMock->expects($this->atLeastOnce())->method('cannedToApiArray')
            ->will($this->returnValue(array()));

        $model = new \Model_SupportPr();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_DAtabase')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di          = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['db']    = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
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

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $data   = array();
        $result = $this->adminApi->canned_pairs($data);

        $this->assertIsArray($result);
    }

    public function testCanned_get()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPr()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('cannedToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedToApiArray')
            ->will($this->returnValue(array()));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPr()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('cannedRm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedRm')
            ->will($this->returnValue(array()));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('cannedCreate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCreate')
            ->will($this->returnValue(rand(1, 100)));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('cannedUpdate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedUpdate')
            ->will($this->returnValue(rand(1, 100)));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPr()));

        $di              = new \Box_Di();
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

        $di       = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportPrCategory()));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('cannedCategoryToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryToApiArray')
            ->will($this->returnValue(array()));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $supportCategory = new \Model_SupportPrCategory();
        $supportCategory->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($supportCategory));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('cannedCategoryUpdate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryUpdate')
            ->will($this->returnValue(array()));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db']     = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $supportCategory = new \Model_SupportPrCategory();
        $supportCategory->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($supportCategory));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('cannedCategoryRm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryRm')
            ->will($this->returnValue(array()));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('cannedCategoryCreate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryCreate')
            ->will($this->returnValue(array()));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('noteCreate'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('noteCreate')
            ->will($this->returnValue(array()));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('noteRm'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('noteRm')
            ->will($this->returnValue(array()));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicketNote()));

        $di              = new \Box_Di();
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
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('ticketTaskComplete'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketTaskComplete')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $di              = new \Box_Di();
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
        $activityMock = $this->getMockBuilder('\Box\Mod\Support\Api\Admin')->setMethods(array('ticket_delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->method('ticket_delete')->will($this->returnValue(true));

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

    public function testBatch_delete_public()
    {
        $activityMock = $this->getMockBuilder('\Box\Mod\Support\Api\Admin')->setMethods(array('public_ticket_delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->method('public_ticket_delete')->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_public(array('ids' => array(1, 2, 3)));
        $this->assertEquals(true, $result);
    }
}
 