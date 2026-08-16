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
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn(new Model_ServiceDownloadable());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('trash')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
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

test('clientSettableConfigKeys returns the downloadable allowlist', function (): void {
    $service = new Service();
    $allowed = $service->clientSettableConfigKeys();

    expect($allowed)->toBeArray();
    expect($allowed)->toContain('period');
    expect($allowed)->toContain('quantity');
    // `files` is admin-controlled and must not be client-settable.
    expect($allowed)->not->toContain('files');
});

test('attachOrderConfig merges admin config over client data (admin wins)', function (): void {
    $service = new Service();
    $service->setDi(container());

    // Admin product config carries the authoritative filename/stored_filename
    // plus an admin-controlled flag (`update_orders`). Client input also carries
    // a client-injected `update_orders` value - it must lose to admin.
    $storedFilename = str_repeat('b', 64);
    $productModel = serviceDownloadableCreateProductEntity(config: json_encode([
        'filename' => 'installer.zip',
        'stored_filename' => $storedFilename,
        'update_orders' => true,
    ], JSON_THROW_ON_ERROR));

    $clientData = [
        'period' => '1M',
        'quantity' => 1,
        'update_orders' => false, // injected - admin must win
    ];

    $result = $service->attachOrderConfig($productModel, $clientData);

    // Allowlisted client keys are preserved (the merge is admin-over-client,
    // not admin-replaces-entire-client).
    expect($result['period'])->toBe('1M');
    expect($result['quantity'])->toBe(1);

    // Admin-controlled keys take precedence over the client-supplied values.
    expect($result['update_orders'])->toBeTrue('client override of admin-controlled update_orders leaked through merge');
    expect($result['filename'])->toBe('installer.zip');
    expect($result['stored_filename'])->toBe($storedFilename);
});

test('attachOrderConfig throws when product has no filename configured', function (): void {
    $service = new Service();
    $service->setDi(container());
    $productModel = serviceDownloadableCreateProductEntity(config: '{}');

    expect(fn (): array => $service->attachOrderConfig($productModel, ['period' => '1M']))
        ->toThrow(Exception::class, 'Product is not configured completely.');
});
