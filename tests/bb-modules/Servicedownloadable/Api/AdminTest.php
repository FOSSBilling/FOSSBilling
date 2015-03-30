<?php


namespace Box\Mod\Servicedownloadable\Api;


class AdminTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Box\Mod\Servicedownloadable\Api\Admin
     */
    protected $api = null;

    public function setup()
    {
        $this->api = new \Box\Mod\Servicedownloadable\Api\Admin();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testuploadProductNotFound()
    {
        $data['id'] = 1;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load');

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->setExpectedException('\Box_Exception', 'Product not found');
        $this->api->upload($data);
    }

    public function testuploadFileNotUploaded()
    {
        $data['id'] = 1;
        $model      = new \Model_Product();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($model));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->setExpectedException('\Box_Exception', 'File was not uploaded');
        $this->api->upload($data);
    }

    public function testupload()
    {
        $data['id'] = 1;
        $model      = new \Model_Product();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedownloadable\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('uploadProductFile')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($model));
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['validator'] = $validatorMock;

        $_FILES['file_data'] = 'exits';

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->upload($data);
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }

    public function testupdateProductNotFound()
    {
        $data['order_id'] = 1;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load');
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->setExpectedException('\Box_Exception', 'Order not found');
        $this->api->update($data);
    }

    public function testupdateOrderNotActivated()
    {
        $data['order_id'] = 1;
        $model            = new \Model_ClientOrder();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($model));

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) { return $orderServiceMock; });
        $di['validator']   = $validatorMock;

        $this->api->setDi($di);
        $this->setExpectedException('\Box_Exception', 'Order is not activated');
        $this->api->update($data);
    }

    public function testupdate()
    {
        $data['order_id'] = 1;
        $model            = new \Model_ClientOrder();

        $modelDownloadableModel = new \Model_ServiceDownloadable();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Servicedownloadable\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateProductFile')
            ->will($this->returnValue(true));

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($modelDownloadableModel));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($model));
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) { return $orderServiceMock; });
        $di['validator']   = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->update($data);
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }
}
 