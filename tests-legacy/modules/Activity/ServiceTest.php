<?php

namespace Box\Tests\Mod\Activity;

class ServiceTest extends \BBTestCase
{
    public function testDi(): void
    {
        $service = new \Box\Mod\Activity\Service();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getDi();
        $this->assertEquals($di, $result);
    }

    public static function searchFilters(): array
    {
        return [
            [[], 'FROM activity_system ', true],
            [['only_clients' => 'yes'], 'm.client_id IS NOT NULL', true],
            [['only_staff' => 'yes'], 'm.admin_id IS NOT NULL', true],
            [['priority' => '2'], 'm.priority =', true],
            [['search' => 'keyword'], 'm.message LIKE ', true],
            [['no_info' => true], 'm.priority < :priority ', true],
            [['no_debug' => true], 'm.priority < :priority ', true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('searchFilters')]
    public function testgetSearchQuery(array $filterKey, string $search, bool $expected): void
    {
        $di = new \Pimple\Container();
        $service = new \Box\Mod\Activity\Service();
        $service->setDi($di);
        $result = $service->getSearchQuery($filterKey);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertTrue(str_contains($result[0], $search), $expected);
    }

    public function testLogEmail(): void
    {
        $service = new \Box\Mod\Activity\Service();
        $data = [
            'client_id' => random_int(1, 100),
            'sender' => 'sender',
            'recipients' => 'recipients',
            'subject' => 'subject',
            'content_html' => 'html',
            'content_text' => 'text',
        ];

        $model = new \Model_ActivityClientEmail();
        $model->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn([]);

        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->logEmail($data['subject'], $data['client_id'], $data['sender'], $data['recipients'], $data['content_html'], $data['content_text']);
        $this->assertTrue($result);
    }

    public function testtoApiArray(): void
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

        $di = new \Pimple\Container();
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

    public function testrmByClient(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->id = 1;

        $activitySystemModel = new \Model_ActivitySystem();
        $activitySystemModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->with('ActivitySystem', 'client_id = ?', [$clientModel->id])
            ->willReturn([$activitySystemModel]);
        $dbMock->expects($this->atLeastOnce())
            ->method('trash')
            ->with($activitySystemModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $service = new \Box\Mod\Activity\Service();
        $service->setDi($di);

        $service->rmByClient($clientModel);
    }
}
