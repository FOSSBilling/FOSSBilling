<?php

namespace Box\Tests\Mod\Filemanager\Api;


class Api_AdminTest extends \BBTestCase
{

    /**
     * @var \Box\Mod\Filemanager\Api\Admin
     */
    protected $adminApi = null;

    public function setUp(): void
    {
        $this->adminApi = new \Box\Mod\Filemanager\Api\Admin();
    }

    public function testSave_file()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('saveFile'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('saveFile')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'path' => 'tests',
            'data' => 'Content'
        );
        $result = $this->adminApi->save_file($data);
        $this->assertTrue($result);
    }

    public function testNew_item()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('create'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('create')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'path' => 'tests',
            'type' => 'dir'
        );
        $result = $this->adminApi->new_item($data);
        $this->assertTrue($result);
    }

    public function testMove_file()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('move'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('move')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));
        $di['validator'] = $validatorMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data   = array(
            'path' => 'tests',
            'to'   => 'src'
        );

        $result = $this->adminApi->move_file($data);
        $this->assertTrue($result);
    }

    public function testGet_list()
    {
        $serviceMock = $this->getMockBuilder('Box\Mod\Filemanager\Service')->setMethods(array('getFiles'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getFiles')
            ->will($this->returnValue(array()));

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->get_list(array());

        $this->assertIsArray($result);
    }


}
 