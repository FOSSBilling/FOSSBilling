<?php

namespace Box\Mod\Email\Controller;

class AdminTest extends \BBTestCase
{
    public function testDi(): void
    {
        $controller = new Admin();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $controller->setDi($di);
        $result = $controller->getDi();
        $this->assertEquals($di, $result);
    }

    public function testregister(): void
    {
        $boxAppMock = $this->getMockBuilder('\FOSSBilling\App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->exactly(5))
            ->method('get');

        $controllerAdmin = new Admin();
        $controllerAdmin->register($boxAppMock);
    }

    public function testgetIndex(): void
    {
        $boxAppMock = $this->getMockBuilder('\FOSSBilling\App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_email_history');

        $controllerAdmin = new Admin();
        $di = new \Pimple\Container();
        $di['is_admin_logged'] = true;

        $controllerAdmin->setDi($di);

        $controllerAdmin->get_history($boxAppMock);
    }
}
