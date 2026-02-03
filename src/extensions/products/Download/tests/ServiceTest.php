<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\Download\Tests;

use FOSSBilling\ProductType\Download\DownloadHandler;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?DownloadHandler $service;

    public function setUp(): void
    {
        $this->service = new DownloadHandler();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
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

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray')
        ;

        $di = $this->getDi();
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
        $clientOrderModel->client_id = 1;
        $clientOrderModel->config = '{"filename" : "temp/asdcxTest.txt"}';

        $model = new \FOSSBilling\ProductType\Download\Entity\Download(1);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');

        $di = $this->getDiWithMockEntityManager();
        $di['validator'] = $validatorMock;

        $this->service->setDi($di);
        $result = $this->service->create($clientOrderModel);
        $this->assertInstanceOf('\FOSSBilling\ProductType\Download\Entity\Download', $result);
    }

    public function testActionDelete(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->service_id = 1;

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \FOSSBilling\ProductType\Download\Entity\Download(1));

        $di = $this->getDiWithMockEntityManagerForDelete();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->service->delete($clientOrderModel);
    }

    public function testSaveProductConfig(): void
    {
        $data = [
            'update_orders' => true,
        ];

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"filename": "test.txt"}';

        $dbMock = $this->createMock(\Box_Database::class);
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

        $updatedConfig = json_decode($productModel->config, true);
        $this->assertIsArray($updatedConfig);
        $this->assertEquals('test.txt', $updatedConfig['filename']);
        $this->assertTrue($updatedConfig['update_orders']);
        $this->assertNotNull($productModel->updated_at);
    }

    public function testSaveProductConfigWithExistingConfig(): void
    {
        $data = [
            'update_orders' => false,
        ];

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"filename": "existing.txt", "update_orders": true}';

        $dbMock = $this->createMock(\Box_Database::class);
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

        $updatedConfig = json_decode($productModel->config, true);
        $this->assertIsArray($updatedConfig);
        $this->assertEquals('existing.txt', $updatedConfig['filename']);
        $this->assertFalse($updatedConfig['update_orders']);
        $this->assertNotNull($productModel->updated_at);
    }

    public function testSaveProductConfigWithNoExistingConfig(): void
    {
        $data = [
            'update_orders' => true,
        ];

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = null;

        $dbMock = $this->createMock(\Box_Database::class);
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
        $updatedConfig = json_decode((string) $productModel->config, true);
        $this->assertIsArray($updatedConfig);
        $this->assertTrue($updatedConfig['update_orders']);
        $this->assertNotNull($productModel->updated_at);
    }
}
