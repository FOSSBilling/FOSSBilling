<?php
/**
 * @group Core
 */
class ServiceTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'example.xml';

    public function testUninstall(){
        $service = new Box\Mod\Example\Service();
        $result = $service->uninstall();
        $this->assertTrue($result);
   }

    public function testUpdate(){
        $service = new Box\Mod\Example\Service();
        $result = $service->update(array());
        $this->assertTrue($result);
   }

    public function testGetSearchQuery()
    {
        $service = new Box\Mod\Example\Service();
        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $service->setDi($di);

        $data = array(
            'client_id' => 1
        );
        list($sql, $params) = $service->getSearchQuery($data);
        $this->assertIsString($sql);
        $this->assertIsArray($params);
        $this->assertArrayHasKey(':client_id', $params);
        $this->assertEquals($params[':client_id'], $data['client_id']);
    }

    public function testEvents()
    {
        $service = new Box\Mod\Example\Service();
        $params = array(
            'ip' => '123.123.123.123',
        );
        $event = new Box_Event(null, 'name', $params, $this->api_admin, $this->api_guest);
        $event->setDi($this->di);

        $result = $service->onEventClientLoginFailed($event);
        $this->assertNull($result);


        $params = array(
            'client_id' => 1,
            'id' => 1
        );
        $event = new Box_Event(null, 'name', $params, $this->api_admin, $this->api_guest);
        $event->setDi($this->di);

        $result = $service->onAfterClientOrderCreate($event);
        $this->assertNull($result);
    }
}