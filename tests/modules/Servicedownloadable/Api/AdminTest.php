<?php


namespace Box\Mod\Servicedownloadable\Api;


class AdminTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicedownloadable\Api\Admin
     */
    protected $api = null;

    public function setup(): void
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

    public function testuploadFileNotUploaded()
    {
        $data['id'] = 1;
        $model      = new \Model_Product();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('File was not uploaded');
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
            ->method('getExistingModelById')
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
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testupdateOrderNotActivated()
    {
        $data['order_id'] = 1;
        $model            = new \Model_ClientOrder();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
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
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Order is not activated');
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
            ->method('getExistingModelById')
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
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}
 