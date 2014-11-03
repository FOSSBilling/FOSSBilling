<?php

namespace Box\Tests\Mod\Filemanager\Api;


class AdminTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Box\Mod\Filemanager\Api\Admin
     */
    protected $adminApi = null;

    public function setUp()
    {
        $this->adminApi = new \Box\Mod\Filemanager\Api\Admin();
    }

    public function testSave_file()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('saveFile'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('saveFile')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'path' => 'tests',
            'data' => 'Content'
        );
        $result = $this->adminApi->save_file($data);
        $this->assertTrue($result);
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testSave_filePathNotSet()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('saveFile'))->getMock();
        $serviceMock->expects($this->never())
            ->method('saveFile')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $data = array(
            'data' => 'Content'
        );
        $this->adminApi->save_file($data);
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testSave_fileContentNotSet()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('saveFile'))->getMock();
        $serviceMock->expects($this->never())
            ->method('saveFile')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $data = array(
            'path' => 'tests',
        );
        $this->adminApi->save_file($data);
    }

    public function testNew_item()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('create'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('create')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'path' => 'tests',
            'type' => 'dir'
        );
        $result = $this->adminApi->new_item($data);
        $this->assertTrue($result);
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testNew_itemPathNotSet()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('create'))->getMock();
        $serviceMock->expects($this->never())
            ->method('create')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $data = array(
            'type' => 'dir'
        );
        $this->adminApi->new_item($data);
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testNew_itemTypeNotSet()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('create'))->getMock();
        $serviceMock->expects($this->never())
            ->method('create')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $data = array(
            'path' => 'tests',
        );

        $this->adminApi->new_item($data);
    }

    public function testMove_file()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('move'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('move')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'path' => 'tests',
            'to'   => 'src'
        );

        $result = $this->adminApi->move_file($data);
        $this->assertTrue($result);
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testMove_filePathNotSet()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('move'))->getMock();
        $serviceMock->expects($this->never())
            ->method('move')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $data = array(
            'to' => 'src'
        );

        $this->adminApi->move_file($data);
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testMove_fileToDirectoryNotSet()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('move'))->getMock();
        $serviceMock->expects($this->never())
            ->method('move')
            ->will($this->returnValue(true));

        $this->adminApi->setService($serviceMock);

        $data = array(
            'path' => 'tests',
        );

        $this->adminApi->move_file($data);
    }

    public function testGet_list()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('getFiles'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getFiles')
            ->will($this->returnValue(array()));

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->get_list(array());

        $this->assertInternalType('array', $result);
    }


}
 