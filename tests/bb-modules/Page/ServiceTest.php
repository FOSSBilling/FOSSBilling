<?php


namespace Box\Mod\Page;


class ServiceTest extends \BBTestCase {

    public function testgetPairs()
    {
        $service = new \Box\Mod\Page\Service();

        $themeService = $this->getMockBuilder('\Box\Mod\Theme\Service')->getMock();
        $themeService->expects($this->atLeastOnce())
            ->method('getCurrentClientAreaThemeCode');

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function() use ($themeService) {return $themeService;});

        $service->setDi($di);
        $result = $service->getPairs();
        $this->assertIsArray($result);
    }
}
 