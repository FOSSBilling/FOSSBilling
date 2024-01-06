<?php

namespace Box\Mod\Page\Api;

class AdminTest extends \BBTestCase
{
    /**
     * @var Admin
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Admin();
    }

    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetPairs()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Page\Service::class)->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->get_pairs();
        $this->assertIsArray($result);
    }
}
