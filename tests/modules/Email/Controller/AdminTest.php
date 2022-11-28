<?php


namespace Box\Mod\Email\Controller;


class AdminTest extends \BBTestCase {

    public function testDi()
    {
        $controller = new \Box\Mod\Email\Controller\Admin();

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
        $boxAppMock->expects($this->exactly(5))
            ->method('get');

        $controllerAdmin = new \Box\Mod\Email\Controller\Admin();
        $controllerAdmin->register($boxAppMock);
    }

    public function testget_index()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_email_history');

        $controllerAdmin = new \Box\Mod\Email\Controller\Admin();
        $controllerAdmin->get_history($boxAppMock);
    }
}
 