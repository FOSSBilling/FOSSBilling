<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Order\Service as OrderService;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicedownloadable\Entity\ServiceDownloadable;
use Box\Mod\Servicedownloadable\Entity\ServiceDownloadableFile;
use Box\Mod\Servicedownloadable\Service;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Tests\Helpers\container;

function serviceDownloadableCreateProductEntity(?int $id = null, ?string $config = null): Product
{
    $product = new Product();
    if ($id !== null) {
        $reflection = new ReflectionProperty($product, 'id');
        $reflection->setValue($product, $id);
    }
    if ($config !== null) {
        $product->setConfig($config);
    }

    return $product;
}

test('action delete', function (): void {
    $service = new Service();
    $clientOrderModel = new Model_ClientOrder();

    $orderServiceMock = Mockery::mock(OrderService::class);
    $downloadable = new ServiceDownloadable();
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($downloadable);
    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('remove')->once()->with($downloadable);
    $emMock->shouldReceive('flush')->once();

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $service->action_delete($clientOrderModel);
});

test('save product config', function (): void {
    $service = new Service();
    $data = [
        'update_orders' => true,
    ];

    $productModel = serviceDownloadableCreateProductEntity(config: '{"files": []}');
    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->once();

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service->setDi($di);
    $result = $service->saveProductConfig($productModel, $data);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();

    $updatedConfig = json_decode($productModel->getConfig() ?? '', true);
    expect($updatedConfig)->toBeArray();
    expect($updatedConfig['files'])->toBe([]);
    expect($updatedConfig['update_orders'])->toBeTrue();
    expect($productModel->getUpdatedAt())->not->toBeNull();
});

test('save product config with existing config', function (): void {
    $service = new Service();
    $data = [
        'update_orders' => false,
    ];

    $productModel = serviceDownloadableCreateProductEntity(config: '{"files": [], "update_orders": true}');
    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->once();

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service->setDi($di);
    $result = $service->saveProductConfig($productModel, $data);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();

    $updatedConfig = json_decode($productModel->getConfig() ?? '', true);
    expect($updatedConfig)->toBeArray();
    expect($updatedConfig['files'])->toBe([]);
    expect($updatedConfig['update_orders'])->toBeFalse();
    expect($productModel->getUpdatedAt())->not->toBeNull();
});

test('creates a downloadable service with all snapshotted files', function (): void {
    $service = new Service();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->id = 10;
    $order->client_id = 20;
    $order->config = json_encode([
        'files' => [
            [
                'id' => str_repeat('a', 32),
                'filename' => 'installer.zip',
                'stored_filename' => str_repeat('b', 64),
                'label' => 'Installer',
                'description' => 'Application files',
            ],
            [
                'id' => str_repeat('c', 32),
                'filename' => 'manual.pdf',
                'stored_filename' => str_repeat('d', 64),
                'label' => 'Manual',
                'description' => null,
            ],
        ],
    ]);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->once()->with(Mockery::on(
        static fn (ServiceDownloadable $downloadable): bool => $downloadable->getClientId() === 20 && $downloadable->getFiles()->count() === 2,
    ));
    $emMock->shouldReceive('flush')->once();
    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $downloadable = $service->action_create($order);

    expect($downloadable->getFiles())->toHaveCount(2)
        ->and($downloadable->getFiles()->first())->toBeInstanceOf(ServiceDownloadableFile::class)
        ->and($downloadable->getFiles()->first()->getLabel())->toBe('Installer');
});

test('removes an order file and its config in one Doctrine transaction', function (): void {
    $file = new ServiceDownloadableFile(str_repeat('a', 32), 'file.zip', str_repeat('b', 64));
    (new ReflectionProperty($file, 'id'))->setValue($file, 2);
    $downloadable = new ServiceDownloadable();
    $downloadable->addFile($file);

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->id = 10;
    $order->config = json_encode(['files' => [[
        'id' => str_repeat('a', 32),
        'filename' => 'file.zip',
        'stored_filename' => str_repeat('b', 64),
    ]]]);

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('update')
        ->once()
        ->with('client_order', Mockery::on(static fn (array $data): bool => json_decode($data['config'], true) === ['files' => []]
            && is_string($data['updated_at'])), ['id' => 10]);

    $repository = Mockery::mock(Box\Mod\Servicedownloadable\Repository\ServiceDownloadableFileRepository::class);
    $repository->shouldReceive('isStoredFilenameReferenced')->once()->andReturnTrue();

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('wrapInTransaction')
        ->once()
        ->andReturnUsing(static fn (callable $callback): mixed => $callback());
    $em->shouldReceive('getConnection')->once()->andReturn($connection);
    $em->shouldReceive('getRepository')->once()->with(ServiceDownloadableFile::class)->andReturn($repository);

    $di = container();
    $di['em'] = $em;
    $service = new Service();
    $service->setDi($di);

    expect($service->removeOrderFile($downloadable, $order, 2))->toBeTrue()
        ->and($downloadable->getFiles())->toHaveCount(0)
        ->and(json_decode($order->config, true))->toBe(['files' => []]);
});

test('rejects duplicate file IDs in order configuration', function (): void {
    $service = new Service();
    $file = [
        'id' => str_repeat('a', 32),
        'filename' => 'installer.zip',
        'stored_filename' => str_repeat('b', 64),
    ];
    $data = ['files' => [$file, $file]];

    expect(fn () => $service->validateOrderData($data))
        ->toThrow(FOSSBilling\Exception::class, 'duplicate file IDs');
});

test('save product config with no existing config', function (): void {
    $service = new Service();
    $data = [
        'update_orders' => true,
    ];

    $productModel = serviceDownloadableCreateProductEntity();
    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->once();

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service->setDi($di);
    $result = $service->saveProductConfig($productModel, $data);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();

    $updatedConfig = json_decode($productModel->getConfig() ?? '', true);
    expect($updatedConfig)->toBeArray();
    expect($updatedConfig)->toHaveKey('update_orders');
    expect($updatedConfig['update_orders'])->toBeTrue();
    expect($productModel->getUpdatedAt())->not->toBeNull();
});

test('validate file upload allows known extension with octet stream mime', function (): void {
    $service = new Service();
    $service->setDi(container());

    $file = Mockery::mock(UploadedFile::class)->shouldIgnoreMissing();
    $file->shouldReceive('getClientOriginalExtension')->andReturn('exe');
    $file->shouldReceive('getClientOriginalName')->andReturn('installer.exe');
    $file->shouldReceive('getMimeType')->andReturn('application/octet-stream');

    $reflection = new ReflectionMethod(Service::class, 'validateFileUpload');
    $reflection->invoke($service, $file);

    expect(true)->toBeTrue();
});

test('validate file upload rejects unknown extension', function (): void {
    $service = new Service();
    $service->setDi(container());

    $file = Mockery::mock(UploadedFile::class)->shouldIgnoreMissing();
    $file->shouldReceive('getClientOriginalExtension')->andReturn('php');
    $file->shouldReceive('getClientOriginalName')->andReturn('shell.php');
    $file->shouldReceive('getMimeType')->andReturn('application/x-httpd-php');

    $reflection = new ReflectionMethod(Service::class, 'validateFileUpload');

    expect(fn (): mixed => $reflection->invoke($service, $file))
        ->toThrow(FOSSBilling\Exception::class);
});
