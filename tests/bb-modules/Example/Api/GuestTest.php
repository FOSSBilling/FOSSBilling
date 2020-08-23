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

    public function testtop_songs()
    {
        $xmlString = "<note>
<to>Tove</to>
<from>Jani</from>
<heading>Reminder</heading>
<body>Don't forget me this weekend!</body>
</note>";
        $data = array();
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('file_get_contents')
            ->willReturn($xmlString);

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->api->setDi($di);
        $result = $this->api->top_songs($data);
        $this->assertIsArray($result);

    }
}
 