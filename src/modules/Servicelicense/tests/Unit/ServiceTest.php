<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Order\Service as OrderService;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicelicense\Entity\ServiceLicense;
use Box\Mod\Servicelicense\Server;
use Box\Mod\Servicelicense\Service;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function serviceLicenseCreateProductEntity(string $config): Product
{
    $product = new Product();
    $product->setConfig($config);

    return $product;
}

function serviceLicenseCreateEmMock(): EntityManagerInterface&Mockery\MockInterface
{
    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->byDefault();
    $emMock->shouldReceive('flush')->byDefault();
    $emMock->shouldReceive('remove')->byDefault();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    return $emMock;
}

function serviceLicenseCreateDi(): Pimple\Container
{
    $di = container();
    $di['em'] = serviceLicenseCreateEmMock();

    return $di;
}

test('attach order config empty product config', function (): void {
    $service = new Service();
    $productModel = serviceLicenseCreateProductEntity('{}');
    $data = [];

    $result = $service->attachOrderConfig($productModel, $data);
    expect($result)->toBeArray();
    expect($result)->toBe([]);
});

test('attach order config', function (): void {
    $service = new Service();
    $productModel = serviceLicenseCreateProductEntity('["hello", "world"]');
    $data = ['testing' => 'phase'];
    $expected = array_merge(json_decode($productModel->getConfig() ?? '', true) ?? [], $data);

    $result = $service->attachOrderConfig($productModel, $data);
    expect($result)->toBeArray();
    expect($result)->toEqual($expected);
});

test('get license plugins', function (): void {
    $service = new Service();
    $result = $service->getLicensePlugins();
    expect($result)->toBeArray();
});

test('action create', function (): void {
    $service = new Service();
    $clientOrderModel = createEntity(\Box\Mod\Order\Entity\Order::class);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->action_create($clientOrderModel);
    expect($result)->toBeInstanceOf(ServiceLicense::class);
});

test('action activate', function (): void {
    $service = new Service();
    $clientOrderModel = createEntity(\Box\Mod\Order\Entity\Order::class);

    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, ['plugin' => 'Simple']);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceLicenseModel);

    $licenseRepoMock = Mockery::mock(Box\Mod\Servicelicense\Repository\ServiceLicenseRepository::class);
    $licenseRepoMock->shouldReceive('findOneByLicenseKey')->andReturn(null);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->with(ServiceLicense::class)->andReturn($licenseRepoMock);
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->action_activate($clientOrderModel);
    expect($result)->toBeTrue();
});

test('action activate license collision', function (): void {
    $service = new Service();
    $clientOrderModel = createEntity(\Box\Mod\Order\Entity\Order::class);

    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, ['plugin' => 'Simple']);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn(['iterations' => 3]);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceLicenseModel);

    $licenseRepoMock = Mockery::mock(Box\Mod\Servicelicense\Repository\ServiceLicenseRepository::class);
    $collisionEntity = new ServiceLicense();
    $collisionEntity->setId(99);
    $licenseRepoMock->shouldReceive('findOneByLicenseKey')
        ->times(3)
        ->andReturn($collisionEntity, $collisionEntity, null);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->with(ServiceLicense::class)->andReturn($licenseRepoMock);
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->action_activate($clientOrderModel);
    expect($result)->toBeTrue();
});

test('action activate license collision max iterations exception', function (): void {
    $service = new Service();
    $clientOrderModel = createEntity(\Box\Mod\Order\Entity\Order::class);

    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, ['plugin' => 'Simple']);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceLicenseModel);

    $licenseRepoMock = Mockery::mock(Box\Mod\Servicelicense\Repository\ServiceLicenseRepository::class);
    $collisionMaxEntity = new ServiceLicense();
    $collisionMaxEntity->setId(99);
    $licenseRepoMock->shouldReceive('findOneByLicenseKey')->atLeast()->once()->andReturn($collisionMaxEntity);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldNotReceive('persist');
    $emMock->shouldNotReceive('flush');
    $emMock->shouldReceive('getRepository')->with(ServiceLicense::class)->andReturn($licenseRepoMock);
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    expect(fn (): bool => $service->action_activate($clientOrderModel))
        ->toThrow(FOSSBilling\Exception::class);
});

test('action activate plugin not found', function (): void {
    $service = new Service();
    $clientOrderModel = createEntity(\Box\Mod\Order\Entity\Order::class);

    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, ['plugin' => 'TestPlugin']);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceLicenseModel);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    expect(fn (): bool => $service->action_activate($clientOrderModel))
        ->toThrow(FOSSBilling\Exception::class, "License plugin {$serviceLicenseModel->plugin} was not found.");
});

test('action activate order activation exception', function (): void {
    $service = new Service();
    $clientOrderModel = createEntity(\Box\Mod\Order\Entity\Order::class);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn(null);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    expect(fn (): bool => $service->action_activate($clientOrderModel))
        ->toThrow(FOSSBilling\Exception::class, 'Could not activate order. Service was not created');
});

test('action delete', function (): void {
    $service = new Service();
    $clientOrderModel = createEntity(\Box\Mod\Order\Entity\Order::class);

    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceLicenseModel);

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

test('reset', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class);

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();
    $di['events_manager'] = $eventMock;

    $service->setDi($di);
    $result = $service->reset($serviceLicenseModel);
    expect($result)->toBeTrue();
});

test('is license active', function (): void {
    $service = new Service();
    $clientOrderModel = createEntity(\Box\Mod\Order\Entity\Order::class, ['status' => \Box\Mod\Order\Entity\Order::STATUS_ACTIVE]);

    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getServiceOrder')->atLeast()->once()->andReturn($clientOrderModel);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $result = $service->isLicenseActive($serviceLicenseModel);
    expect($result)->toBeTrue();
});

test('is license not active', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getServiceOrder')->atLeast()->once()->andReturn(null);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $result = $service->isLicenseActive($serviceLicenseModel);
    expect($result)->toBeFalse();
});

test('is license inactive when order has expired', function (): void {
    $service = new Service();

    $expiredOrder = createEntity(\Box\Mod\Order\Entity\Order::class, [
        'status' => \Box\Mod\Order\Entity\Order::STATUS_ACTIVE,
        'expires_at' => date('Y-m-d H:i:s', time() - 3600),
    ]);

    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getServiceOrder')
        ->atLeast()->once()
        ->andReturn($expiredOrder);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $result = $service->isLicenseActive($serviceLicenseModel);
    expect($result)->toBeFalse();
});

test('is valid ip', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, ['ips' => '{}']);
    $value = '1.1.1.1';

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);

    $result = $service->isValidIp($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid ip when ip is not in allowed list and validation is not enforced returns true', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, ['ips' => '["2.2.2.2"]', 'validate_ip' => false]);
    $value = '1.1.1.1';

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);

    $result = $service->isValidIp($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid ip when validate_ip is set and ip does not match returns false', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, [
        'ips' => '["2.2.2.2"]',
        'validate_ip' => '3.3.3.3',
    ]);
    $value = '1.1.1.1';

    $result = $service->isValidIp($serviceLicenseModel, $value);
    expect($result)->toBeFalse();
});

test('is valid version', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, ['versions' => '{}']);
    $value = '1.0';

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);

    $result = $service->isValidVersion($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid version test2', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, ['versions' => '["2.0"]']);
    $value = '1.0';

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);

    $result = $service->isValidVersion($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid version test3', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, [
        'versions' => '["2.0"]',
        'validate_version' => '3.3.3.3',
    ]);
    $value = '1.0';

    $result = $service->isValidVersion($serviceLicenseModel, $value);
    expect($result)->toBeFalse();
});

test('is valid path', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, ['paths' => '{}']);
    $value = '/var';

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);

    $result = $service->isValidPath($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid path test2', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, ['paths' => '["/"]']);
    $value = '/var';

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);

    $result = $service->isValidPath($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid path test3', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, [
        'paths' => '["/"]',
        'validate_path' => '/user',
    ]);
    $value = '/var';

    $result = $service->isValidPath($serviceLicenseModel, $value);
    expect($result)->toBeFalse();
});

test('is valid host', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, ['hosts' => '{}']);
    $value = 'site.com';

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);

    $result = $service->isValidHost($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid host test2', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, ['hosts' => '["fossbilling.org"]', 'validate_host' => false]);
    $value = 'site.com';

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);

    $result = $service->isValidHost($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid host test3', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, [
        'hosts' => '["fossbilling.org"]',
        'validate_host' => 'example.com',
    ]);
    $value = 'site.com';

    $result = $service->isValidHost($serviceLicenseModel, $value);
    expect($result)->toBeFalse();
});

test('get additional params', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class, ['plugin' => 'Simple']);

    $result = $service->getAdditionalParams($serviceLicenseModel);
    expect($result)->toBeArray();
});

test('get owner name', function (): void {
    $service = new Service();
    $client = new Client();
    $client->setFirstName('John');
    $client->setLastName('Smith');

    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class);

    $expected = $client->getFirstName() . ' ' . $client->getLastName();

    $clientRepo = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepo->shouldReceive('find')->atLeast()->once()->andReturn($client);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Client::class)->andReturn($clientRepo);
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);

    $result = $service->getOwnerName($serviceLicenseModel);
    expect($result)->toBeString();
    expect($result)->toEqual($expected);
});

test('get expiration date', function (): void {
    $service = new Service();
    $expected = '2004-02-12 15:19:21';
    $clientOrderModel = createEntity(\Box\Mod\Order\Entity\Order::class, ['expires_at' => $expected]);

    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getServiceOrder')->atLeast()->once()->andReturn($clientOrderModel);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->getExpirationDate($serviceLicenseModel);
    expect($result)->toBeInstanceOf(\DateTimeInterface::class);
});

test('to api array', function (): void {
    $service = new Service();
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class);

    $expected = [
        'license_key' => '',
        'validate_ip' => '',
        'validate_host' => '',
        'validate_version' => '',
        'validate_path' => '',
        'ips' => '',
        'hosts' => '',
        'paths' => '',
        'versions' => '',
        'pinged_at' => '',
        'plugin' => '',
    ];

    $result = $service->toApiArray($serviceLicenseModel, false, createEntity(\Box\Mod\Staff\Entity\Admin::class));
    expect($result)->toBeArray();
    expect(count(array_diff(array_keys($expected), array_keys($result))))->toBe(0);
});

test('update', function (): void {
    $service = new Service();
    $data = [
        'license_key' => '123456Licence',
        'validate_ip' => '1.1.1.1',
        'validate_host' => 'fossbilling.org',
        'validate_version' => '1.0',
        'validate_path' => '/usr',
        'ips' => "2.2.2.2\n",
        'pinged_at' => '',
        'plugin' => 'Simple',
    ];
    $serviceLicenseModel = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);
    $result = $service->update($serviceLicenseModel, $data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('check license details format eq 2', function (): void {
    $service = new Service();
    $setChannelCalled = 0;
    $loggerMock = new class($setChannelCalled) extends Box_Log {
        public function __construct(public int &$setChannelCalled)
        {
        }

        public function setChannel(string $channel): static
        {
            ++$this->setChannelCalled;

            return $this;
        }
    };

    $data = [
        'format' => 2,
    ];

    $licenseServerMock = Mockery::mock(Server::class);
    $licenseServerMock->shouldReceive('process')->atLeast()->once()->andReturn([]);

    $di = container();
    $di['logger'] = $loggerMock;
    $di['license_server'] = $licenseServerMock;
    $service->setDi($di);

    $result = $service->checkLicenseDetails($data);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('error');
    expect($result)->toHaveKey('error_code');
    expect($setChannelCalled)->toBeGreaterThanOrEqual(1);
});

test('check license details', function (): void {
    $service = new Service();
    $setChannelCalled = 0;
    $loggerMock = new class($setChannelCalled) extends Box_Log {
        public function __construct(public int &$setChannelCalled)
        {
        }

        public function setChannel(string $channel): static
        {
            ++$this->setChannelCalled;

            return $this;
        }
    };

    $data = [];

    $licenseServerMock = Mockery::mock(Server::class);
    $licenseServerMock->shouldReceive('process')->atLeast()->once()->andReturn([]);

    $di = container();
    $di['logger'] = $loggerMock;
    $di['license_server'] = $licenseServerMock;
    $service->setDi($di);

    $result = $service->checkLicenseDetails($data);

    expect($result)->toBeArray();
    expect($setChannelCalled)->toBeGreaterThanOrEqual(1);
});

test('server process rejects expired license', function (): void {
    $server = new Server();

    $serviceLicense = createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class);

    $licenseRepoMock = Mockery::mock(Box\Mod\Servicelicense\Repository\ServiceLicenseRepository::class);
    $licenseRepoMock->shouldReceive('findOneByLicenseKey')
        ->once()
        ->with('KEY')
        ->andReturn($serviceLicense);

    $serviceMock = Mockery::mock(Service::class)->shouldIgnoreMissing();
    $serviceMock->shouldReceive('isLicenseActive')
        ->once()
        ->with($serviceLicense)
        ->andReturn(false);
    $serviceMock->shouldReceive('getServiceLicenseRepository')
        ->once()
        ->andReturn($licenseRepoMock);

    $requestMock = Mockery::mock(FOSSBilling\Request::class);
    $requestMock->shouldReceive('getClientIp')->once()->andReturn('127.0.0.1');

    $di = container();
    $di['request'] = $requestMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceMock);
    $server->setDi($di);

    $data = [
        'license' => 'KEY',
        'host' => 'example.com',
        'version' => '1.0',
        'path' => '/var/www',
    ];

    expect(fn (): array => $server->process($data))
        ->toThrow(LogicException::class, 'License is not active');
});
