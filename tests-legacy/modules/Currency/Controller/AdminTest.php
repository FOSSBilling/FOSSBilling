<?php

namespace Box\Mod\Currency\Controller;

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
        $boxAppMock->expects($this->once())
            ->method('get')
            ->with('/currency/manage/:code', 'get_manage', ['code' => '[a-zA-Z]+'], Admin::class);

        $controllerAdmin = new Admin();
        $controllerAdmin->register($boxAppMock);
    }
}
