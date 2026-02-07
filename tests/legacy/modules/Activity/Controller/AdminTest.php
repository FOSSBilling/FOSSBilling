<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Activity\Controller;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    public function testDi(): void
    {
        $controller = new \Box\Mod\Activity\Controller\Admin();

        $di = $this->getDi();
        $db = $this->createMock('Box_Database');

        $di['db'] = $db;
        $controller->setDi($di);
        $result = $controller->getDi();
        $this->assertEquals($di, $result);
    }

    public function testFetchNavigation(): void
    {
        $di = $this->getDi();
        $link = 'activity';

        $urlMock = $this->createMock('Box_Url');
        $urlMock->expects($this->atLeastOnce())
            ->method('adminLink')
            ->willReturn('https://fossbilling.org/index.php?_url=/' . $link);
        $di['url'] = $urlMock;

        $controllerAdmin = new \Box\Mod\Activity\Controller\Admin();
        $controllerAdmin->setDi($di);

        $result = $controllerAdmin->fetchNavigation();
        $this->assertIsArray($result);
    }

    public function testRegister(): void
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('get')
            ->with('/activity', 'get_index', [], \Box\Mod\Activity\Controller\Admin::class);

        $controllerAdmin = new \Box\Mod\Activity\Controller\Admin();
        $controllerAdmin->register($boxAppMock);
    }

    public function testGetIndex(): void
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_activity_index');

        $controllerAdmin = new \Box\Mod\Activity\Controller\Admin();
        $di = $this->getDi();
        $di['is_admin_logged'] = true;

        $controllerAdmin->setDi($di);

        $controllerAdmin->get_index($boxAppMock);
    }
}
