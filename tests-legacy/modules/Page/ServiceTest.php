<?php

namespace Box\Mod\Page;

class ServiceTest extends \BBTestCase
{
    public function testgetPairs(): void
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
