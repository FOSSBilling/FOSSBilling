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
use Box\Mod\Servicedownloadable\Service;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

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
    $clientOrderModel = createEntity(\Box\Mod\Order\Entity\Order::class);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn(createEntity(\Box\Mod\Servicedownloadable\Entity\ServiceDownloadable::class));

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('remove')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

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

    $productModel = serviceDownloadableCreateProductEntity(config: '{"filename": "test.txt"}');
    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service->setDi($di);
    $result = $service->saveProductConfig($productModel, $data);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();

    $updatedConfig = json_decode($productModel->getConfig() ?? '', true);
    expect($updatedConfig)->toBeArray();
    expect($updatedConfig['filename'])->toEqual('test.txt');
    expect($updatedConfig['update_orders'])->toBeTrue();
    expect($productModel->getUpdatedAt())->not->toBeNull();
});

test('save product config with existing config', function (): void {
    $service = new Service();
    $data = [
        'update_orders' => false,
    ];

    $productModel = serviceDownloadableCreateProductEntity(config: '{"filename": "existing.txt", "update_orders": true}');
    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = new Pimple\Container();
    $di['em'] = $emMock;

    $service->setDi($di);
    $result = $service->saveProductConfig($productModel, $data);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();

    $updatedConfig = json_decode($productModel->getConfig() ?? '', true);
    expect($updatedConfig)->toBeArray();
    expect($updatedConfig['filename'])->toEqual('existing.txt');
    expect($updatedConfig['update_orders'])->toBeFalse();
    expect($productModel->getUpdatedAt())->not->toBeNull();
});

test('save product config with no existing config', function (): void {
    $service = new Service();
    $data = [
        'update_orders' => true,
    ];

    $productModel = serviceDownloadableCreateProductEntity();
    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

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
