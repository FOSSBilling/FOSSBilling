<?php

declare(strict_types=1);

namespace Box\Mod\Cron\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    public function testInfo(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Cron\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('getCronInfo')->willReturn([]);

        $api_admin = new Admin();
        $api_admin->setService($serviceMock);

        $result = $api_admin->info([]);
        $this->assertIsArray($result);
    }
}
