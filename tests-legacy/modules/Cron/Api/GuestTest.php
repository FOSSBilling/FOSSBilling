<?php

declare(strict_types=1);

namespace Box\Mod\Cron\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class GuestTest extends \BBTestCase
{
    public function testGetDi(): void
    {
        $di = $this->getDi();
        $api = new Guest();
        $api->setDi($di);
        $getDi = $api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testSettings(): void
    {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())->method('getConfig')->willReturn([]);

        $api = new Guest();
        $api->setMod($modMock);

        $result = $api->settings();
        $this->assertIsArray($result);
    }

    public function testIsLate(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Cron\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('isLate')->willReturn(true);

        $api = new Guest();
        $api->setService($serviceMock);

        $result = $api->is_late();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}
