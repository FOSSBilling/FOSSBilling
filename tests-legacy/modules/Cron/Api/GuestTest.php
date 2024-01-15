<?php

namespace Box\Mod\Cron\Api;

class GuestTest extends \BBTestCase
{
    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $api = new Guest();
        $api->setDi($di);
        $getDi = $api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testsettings()
    {
        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())->method('getConfig')->willReturn([]);

        $api = new Guest();
        $api->setMod($modMock);

        $result = $api->settings();
        $this->assertIsArray($result);
    }

    public function testisLate()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cron\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('isLate')->willReturn(true);

        $api = new Guest();
        $api->setService($serviceMock);

        $result = $api->is_late();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}
