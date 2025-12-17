<?php

declare(strict_types=1);

namespace Box\Mod\Servicedownloadable;

#[PHPUnit\Framework\Attributes\Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;

    public function setUp(): void
    {
        $this->service = new Service();
    }

    public function testGetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testAttachOrderConfig(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"filename" : "temp/asdcxTest.txt"}';

        $data = [];

        $expected = array_merge(json_decode($productModel->config ?? '', true), $data);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);
        $result = $this->service->attachOrderConfig($productModel, $data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testActionCreate(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->config = '{"filename" : "temp/asdcxTest.txt"}';

        $model = new \Model_ServiceDownloadable();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->service->setDi($di);
        $result = $this->service->action_create($clientOrderModel);
        $this->assertInstanceOf('\Model_ServiceDownloadable', $result);
    }

    public function testActionDelete(): void
    {
        $clientOrderModel = new \Model_ClientOrder();

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceDownloadable());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->service->action_delete($clientOrderModel);
    }

    public function testsaveProductConfig(): void
    {
        $data = [
            'update_orders' => true,
        ];

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"filename": "test.txt"}';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($productModel)
            ->willReturn(1);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->saveProductConfig($productModel, $data);

        $this->assertIsBool($result);
        $this->assertTrue($result);

        // Verify the config was updated correctly
        $updatedConfig = json_decode($productModel->config, true);
        $this->assertIsArray($updatedConfig);
        $this->assertEquals('test.txt', $updatedConfig['filename']);
        $this->assertTrue($updatedConfig['update_orders']);
        $this->assertNotNull($productModel->updated_at);
    }

    public function testsaveProductConfigWithExistingConfig(): void
    {
        $data = [
            'update_orders' => false,
        ];

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"filename": "existing.txt", "update_orders": true}';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($productModel)
            ->willReturn(1);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->saveProductConfig($productModel, $data);

        $this->assertIsBool($result);
        $this->assertTrue($result);

        // Verify the config was updated correctly
        $updatedConfig = json_decode($productModel->config, true);
        $this->assertIsArray($updatedConfig);
        $this->assertEquals('existing.txt', $updatedConfig['filename']);
        $this->assertFalse($updatedConfig['update_orders']);
        $this->assertNotNull($productModel->updated_at);
    }

    public function testsaveProductConfigWithNoExistingConfig(): void
    {
        $data = [
            'update_orders' => true,
        ];

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = null;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($productModel)
            ->willReturn(1);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->saveProductConfig($productModel, $data);

        $this->assertIsBool($result);
        $this->assertTrue($result);

        // Verify the config was created correctly
        $updatedConfig = json_decode($productModel->config, true);
        $this->assertIsArray($updatedConfig);
        $this->assertTrue($updatedConfig['update_orders']);
        $this->assertNotNull($productModel->updated_at);
    }
}
