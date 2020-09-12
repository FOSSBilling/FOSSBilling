<?php


namespace Box\Mod\Example\Api;


class AdminTest extends \BBTestCase {

    /**
     * @var \Box\Mod\Example\Api\Admin
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api= new \Box\Mod\Example\Api\Admin();
    }

    public function testget_something()
    {
        $data = array('microsoft' => '');
        $expected = array('apple', 'google', 'facebook', 'microsoft');
        $result = $this->api->get_something($data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }
}
 