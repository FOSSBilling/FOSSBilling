<?php

namespace Box\Mod\Servicedownloadable;

class ServiceTest extends \BBTestCase
{
    /**
     * @var Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new Service();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testattachOrderConfig(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"filename" : "temp/asdcxTest.txt"}';

        $data = [];

        $expected = array_merge(json_decode($productModel->config, 1), $data);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);
        $result = $this->service->attachOrderConfig($productModel, $data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testactionCreate(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->config = '{"filename" : "temp/asdcxTest.txt"}';

        $model = new \Model_ServiceDownloadable();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->service->setDi($di);
        $result = $this->service->action_create($clientOrderModel);
        $this->assertInstanceOf('\Model_ServiceDownloadable', $result);
    }

    public function testactionDelete(): void
    {
        $clientOrderModel = new \Model_ClientOrder();

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceDownloadable());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn () => $orderServiceMock);

        $this->service->setDi($di);
        $this->service->action_delete($clientOrderModel);
    }

    public function testhitDownload(): void
    {
        $model = new \Model_ServiceDownloadable();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->service->hitDownload($model);
    }

    public function testtoApiArray(): void
    {
        $model = new \Model_ServiceDownloadable();
        $model->loadBean(new \DummyBean());

        $model->filename = 'config.cfg';
        $model->downloads = 1;

        $filePath = 'path/to/location' . md5($model->filename);

        $expected = [
            'path' => $filePath,
            'filename' => $model->filename,
            'downloads' => 1,
        ];

        $productServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $productServiceMock->expects($this->atLeastOnce())
            ->method('getSavePath')
            ->willReturn($filePath);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn () => $productServiceMock);

        $this->service->setDi($di);
        $result = $this->service->toApiArray($model, null, new \Model_Admin());
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testuploadProductFileErrorUploadingFile(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $successfullyUploadedFileCount = 0;
        $requestMock = $this->getMockBuilder('\\' . \FOSSBilling\Request::class)->getMock();
        $requestMock->expects($this->atLeastOnce())
            ->method('hasFiles')
            ->willReturn($successfullyUploadedFileCount);
        $di = new \Pimple\Container();
        $di['request'] = $requestMock;
        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Error uploading file');
        $this->service->uploadProductFile($productModel);
    }

    public function testuploadProductFile(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $successfullyUploadedFileCount = 1;

        $file = [
            'name' => 'test',
            'tmp_name' => '12345',
        ];
        $fileMock = new \FOSSBilling\RequestFile($file);

        $requestMock = $this->getMockBuilder('\\' . \FOSSBilling\Request::class)->getMock();
        $requestMock->expects($this->atLeastOnce())
            ->method('hasFiles')
            ->willReturn($successfullyUploadedFileCount);
        $requestMock->expects($this->atLeastOnce())
            ->method('getUploadedFiles')
            ->willReturn([$fileMock]);

        $productServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $productServiceMock->expects($this->atLeastOnce())
            ->method('getSavePath');
        $productServiceMock->expects($this->atLeastOnce())
            ->method('removeOldFile');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn () => $productServiceMock);
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['request'] = $requestMock;
        $this->service->setDi($di);

        $result = $this->service->uploadProductFile($productModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testupdateProductFile(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $serviceDownloadableModel = new \Model_ServiceDownloadable();
        $serviceDownloadableModel->loadBean(new \DummyBean());

        $successfullyUploadedFileCount = 1;

        $file = [
            'name' => 'test',
            'tmp_name' => '12345',
        ];
        $fileMock = new \FOSSBilling\RequestFile($file);

        $requestMock = $this->getMockBuilder('\\' . \FOSSBilling\Request::class)->getMock();
        $requestMock->expects($this->atLeastOnce())
            ->method('hasFiles')
            ->willReturn($successfullyUploadedFileCount);
        $requestMock->expects($this->atLeastOnce())
            ->method('getUploadedFiles')
            ->willReturn([$fileMock]);

        $productServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $productServiceMock->expects($this->atLeastOnce())
            ->method('getSavePath');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn () => $productServiceMock);
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['request'] = $requestMock;

        $this->service->setDi($di);
        $result = $this->service->updateProductFile($serviceDownloadableModel, $orderModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testupdateProductFileFileNotUploaded(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $serviceDownloadableModel = new \Model_ServiceDownloadable();
        $serviceDownloadableModel->loadBean(new \DummyBean());

        $successfullyUploadedFileCount = 0;
        $requestMock = $this->getMockBuilder('\\' . \FOSSBilling\Request::class)->getMock();
        $requestMock->expects($this->atLeastOnce())
            ->method('hasFiles')
            ->willReturn($successfullyUploadedFileCount);
        $di = new \Pimple\Container();
        $di['request'] = $requestMock;
        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Error uploading file');
        $this->service->updateProductFile($serviceDownloadableModel, $orderModel);
    }

    public function testsendFileFileDoesNotExists(): void
    {
        $serviceDownloadableModel = new \Model_ServiceDownloadable();
        $serviceDownloadableModel->loadBean(new \DummyBean());
        $serviceDownloadableModel->filename = 'config.cfg';
        $serviceDownloadableModel->downloads = 1;

        $filePath = 'path/to/location' . md5($serviceDownloadableModel->filename);

        $productServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $productServiceMock->expects($this->atLeastOnce())
            ->method('getSavePath')
            ->willReturn($filePath);

        $di = new \Pimple\Container();
        $di['tools'] = $toolsMock;
        $di['mod_service'] = $di->protect(fn () => $productServiceMock);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('File cannot be downloaded at the moment. Please contact support');
        $this->service->sendFile($serviceDownloadableModel);
    }
}
