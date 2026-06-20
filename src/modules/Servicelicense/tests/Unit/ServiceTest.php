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
use Box\Mod\Servicelicense\Server;
use Box\Mod\Servicelicense\Service;

use function Tests\Helpers\container;

function serviceLicenseCreateProductEntity(string $config): Product
{
    $product = new Product();
    $product->setConfig($config);

    return $product;
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
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class)->shouldIgnoreMissing();
    $dbMock->shouldReceive('dispense')->atLeast()->once()->andReturn($serviceLicenseModel);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->action_create($clientOrderModel);
    expect($result)->toBeInstanceOf(Model_ServiceLicense::class);
});

test('action activate', function (): void {
    $service = new Service();
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->plugin = 'Simple';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceLicenseModel);

    $dbMock = Mockery::mock(Box_Database::class)->shouldIgnoreMissing();
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->action_activate($clientOrderModel);
    expect($result)->toBeTrue();
});

test('action activate license collision', function (): void {
    $service = new Service();
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->plugin = 'Simple';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn(['iterations' => 3]);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceLicenseModel);

    $dbMock = Mockery::mock(Box_Database::class)->shouldIgnoreMissing();
    $dbMock->shouldReceive('findOne')
        ->times(3)
        ->andReturn($serviceLicenseModel, $serviceLicenseModel, null);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->action_activate($clientOrderModel);
    expect($result)->toBeTrue();
});

test('action activate license collision max iterations exception', function (): void {
    $service = new Service();
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->plugin = 'Simple';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceLicenseModel);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldNotReceive('store');
    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn($serviceLicenseModel);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    expect(fn (): bool => $service->action_activate($clientOrderModel))
        ->toThrow(FOSSBilling\Exception::class);
});

test('action activate plugin not found', function (): void {
    $service = new Service();
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->plugin = 'TestPlugin';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceLicenseModel);

    $di = container();
    $di['logger'] = new Box_Log();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    expect(fn (): bool => $service->action_activate($clientOrderModel))
        ->toThrow(FOSSBilling\Exception::class, "License plugin {$serviceLicenseModel->plugin} was not found.");
});

test('action activate order activation exception', function (): void {
    $service = new Service();
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    expect(fn (): bool => $service->action_activate($clientOrderModel))
        ->toThrow(FOSSBilling\Exception::class, 'Could not activate order. Service was not created');
});

test('action delete', function (): void {
    $service = new Service();
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($serviceLicenseModel);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('trash')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $service->action_delete($clientOrderModel);
});

test('reset', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());

    $eventMock = Mockery::mock(Box_EventManager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();
    $di['events_manager'] = $eventMock;

    $service->setDi($di);
    $result = $service->reset($serviceLicenseModel);
    expect($result)->toBeTrue();
});

test('is license active', function (): void {
    $service = new Service();
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());
    $clientOrderModel->status = Model_ClientOrder::STATUS_ACTIVE;

    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getServiceOrder')->atLeast()->once()->andReturn($clientOrderModel);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $result = $service->isLicenseActive($serviceLicenseModel);
    expect($result)->toBeTrue();
});

test('is license not active', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getServiceOrder')->atLeast()->once()->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $result = $service->isLicenseActive($serviceLicenseModel);
    expect($result)->toBeFalse();
});

test('is valid ip', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->ips = '{}';
    $value = '1.1.1.1';

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidIp($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid ip test2', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->ips = '["2.2.2.2"]';
    $value = '1.1.1.1';

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidIp($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid ip test3', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->ips = '["2.2.2.2"]';
    $serviceLicenseModel->validate_ip = '3.3.3.3';
    $value = '1.1.1.1';

    $result = $service->isValidIp($serviceLicenseModel, $value);
    expect($result)->toBeFalse();
});

test('is valid version', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->versions = '{}';
    $value = '1.0';

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidVersion($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid version test2', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->versions = '["2.0"]';
    $value = '1.0';

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidVersion($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid version test3', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->versions = '["2.0"]';
    $serviceLicenseModel->validate_version = '3.3.3.3';
    $value = '1.0';

    $result = $service->isValidVersion($serviceLicenseModel, $value);
    expect($result)->toBeFalse();
});

test('is valid path', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->paths = '{}';
    $value = '/var';

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidPath($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid path test2', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->paths = '["/"]';
    $value = '/var';

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidPath($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid path test3', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->paths = '["/"]';
    $serviceLicenseModel->validate_path = '/user';
    $value = '/var';

    $result = $service->isValidPath($serviceLicenseModel, $value);
    expect($result)->toBeFalse();
});

test('is valid host', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->hosts = '{}';
    $value = 'site.com';

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidHost($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid host test2', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->hosts = '["fossbilling.org"]';
    $value = 'site.com';

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidHost($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('is valid host test3', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->hosts = '["fossbilling.org"]';
    $serviceLicenseModel->validate_host = 'example.com';
    $value = 'site.com';

    $result = $service->isValidHost($serviceLicenseModel, $value);
    expect($result)->toBeFalse();
});

test('get additional params', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());
    $serviceLicenseModel->plugin = 'Simple';

    $result = $service->getAdditionalParams($serviceLicenseModel);
    expect($result)->toBeArray();
});

test('get owner name', function (): void {
    $service = new Service();
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());
    $clientModel->first_name = 'John';
    $clientModel->last_name = 'Smith';

    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());

    $expected = $clientModel->first_name . ' ' . $clientModel->last_name;

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('load')->atLeast()->once()->andReturn($clientModel);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->getOwnerName($serviceLicenseModel);
    expect($result)->toBeString();
    expect($result)->toEqual($expected);
});

test('get expiration date', function (): void {
    $service = new Service();
    $expected = '2004-02-12 15:19:21';
    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());
    $clientOrderModel->expires_at = $expected;

    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getServiceOrder')->atLeast()->once()->andReturn($clientOrderModel);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->getExpirationDate($serviceLicenseModel);
    expect($result)->toBeString();
    expect($result)->toEqual($expected);
});

test('to api array', function (): void {
    $service = new Service();
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());

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

    $result = $service->toApiArray($serviceLicenseModel, false, new Model_Admin());
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
    $serviceLicenseModel = new Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

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
