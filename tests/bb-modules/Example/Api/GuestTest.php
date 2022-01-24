<?php


namespace Box\Mod\Example\Api;


class GuestTest extends \BBTestCase {

    /**
     * @var \Box\Mod\Example\Api\Guest
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api= new \Box\Mod\Example\Api\Guest();
    }

    public function testreadme()
    {
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('file_get_contents')
            ->willReturn('');


        $di = new \Box_Di();
        $di['tools'] = $toolsMock;
        $this->api->setDi($di);

        $result = $this->api->readme(array());
        $this->assertIsString($result);
    }
}