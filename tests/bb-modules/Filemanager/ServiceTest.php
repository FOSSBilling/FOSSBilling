<?php
namespace Box\Tests\Mod\Filemanager;

class ServiceTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Filemanager\Service
     */
    protected $service = null;

    public function setUp(): void
    {
        $this->service = new \Box\Mod\Filemanager\Service();
    }

    public function testDi()
    {
        $di       = new \Box_Di();
        $db       = $this->getMockBuilder('Box_Database')->getMock();
        $di['db'] = $db;
        $this->service->setDi($di);
        $result = $this->service->getDi();
        $this->assertEquals($di, $result);
        $this->assertInstanceOf('Box_Di', $result);
    }

    public function testSaveFile()
    {
        $bytesWritten = 12;
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('file_put_contents')
            ->will($this->returnValue($bytesWritten));

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);

        $result = $this->service->saveFile('tests/new_test_file.txt', 'content');
        $this->assertTrue($result);
    }

    public function testCreateDirectory()
    {
        $type   = 'dir';
        $path   = 'tests/test_dir';
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('fileExists')
            ->will($this->returnValue(false));
        $toolsMock->expects($this->atLeastOnce())
            ->method('mkdir')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);

        $result = $this->service->create($path, $type);
        $this->assertTrue($result);
    }

    public function testCreateFile()
    {
        $type   = 'file';
        $path   = 'tests/test_dir/test_file.txt';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Filemanager\Service')
            ->setMethods(array('saveFile'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('saveFile')
            ->will($this->returnValue(true));


        $result = $serviceMock->create($path, $type);

        $this->assertTrue($result);
    }

    public function testCreateDirectoryExistsException()
    {
        $type   = 'dir';
        $path   = 'tests/test_dir';

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('fileExists')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);
        $this->expectException(\Box_Exception::class);
        $result = $this->service->create($path, $type);
        $this->assertTrue($result);
    }

    public function testCreateDirectoryTypeNotExists()
    {
        $type   = 'non-existing-type';
        $path   = 'tests/test_dir';
        $this->expectException(\Box_Exception::class);
        $result = $this->service->create($path, $type);
        $this->assertTrue($result);
    }

    public function testMove()
    {
        $from   = 'tests/test_dir/test_file.txt';
        $to     = 'tests';

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('rename')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);

        $result = $this->service->move($from, $to);
        $this->assertTrue($result);
    }

    public function testGetFiles()
    {
        $result = $this->service->getFiles('../tests');
        $file = $result['files'][0];

        $this->assertIsArray($result);
        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('filecount', $result);
        $this->assertIsArray($result['files']);
        $this->assertIsInt($result['filecount']);
        $this->assertEquals($result['filecount'], count($result['files']));

        $this->assertArrayHasKey('filename', $file);
        $this->assertArrayHasKey('type', $file);
        $this->assertArrayHasKey('path', $file);
        $this->assertArrayHasKey('size', $file);
    } 
    
    public function testGetFilesEmptyDirectory()
    {
        $result = $this->service->getFiles('../tests/test_dir');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('filecount', $result);
        $this->assertIsInt($result['filecount']);

        $this->assertNull($result['files']);
        $this->assertEquals($result['filecount'], 0);

    }
    public function testGetFilesNonExistingDir()
    {
        $result = $this->service->getFiles('non-existing-dir');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('filecount', $result);
        $this->assertNull($result['files']);
        $this->assertEquals($result['filecount'], 0);

    }
}
 