<?php

declare(strict_types=1);

namespace Box\Mod\Currency\Controller;
use PHPUnit\Framework\Attributes\DataProvider; 
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    public function testDi(): void
    {
        $controller = new Admin();

        $di = $this->getDi();
        $db = $this->createMock('Box_Database');

        $di['db'] = $db;
        $controller->setDi($di);
        $result = $controller->getDi();
        $this->assertEquals($di, $result);
    }

    public function testRegister(): void
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->once())
            ->method('get')
            ->with('/currency/manage/:code', 'get_manage', ['code' => '[a-zA-Z]+'], Admin::class);

        $controllerAdmin = new Admin();
        $controllerAdmin->register($boxAppMock);
    }
}
