<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\Download\Api;

use FOSSBilling\ProductType\Download\Api\Admin;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Admin $api;

    public function setUp(): void
    {
        $this->api = new Admin();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testUpdateOrderNotActivated(): void
    {
        $data['order_id'] = 1;
        $model = new \Model_ClientOrder();

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        $this->api->update($data);
    }

    public function testUpdate(): void
    {
        $data['order_id'] = 1;
        $model = new \Model_ClientOrder();

        $modelDownloadableModel = new \Model_ServiceDownload();

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Download\DownloadHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateProductFile')
            ->willReturn(true);

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($modelDownloadableModel);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testConfigSave(): void
    {
        $data = [
            'id' => 1,
            'update_orders' => true,
        ];

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"filename": "test.txt"}';

        $serviceMock = $this->getMockBuilder('\\' . \FOSSBilling\ProductType\Download\DownloadHandler::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('saveProductConfig')
            ->with($productModel, $data)
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('Product', $data['id'], 'Product not found')
            ->willReturn($productModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->config_save($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testConfigSaveProductNotFound(): void
    {
        $data = [
            'id' => 999,
            'update_orders' => true,
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('Product', $data['id'], 'Product not found')
            ->willThrowException(new \FOSSBilling\Exception('Product not found'));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product not found');
        $this->api->config_save($data);
    }

    public function testSendFileProductNotFound(): void
    {
        $data = ['id' => 999];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('Product', $data['id'], 'Product not found')
            ->willThrowException(new \FOSSBilling\Exception('Product not found'));

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product not found');
        $this->api->send_file($data);
    }

    public function testSendFileNoFileConfigured(): void
    {
        $data = ['id' => 1];

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{}';

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Download\DownloadHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('sendProductFile')
            ->with($productModel)
            ->willThrowException(new \FOSSBilling\Exception('No file associated with this product.', null, 404));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('Product', $data['id'], 'Product not found')
            ->willReturn($productModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('No file associated with this product.');
        $this->api->send_file($data);
    }

    public function testSendFileFileNotFound(): void
    {
        $data = ['id' => 1];

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"filename": "test.txt"}';

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Download\DownloadHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('sendProductFile')
            ->with($productModel)
            ->willThrowException(new \FOSSBilling\Exception('File cannot be downloaded at the moment. Please contact support.', null, 404));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('Product', $data['id'], 'Product not found')
            ->willReturn($productModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('File cannot be downloaded at the moment. Please contact support.');
        $this->api->send_file($data);
    }

    public function testSendFile(): void
    {
        $data = ['id' => 1];

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"filename": "test.txt"}';

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Download\DownloadHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('sendProductFile')
            ->with($productModel)
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('Product', $data['id'], 'Product not found')
            ->willReturn($productModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->send_file($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}
