<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class ServiceTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'example.xml';

    public function testUninstall(): void
    {
        $service = new Box\Mod\Example\Service();
        $result = $service->uninstall();
        $this->assertTrue($result);
    }

    public function testUpdate(): void
    {
        $service = new Box\Mod\Example\Service();
        $result = $service->update([]);
        $this->assertTrue($result);
    }

    public function testGetSearchQuery(): void
    {
        $service = new Box\Mod\Example\Service();
        $di = new Pimple\Container();
        $service->setDi($di);

        $data = [
            'client_id' => 1,
        ];
        [$sql, $params] = $service->getSearchQuery($data);
        $this->assertIsString($sql);
        $this->assertIsArray($params);
        $this->assertArrayHasKey(':client_id', $params);
        $this->assertEquals($params[':client_id'], $data['client_id']);
    }

    public function testEvents(): void
    {
        $service = new Box\Mod\Example\Service();
        $params = [
            'ip' => '123.123.123.123',
        ];
        $event = new Box_Event(null, 'name', $params, $this->api_admin, $this->api_guest);
        $event->setDi($this->di);

        $result = $service->onEventClientLoginFailed($event);
        $this->assertNull($result);

        $params = [
            'client_id' => 1,
            'id' => 1,
        ];
        $event = new Box_Event(null, 'name', $params, $this->api_admin, $this->api_guest);
        $event->setDi($this->di);

        $result = $service->onAfterClientOrderCreate($event);
        $this->assertNull($result);
    }
}
