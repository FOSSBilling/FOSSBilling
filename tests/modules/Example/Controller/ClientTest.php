<?php


namespace Box\Mod\Example\Controller;


class ClientTest extends \BBTestCase {

    public function testDi()
    {
        $controller = new \Box\Mod\Example\Controller\Client();

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
        $boxAppMock->expects($this->exactly(2))
            ->method('get');

        $controller = new \Box\Mod\Example\Controller\Client();
        $controller->register($boxAppMock);
    }

    public function testget_index()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_example_index');

        $controller = new \Box\Mod\Example\Controller\Client();
        $controller->get_index($boxAppMock);
    }

    public function testget_protected()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_example_index');

        $controller = new \Box\Mod\Example\Controller\Client();
        $controller->get_protected($boxAppMock);
    }

}
 