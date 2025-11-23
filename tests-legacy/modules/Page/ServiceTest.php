<?php

declare(strict_types=1);

namespace Box\Mod\Page;

#[PHPUnit\Framework\Attributes\Group('Core')]
final class ServiceTest extends \BBTestCase
{
    public function testGetPairs(): void
    {
        $service = new Service();

        $themeService = $this->getMockBuilder('\\' . \Box\Mod\Theme\Service::class)->getMock();
        $themeService->expects($this->atLeastOnce())
            ->method('getCurrentClientAreaThemeCode')
            ->willReturn('huraga');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeService);

        $service->setDi($di);
        $result = $service->getPairs();
        $this->assertIsArray($result);
    }
}
