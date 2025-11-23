<?php

declare(strict_types=1);

namespace Box\Mod\Cron\Api;

#[PHPUnit\Framework\Attributes\Group('Core')]
final class GuestTest extends \BBTestCase
{
    public function testGetDi(): void
    {
        $di = new \Pimple\Container();
        $api = new Guest();
        $api->setDi($di);
        $getDi = $api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testSettings(): void
    {
        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())->method('getConfig')->willReturn([]);

        $api = new Guest();
        $api->setMod($modMock);

        $result = $api->settings();
        $this->assertIsArray($result);
    }

    public function testIsLate(): void
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
