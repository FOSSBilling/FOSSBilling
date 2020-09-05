<?php


namespace Box\Mod\Index\Controller;


class AdminTest extends \BBTestCase {

    public function testDi()
    {
        $controller = new \Box\Mod\Index\Controller\Admin();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $controller->setDi($di);
        $result = $controller->getDi();
        $this->assertEquals($di, $result);
    }

    public function testregister()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->exactly(4))
            ->method('get');

        $controller = new \Box\Mod\Index\Controller\Admin();
        $controller->register($boxAppMock);
    }

    public function testget_index_AdminIsLogged()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_index_dashboard');

        $authorizationMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authorizationMock->expects($this->atLeastOnce())
            ->method("isAdminLoggedIn")
            ->willReturn(true);

        $di = new \Box_Di();
        $di['auth'] = $authorizationMock;

        $controller = new \Box\Mod\Index\Controller\Admin();
        $controller->setDi($di);
        $controller->get_index($boxAppMock);
    }

    public function testget_index()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('redirect')
            ->with('/staff/login');

        $authorizationMock = $this->getMockBuilder('\Box_Authorization')->disableOriginalConstructor()->getMock();
        $authorizationMock->expects($this->atLeastOnce())
            ->method("isAdminLoggedIn")
            ->willReturn(false);

        $di = new \Box_Di();
        $di['auth'] = $authorizationMock;

        $controller = new \Box\Mod\Index\Controller\Admin();
        $controller->setDi($di);
        $controller->get_index($boxAppMock);
    }
}
 