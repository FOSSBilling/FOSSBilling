<?php

declare(strict_types=1);

namespace Box\Mod\Page;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    public function testGetPairs(): void
    {
        $service = new Service();

        $themeService = $this->createMock(\Box\Mod\Theme\Service::class);
        $themeService->expects($this->atLeastOnce())
            ->method('getCurrentClientAreaThemeCode')
            ->willReturn('huraga');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeService);

        $service->setDi($di);
        $result = $service->getPairs();
        $this->assertIsArray($result);
    }
}
