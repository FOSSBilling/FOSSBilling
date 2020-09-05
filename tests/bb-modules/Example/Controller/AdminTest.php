<?php


namespace Box\Mod\Example\Controller;


class AdminTest extends \BBTestCase {

    public function testDi()
    {
        $controller = new \Box\Mod\Example\Controller\Admin();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $controller->setDi($di);
        $result = $controller->getDi();
        $this->assertEquals($di, $result);
    }

    public function testfetchNavigation()
    {
        $link = 'example';

        $urlMock = $this->getMockBuilder('Box_Url')->getMock();
        $urlMock->expects($this->atLeastOnce())
            ->method('adminLink')
            ->willReturn('http://boxbilling.com/index.php?_url=/' . $link);
        $di['url'] = $urlMock;

        $controller = new \Box\Mod\Example\Controller\Admin();
        $controller->setDi($di);
        $result = $controller->fetchNavigation();

        $this->assertArrayHasKey('group', $result);
        $this->assertArrayHasKey('subpages', $result);

        $this->assertIsArray($result['group']);
        $this->assertIsArray($result['subpages']);
    }

    public function testregister()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->exactly(4))
            ->method('get');

        $controllerAdmin = new \Box\Mod\Example\Controller\Admin();
        $controllerAdmin->register($boxAppMock);
    }

    public function testget_index()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_example_index');

        $controllerAdmin = new \Box\Mod\Example\Controller\Admin();
        $controllerAdmin->get_index($boxAppMock);
    }

    public function testget_test()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_example_index');

        $controllerAdmin = new \Box\Mod\Example\Controller\Admin();
        $controllerAdmin->get_test($boxAppMock);
    }

    public function testget_user()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_example_index');

        $id = 1;

        $controllerAdmin = new \Box\Mod\Example\Controller\Admin();
        $controllerAdmin->get_user($boxAppMock, $id);
    }
}
 