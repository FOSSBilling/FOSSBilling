<?php

declare(strict_types=1);

namespace Box\Mod\Email\Controller;

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
        $boxAppMock->expects($this->exactly(5))
            ->method('get');

        $controllerAdmin = new Admin();
        $controllerAdmin->register($boxAppMock);
    }

    public function testGetIndex(): void
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_email_history');

        $controllerAdmin = new Admin();
        $di = $this->getDi();
        $di['is_admin_logged'] = true;

        $controllerAdmin->setDi($di);

        $controllerAdmin->get_history($boxAppMock);
    }
}
