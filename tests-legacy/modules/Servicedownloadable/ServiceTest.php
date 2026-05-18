<?php

declare(strict_types=1);

namespace Box\Mod\Servicedownloadable;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service = null;

    public function setUp(): void
    {
        $this->service = new Service();
    }

    private function createProductEntity(?int $id = null, ?string $config = null): \Box\Mod\Product\Entity\Product
    {
        $product = new \Box\Mod\Product\Entity\Product();
        if ($id !== null) {
            $reflection = new \ReflectionProperty($product, 'id');
            $reflection->setAccessible(true);
            $reflection->setValue($product, $id);
        }
        if ($config !== null) {
            $product->setConfig($config);
        }

        return $product;
    }

    public function testAttachOrderConfig(): void
    {
        $productModel = $this->createProductEntity(config: '{"filename" : "temp/asdcxTest.txt"}');

        $data = [];

        $expected = array_merge(json_decode($productModel->getConfig() ?? '', true) ?? [], $data);

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);

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
        $clientOrderModel->config = '{"filename" : "temp/asdcxTest.txt"}';

        $model = new \Model_ServiceDownloadable();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(1);
        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);

        $di = $this->getDi();
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

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->service->action_delete($clientOrderModel);
    }

    public function testSaveProductConfig(): void
    {
        $data = [
            'update_orders' => true,
        ];

        $productModel = $this->createProductEntity(config: '{"filename": "test.txt"}');
        $emMock = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $emMock->expects($this->once())
            ->method('flush');

        $di = new \Pimple\Container();
        $di['em'] = $emMock;

        $this->service->setDi($di);
        $result = $this->service->saveProductConfig($productModel, $data);

        $this->assertIsBool($result);
        $this->assertTrue($result);

        // Verify the config was updated correctly
        $updatedConfig = json_decode($productModel->getConfig() ?? '', true);
        $this->assertIsArray($updatedConfig);
        $this->assertEquals('test.txt', $updatedConfig['filename']);
        $this->assertTrue($updatedConfig['update_orders']);
        $this->assertNotNull($productModel->getUpdatedAt());
    }

    public function testSaveProductConfigWithExistingConfig(): void
    {
        $data = [
            'update_orders' => false,
        ];

        $productModel = $this->createProductEntity(config: '{"filename": "existing.txt", "update_orders": true}');
        $emMock = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $emMock->expects($this->once())
            ->method('flush');

        $di = new \Pimple\Container();
        $di['em'] = $emMock;

        $this->service->setDi($di);
        $result = $this->service->saveProductConfig($productModel, $data);

        $this->assertIsBool($result);
        $this->assertTrue($result);

        // Verify the config was updated correctly
        $updatedConfig = json_decode($productModel->getConfig() ?? '', true);
        $this->assertIsArray($updatedConfig);
        $this->assertEquals('existing.txt', $updatedConfig['filename']);
        $this->assertFalse($updatedConfig['update_orders']);
        $this->assertNotNull($productModel->getUpdatedAt());
    }

    public function testSaveProductConfigWithNoExistingConfig(): void
    {
        $data = [
            'update_orders' => true,
        ];

        $productModel = $this->createProductEntity();
        $emMock = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $emMock->expects($this->once())
            ->method('flush');

        $di = new \Pimple\Container();
        $di['em'] = $emMock;

        $this->service->setDi($di);
        $result = $this->service->saveProductConfig($productModel, $data);

        $this->assertIsBool($result);
        $this->assertTrue($result);

        // Verify the config was created correctly
        $updatedConfig = json_decode($productModel->getConfig() ?? '', true);
        $this->assertIsArray($updatedConfig);
        $this->assertArrayHasKey('update_orders', $updatedConfig);
        $this->assertTrue($updatedConfig['update_orders']);
        $this->assertNotNull($productModel->getUpdatedAt());
    }

    public function testValidateFileUploadAllowsKnownExtensionWithOctetStreamMime(): void
    {
        $this->service->setDi($this->getDi());

        $file = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('exe');
        $file->method('getClientOriginalName')->willReturn('installer.exe');
        $file->method('getMimeType')->willReturn('application/octet-stream');

        $this->invokeValidateFileUpload($file);

        $this->addToAssertionCount(1);
    }

    public function testValidateFileUploadRejectsUnknownExtension(): void
    {
        $this->service->setDi($this->getDi());

        $file = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('php');
        $file->method('getClientOriginalName')->willReturn('shell.php');
        $file->method('getMimeType')->willReturn('application/x-httpd-php');

        $this->expectException(\FOSSBilling\Exception::class);

        $this->invokeValidateFileUpload($file);
    }

    private function invokeValidateFileUpload(\Symfony\Component\HttpFoundation\File\UploadedFile $file): void
    {
        $reflection = new \ReflectionMethod(Service::class, 'validateFileUpload');
        $reflection->invoke($this->service, $file);
    }
}
