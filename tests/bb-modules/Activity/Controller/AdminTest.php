<?php


namespace Box\Tests\Mod\Activity\Controller;


class AdminTest extends \BBTestCase {

    public function testDi()
    {
        $controller = new \Box\Mod\Activity\Controller\Admin();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $controller->setDi($di);
        $result = $controller->getDi();
        $this->assertEquals($di, $result);
    }

    public function testfetchNavigation()
    {
        $di = new \Box_Di();
        $link = 'activity';

        $urlMock = $this->getMockBuilder('Box_Url')->getMock();
        $urlMock->expects($this->atLeastOnce())
            ->method('adminLink')
            ->willReturn('http://boxbilling.com/index.php?_url=/' . $link);
        $di['url'] = $urlMock;

        $controllerAdmin = new \Box\Mod\Activity\Controller\Admin();
        $controllerAdmin->setDi($di);

        $result = $controllerAdmin->fetchNavigation();
        $this->assertIsArray($result);
    }

    public function testregister()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('get')
            ->with('/activity', 'get_index', array(), 'Box\Mod\Activity\Controller\Admin');

        $controllerAdmin = new \Box\Mod\Activity\Controller\Admin();
        $controllerAdmin->register($boxAppMock);
    }

    public function testget_index()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_activity_index');

        $controllerAdmin = new \Box\Mod\Activity\Controller\Admin();
        $controllerAdmin->get_index($boxAppMock);
    }
}
 