<?php


namespace Box\Mod\Example\Api;


class AdminTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var \Box\Mod\Example\Api\Admin
     */
    protected $api = null;

    public function setup()
    {
        $this->api= new \Box\Mod\Example\Api\Admin();
    }

    public function testget_something()
    {
        $data = array('microsoft' => '');
        $expected = array('apple', 'google', 'facebook', 'microsoft');
        $result = $this->api->get_something($data);
        $this->assertInternalType('array', $result);
        $this->assertEquals($expected, $result);
    }
}
 