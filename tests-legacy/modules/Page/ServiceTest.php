<?php

namespace Box\Mod\Page;

class ServiceTest extends \BBTestCase
{
    public function testgetPairs(): void
    {
        $service = new Service();

        $themeService = $this->getMockBuilder('\\' . \Box\Mod\Theme\Service::class)->getMock();
        $themeService->expects($this->atLeastOnce())
            ->method('getCurrentClientAreaThemeCode');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn () => $themeService);

        $service->setDi($di);
        $result = $service->getPairs();
        $this->assertIsArray($result);
    }
}
