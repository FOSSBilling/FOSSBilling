<?php

namespace Box\Mod\Cron\Api;

class AdminTest extends \BBTestCase
{
    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $api_admin = new Admin();
        $api_admin->setDi($di);
        $getDi = $api_admin->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testinfo(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Cron\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getCronInfo')->willReturn([]);

        $api_admin = new Admin();
        $api_admin->setService($serviceMock);

        $result = $api_admin->info([]);
        $this->assertIsArray($result);
    }
}
