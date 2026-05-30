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

    public function testAttachOrderConfig(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"filename" : "temp/asdcxTest.txt", "stored_filename": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"}';

        $data = [];

        $expected = array_merge(json_decode($productModel->config ?? '', true), $data);

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);
        $result = $this->service->attachOrderConfig($productModel, $data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testAttachOrderConfigRejectsInvalidStoredFilename(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"filename" : "download.txt", "stored_filename": "../config.php"}';

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);

        $data = [];
        $this->service->attachOrderConfig($productModel, $data);
    }

    public function testActionCreate(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->config = '{"filename" : "temp/asdcxTest.txt", "stored_filename": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"}';

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
        $this->assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $result->stored_filename);
    }

    public function testActionCreateRejectsInvalidStoredFilename(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->config = '{"filename" : "download.txt", "stored_filename": "../config.php"}';

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);

        $this->service->action_create($clientOrderModel);
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

        // Verify the config was updated correctly
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

        // Verify the config was updated correctly
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
        $updatedConfig = json_decode($productModel->config ?? '', true);
        $this->assertIsArray($updatedConfig);
        $this->assertArrayHasKey('update_orders', $updatedConfig);
        $this->assertTrue($updatedConfig['update_orders']);
        $this->assertNotNull($productModel->updated_at);
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

    public function testUploadsWithSameOriginalFilenameUseSeparateStoredFiles(): void
    {
        $di = $this->createUploadDi();
        $this->service->setDi($di);

        $productA = $this->createProductModel(null);
        $productB = $this->createProductModel(null);

        $this->uploadFile($di, $productA, 'download.txt', 'PRODUCT_A_CONTENT');
        $this->uploadFile($di, $productB, 'download.txt', 'PRODUCT_B_CONTENT');

        $configA = json_decode($productA->config, true);
        $configB = json_decode($productB->config, true);

        $this->assertSame('download.txt', $configA['filename']);
        $this->assertSame('download.txt', $configB['filename']);
        $this->assertNotSame($configA['stored_filename'], $configB['stored_filename']);

        $pathA = \Symfony\Component\Filesystem\Path::join(PATH_UPLOADS, $configA['stored_filename']);
        $pathB = \Symfony\Component\Filesystem\Path::join(PATH_UPLOADS, $configB['stored_filename']);

        try {
            $this->assertSame('PRODUCT_A_CONTENT', file_get_contents($pathA));
            $this->assertSame('PRODUCT_B_CONTENT', file_get_contents($pathB));
            $this->assertSame('PRODUCT_A_CONTENT', $this->service->sendProductFile($productA)->getContent());
            $this->assertSame('PRODUCT_B_CONTENT', $this->service->sendProductFile($productB)->getContent());
        } finally {
            @unlink($pathA);
            @unlink($pathB);
        }
    }

    public function testSendProductFileRequiresStoredFilename(): void
    {
        $di = $this->createUploadDi();
        $this->service->setDi($di);

        $product = $this->createProductModel('{"filename": "legacy.txt"}');

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('No file associated with this product.');

        $this->service->sendProductFile($product);
    }

    public function testSendProductFileRejectsTraversalStoredFilename(): void
    {
        $di = $this->createUploadDi();
        $this->service->setDi($di);

        $product = $this->createProductModel('{"filename": "download.txt", "stored_filename": "../config.php"}');

        $this->expectException(\FOSSBilling\Exception::class);

        $this->service->sendProductFile($product);
    }

    public function testSendFileRejectsTraversalStoredFilename(): void
    {
        $di = $this->createUploadDi();
        $this->service->setDi($di);

        $serviceDownloadable = new \Model_ServiceDownloadable();
        $serviceDownloadable->loadBean(new \DummyBean());
        $serviceDownloadable->filename = 'download.txt';
        $serviceDownloadable->stored_filename = '../config.php';

        $this->expectException(\FOSSBilling\Exception::class);

        $this->service->sendFile($serviceDownloadable);
    }

    public function testReplacingProductFileDoesNotDeleteInvalidOldStoredFilenameOutsideUploads(): void
    {
        $di = $this->createUploadDi();
        $this->service->setDi($di);

        $outsidePath = \Symfony\Component\Filesystem\Path::join(dirname(PATH_UPLOADS), 'downloadable-secret.txt');
        file_put_contents($outsidePath, 'SECRET');

        $product = $this->createProductModel(json_encode([
            'filename' => 'old.txt',
            'stored_filename' => '../downloadable-secret.txt',
        ]));

        try {
            $this->uploadFile($di, $product, 'download.txt', 'NEW_CONTENT');

            $this->assertFileExists($outsidePath);
            $this->assertSame('SECRET', file_get_contents($outsidePath));
        } finally {
            @unlink($outsidePath);
        }
    }

    private function invokeValidateFileUpload(\Symfony\Component\HttpFoundation\File\UploadedFile $file): void
    {
        $reflection = new \ReflectionMethod(Service::class, 'validateFileUpload');
        $reflection->invoke($this->service, $file);
    }

    private function createUploadDi(): \Pimple\Container
    {
        $di = new \Pimple\Container();
        $di['db'] = new class {
            public function store($model): int
            {
                return 1;
            }

            public function getCell(string $query, array $bindings = []): int
            {
                return 0;
            }
        };
        $di['logger'] = new class {
            public function info(...$args): void
            {
            }

            public function warn(...$args): void
            {
            }
        };
        $di['mod_service'] = $di->protect(fn (): object => new class {
            public function getOrdersForProduct(\Model_Product $product): array
            {
                return [];
            }
        });

        return $di;
    }

    private function createProductModel(?string $config): \Model_Product
    {
        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());
        $product->id = random_int(1000, 9999);
        $product->config = $config;

        return $product;
    }

    private function uploadFile(\Pimple\Container $di, \Model_Product $product, string $filename, string $contents): void
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'fb-downloadable-');
        file_put_contents($temporaryFile, $contents);

        $file = new \Symfony\Component\HttpFoundation\File\UploadedFile($temporaryFile, $filename, 'text/plain', UPLOAD_ERR_OK, true);
        $di['request'] = new \Symfony\Component\HttpFoundation\Request([], [], [], [], ['file_data' => $file]);

        $this->service->uploadProductFile($product);
    }
}
