<?php
namespace Box\Tests\Mod\Support\Api;


class Api_ClientTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Support\Api\Client
     */
    protected $clientApi = null;

    public function setup(): void
    {
        $this->clientApi = new \Box\Mod\Support\Api\Client();
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
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data   = array();
        $result = $this->clientApi->ticket_get_list($data);

        $this->assertIsArray($result);
    }

    public function testTicket_get()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('findOneByClient', 'toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('findOneByClient')
            ->will($this->returnValue(new \Model_SupportTicket()));
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $this->clientApi->ticket_get($data);

        $this->assertIsArray($result);
    }

    public function testHelpdesk_get_pairs()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('helpdeskGetPairs'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskGetPairs')
            ->will($this->returnValue(array(0 => 'General')));

        $this->clientApi->setService($serviceMock);

        $result = $this->clientApi->helpdesk_get_pairs();

        $this->assertIsArray($result);
    }

    public function testTicket_create()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('ticketCreateForClient'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketCreateForClient')
            ->will($this->returnValue(rand(1, 100)));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue(new \Model_SupportHelpdesk()));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db']        = $dbMock;
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data   = array(
            'content'             => 'Content',
            'subject'             => 'Subject',
            'support_helpdesk_id' => rand(1, 100),
        );
        $result = $this->clientApi->ticket_create($data);

        $this->assertIsInt($result);
    }

    public function testTicket_reply()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('canBeReopened', 'ticketReply'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('canBeReopened')
            ->will($this->returnValue(true));
        $serviceMock->expects($this->atLeastOnce())->method('ticketReply')
            ->will($this->returnValue(rand(1, 100)));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db']        = $dbMock;
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data   = array(
            'content' => 'Content',
            'id'      => rand(1, 100),
        );
        $result = $this->clientApi->ticket_reply($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testTicket_replyCanNotBeReopenedException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('canBeReopened', 'ticketReply'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('canBeReopened')
            ->will($this->returnValue(false));
        $serviceMock->expects($this->never())->method('ticketReply')
            ->will($this->returnValue(rand(1, 100)));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(new \Model_SupportTicket()));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db']        = $dbMock;
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data   = array(
            'content' => 'Content',
            'id'      => rand(1, 100),
        );
        $this->expectException(\Box_Exception::class);
        $result = $this->clientApi->ticket_reply($data);

        $this->assertIsInt($result);
    }


    public function testTicket_close()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->setMethods(array('findOneByClient', 'closeTicket'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('findOneByClient')
            ->will($this->returnValue(new \Model_SupportTicket()));
        $serviceMock->expects($this->atLeastOnce())->method('closeTicket')
            ->will($this->returnValue(rand(1, 100)));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id = rand(1, 100);

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data   = array(
            'content' => 'Content',
            'id'      => rand(1, 100),
        );
        $result = $this->clientApi->ticket_close($data);

        $this->assertIsInt($result);
    }


}
 