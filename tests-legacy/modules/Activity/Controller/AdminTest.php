<?php

namespace Box\Tests\Mod\Activity\Controller;

class AdminTest extends \BBTestCase
{
    public function testDi(): void
    {
        $controller = new \Box\Mod\Activity\Controller\Admin();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $controller->setDi($di);
        $result = $controller->getDi();
        $this->assertEquals($di, $result);
    }

    public function testfetchNavigation(): void
    {
        $di = new \Pimple\Container();
        $link = 'activity';

        $urlMock = $this->getMockBuilder('Box_Url')->getMock();
        $urlMock->expects($this->atLeastOnce())
            ->method('adminLink')
            ->willReturn('https://fossbilling.org/index.php?_url=/' . $link);
        $di['url'] = $urlMock;

        $controllerAdmin = new \Box\Mod\Activity\Controller\Admin();
        $controllerAdmin->setDi($di);

        $result = $controllerAdmin->fetchNavigation();
        $this->assertIsArray($result);
    }

    public function testregister(): void
    {
        $boxAppMock = $this->getMockBuilder('\FOSSBilling\App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('get')
            ->with('/activity', 'get_index', [], \Box\Mod\Activity\Controller\Admin::class);

        $controllerAdmin = new \Box\Mod\Activity\Controller\Admin();
        $controllerAdmin->register($boxAppMock);
    }

    public function testgetIndex(): void
    {
        $boxAppMock = $this->getMockBuilder('\FOSSBilling\App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_activity_index');

        $controllerAdmin = new \Box\Mod\Activity\Controller\Admin();
        $di = new \Pimple\Container();
        $di['is_admin_logged'] = true;

        $controllerAdmin->setDi($di);

        $controllerAdmin->get_index($boxAppMock);
    }
}
