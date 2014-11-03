<?php
/**
 * @group Core
 */
class Box_Mod_Client_ServiceTest extends ApiTestCase
{
    public function testEvents()
    {
        $service = new \Box\Mod\Client\Service();
        $service->setDi($this->di);
        $params = array(
            'id' => 1,
            'password' => 'qwerty123',
        );
        $event = new Box_Event(null, 'name', $params, $this->api_admin, $this->api_guest);
        $event->setDi($this->di);
        $bool = $service->onAfterClientSignUp($event);
        $this->assertTrue($bool);
    }

 public function testGenerateEmailConfirmationLink()
    {
        $service = new \Box\Mod\Client\Service();
        $service->setDi($this->di);
        $link = $service->generateEmailConfirmationLink(1);
        $this->assertInternalType('string', $link);
        $this->assertEquals(strpos($link, 'http://'), 0);
    }



}