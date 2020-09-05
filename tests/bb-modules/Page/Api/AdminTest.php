<?php


namespace Box\Mod\Page\Api;


class AdminTest extends \BBTestCase {

    /**
     * @var \Box\Mod\Page\Api\Admin
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api = new \Box\Mod\Page\Api\Admin();
    }


    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testget_pairs()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Page\Service')->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPairs')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);
        $result = $this->api->get_pairs();
        $this->assertIsArray($result);
    }

}
 