<?php


namespace Box\Mod\Filemanager\Controller;


class AdminTest extends \BBTestCase {

    public function testDi()
    {
        $controller = new \Box\Mod\Filemanager\Controller\Admin();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $controller->setDi($di);
        $result = $controller->getDi();
        $this->assertEquals($di, $result);
    }

    public function testfetchNavigation()
    {
        $link = 'filemanager';

        $urlMock = $this->getMockBuilder('Box_Url')->getMock();
        $urlMock->expects($this->atLeastOnce())
            ->method('adminLink')
            ->willReturn('http://boxbilling.com/index.php?_url=/' . $link);

        $di = new \Box_Di();
        $di['url'] = $urlMock;

        $controller = new \Box\Mod\Filemanager\Controller\Admin();
        $controller->setDi($di);
        $result = $controller->fetchNavigation();

        $this->assertArrayHasKey('subpages', $result);
        $this->assertIsArray($result['subpages']);
    }

    public function testregister()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->exactly(4))
            ->method('get');

        $controller = new \Box\Mod\Filemanager\Controller\Admin();
        $controller->register($boxAppMock);
    }

    public function testget_index()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_filemanager_index');

        $controller = new \Box\Mod\Filemanager\Controller\Admin();
        $controller->get_index($boxAppMock);
    }

    public function testget_ide()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_filemanager_ide');

        $_GET['open'] = 'text.txt';
        $_GET['inline'] = true;


        $controller = new \Box\Mod\Filemanager\Controller\Admin();
        $controller->get_ide($boxAppMock);
    }

    public function testget_icons()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_filemanager_icons');

        $controller = new \Box\Mod\Filemanager\Controller\Admin();
        $controller->get_icons($boxAppMock);
    }

    public function testget_editor_FileNotExist()
    {
        $controller = new \Box\Mod\Filemanager\Controller\Admin();

        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();

        $_GET['file'] = 'notexisting.fl';

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('fileExists')
            ->willReturn(false);

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;
        $di['is_admin_logged']  = true;

        $controller->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('File does not exist', 404);
        $controller->get_editor($boxAppMock);
    }

    public function testget_editor_FileisNotForBoxBillingFolder()
    {
        $controller = new \Box\Mod\Filemanager\Controller\Admin();

        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();

        $_GET['file'] = 'index.html';

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('fileExists')
            ->willReturn(true);

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;
        $di['is_admin_logged']  = true;

        $controller->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('File does not exist', 405);
        $controller->get_editor($boxAppMock);
    }

    public function testget_editor_TypeisFile()
    {
        $controller = new \Box\Mod\Filemanager\Controller\Admin();

        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_filemanager_editor')
            ->willReturn('renderiing..');

        $_GET['file'] = BB_PATH_ROOT.'/file.fl';

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('fileExists')
            ->willReturn(true);
        $toolsMock->expects($this->atLeastOnce())
            ->method('file_get_contents')
            ->willReturn(true);

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;
        $di['is_admin_logged']  = true;

        $controller->setDi($di);
        $result = $controller->get_editor($boxAppMock);
        $this->assertIsString($result);
    }

    public function testget_editor_TypeisImage()
    {
        $controller = new \Box\Mod\Filemanager\Controller\Admin();

        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_filemanager_image')
            ->willReturn('renderiing..');

        $_GET['file'] = BB_PATH_ROOT.'/foto.jpg';

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('fileExists')
            ->willReturn(true);

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;
        $di['is_admin_logged']  = true;

        $controller->setDi($di);
        $result = $controller->get_editor($boxAppMock);
        $this->assertIsString($result);
    }
}
 