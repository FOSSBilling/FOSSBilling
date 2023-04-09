<?php

namespace Box\Tests\Mod\Activity;

class ServiceTest extends \BBTestCase
{

    public function testDi()
    {
        $service = new \Box\Mod\Activity\Service();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getDi();
        $this->assertEquals($di, $result);
    }

    public function searchFilters()
    {
        return array(
            array(array(), 'FROM activity_system ', TRUE),
            array(array('only_clients' => 'yes'), 'm.client_id IS NOT NULL', TRUE),
            array(array('only_staff' => 'yes'), 'm.admin_id IS NOT NULL', TRUE),
            array(array('priority' => '2'), 'm.priority =', TRUE),
            array(array('search' => 'keyword'), 'm.message LIKE ', TRUE),
            array(array('no_info' => true), 'm.priority < :priority ', TRUE),
            array(array('no_debug' => true), 'm.priority < :priority ', TRUE),
        );
    }

    /**
     * @dataProvider searchFilters
     */
    public function testgetSearchQuery($filterKey, $search, $expected)
    {
        $di = new \Box_Di();
        $service = new \Box\Mod\Activity\Service();
        $service->setDi($di);
        $result  = $service->getSearchQuery($filterKey);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertTrue(strpos($result[0], $search) != false, $expected, $result);
    }

    public function testLogEmail()
    {
        $service = new \Box\Mod\Activity\Service();
        $data = array(
            'client_id'    => rand(1, 100),
            'sender'       => 'sender',
            'recipients'   => 'recipients',
            'subject'      => 'subject',
            'content_html' => 'html',
            'content_text' => 'text',
        );

        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \DummyBean());

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($model));
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(array()));

        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->logEmail($data['subject'], $data['client_id'], $data['sender'], $data['recipients'], $data['content_html'], $data['content_text']);
        $this->assertTrue($result);
    }

    public function testtoApiArray()
    {
        $clientHistoryModel = new \Model_ActivityClientHistory();
        $clientHistoryModel->loadBean(new \DummyBean());
        $clientHistoryModel->client_id = 1;

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $expectionError = 'Client not found';
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('Client', $clientHistoryModel->client_id, $expectionError)
            ->willReturn($clientModel);

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Activity\Service();
        $service->setDi($di);

        $result = $service->toApiArray($clientHistoryModel);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('ip', $result);
        $this->assertArrayHasKey('created_at', $result);

        $this->assertIsArray($result['client']);
        $this->assertArrayHasKey('id', $result['client']);
        $this->assertArrayHasKey('first_name', $result['client']);
        $this->assertArrayHasKey('last_name', $result['client']);
        $this->assertArrayHasKey('email', $result['client']);
    }

    public function testrmByClient()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->id = 1;

        $activitySystemModel = new \Model_ActivitySystem();
        $activitySystemModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('ActivitySystem', 'client_id = ?', array($clientModel->id))
            ->willReturn(array($activitySystemModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->with($activitySystemModel);

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Activity\Service();
        $service->setDi($di);

        $service->rmByClient($clientModel);
    }

}
 
