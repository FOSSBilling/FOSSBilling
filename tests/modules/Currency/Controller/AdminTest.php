<?php


namespace Box\Mod\Currency\Controller;


class AdminTest extends \BBTestCase {

    public function testDi()
    {
        $controller = new \Box\Mod\Currency\Controller\Admin();

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
        $boxAppMock->expects($this->once())
            ->method('get')
            ->with('/currency/manage/:code', 'get_manage', array('code'=>'[a-zA-Z]+'), 'Box\Mod\Currency\Controller\Admin');

        $controllerAdmin = new \Box\Mod\Currency\Controller\Admin();
        $controllerAdmin->register($boxAppMock);
    }
}
 