<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

test('getDi', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('attachOrderConfig with empty product config', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->config = '{}';
    $data = [];

    $result = $service->attachOrderConfig($productModel, $data);
    expect($result)->toBeArray();
    expect($result)->toBe([]);
});

test('attachOrderConfig', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $productModel = new \Model_Product();
    $productModel->loadBean(new \Tests\Helpers\DummyBean());
    $productModel->config = '["hello", "world"]';
    $data = ['testing' => 'phase'];
    $expected = array_merge(json_decode($productModel->config ?? '', true), $data);

    $result = $service->attachOrderConfig($productModel, $data);
    expect($result)->toBeArray();
    expect($result)->toEqual($expected);
});

test('getLicensePlugins', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $result = $service->getLicensePlugins();
    expect($result)->toBeArray();
});

test('actionCreate', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()
        ->once()
        ->andReturn($serviceLicenseModel);
    $dbMock->shouldReceive('store')->atLeast()->once();

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getConfig')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->action_create($clientOrderModel);
    expect($result)->toBeInstanceOf(\Model_ServiceLicense::class);
});

test('actionActivate', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->plugin = 'Simple';

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getConfig')
        ->atLeast()
        ->once()
        ->andReturn([]);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn($serviceLicenseModel);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();
    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->action_activate($clientOrderModel);
    expect($result)->toBeTrue();
});

test('actionActivate with license collision', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->plugin = 'Simple';

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getConfig')
        ->atLeast()
        ->once()
        ->andReturn(['iterations' => 3]);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn($serviceLicenseModel);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();
    $dbMock->shouldReceive('findOne')
        ->times(3)
        ->andReturnUsing(function () use ($serviceLicenseModel) {
            static $callCount = 0;
            $callCount++;
            return $callCount <= 2 ? $serviceLicenseModel : null;
        });

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->action_activate($clientOrderModel);
    expect($result)->toBeTrue();
});

test('actionActivate with license collision max iterations exception', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->plugin = 'Simple';

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getConfig')
        ->atLeast()
        ->once()
        ->andReturn([]);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn($serviceLicenseModel);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->never();
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn($serviceLicenseModel);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    expect(fn () => $service->action_activate($clientOrderModel))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('actionActivate with plugin not found', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->plugin = 'TestPlugin';

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getConfig')
        ->atLeast()
        ->once()
        ->andReturn([]);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn($serviceLicenseModel);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    expect(fn () => $service->action_activate($clientOrderModel))
        ->toThrow(\FOSSBilling\Exception::class, "License plugin {$serviceLicenseModel->plugin} was not found.");
});

test('actionActivate with order activation exception', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getConfig')
        ->atLeast()
        ->once()
        ->andReturn([]);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    expect(fn () => $service->action_activate($clientOrderModel))
        ->toThrow(\FOSSBilling\Exception::class, 'Could not activate order. Service was not created');
});

test('actionDelete', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());

    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn($serviceLicenseModel);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $service->action_delete($clientOrderModel);
});

test('reset', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventMock;

    $service->setDi($di);
    $result = $service->reset($serviceLicenseModel);
    expect($result)->toBeTrue();
});

test('isLicenseActive', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientOrderModel->status = \Model_ClientOrder::STATUS_ACTIVE;

    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getServiceOrder')
        ->atLeast()
        ->once()
        ->andReturn($clientOrderModel);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $result = $service->isLicenseActive($serviceLicenseModel);
    expect($result)->toBeTrue();
});

test('isLicenseNotActive', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getServiceOrder')
        ->atLeast()
        ->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $result = $service->isLicenseActive($serviceLicenseModel);
    expect($result)->toBeFalse();
});

test('isValidIp', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->ips = '{}';
    $value = '1.1.1.1';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidIp($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('isValidIp test 2', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->ips = '["2.2.2.2"]';
    $value = '1.1.1.1';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidIp($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('isValidIp test 3', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->ips = '["2.2.2.2"]';
    $serviceLicenseModel->validate_ip = '3.3.3.3';
    $value = '1.1.1.1';

    $result = $service->isValidIp($serviceLicenseModel, $value);
    expect($result)->toBeFalse();
});

test('isValidVersion', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->versions = '{}';
    $value = '1.0';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidVersion($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('isValidVersion test 2', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->versions = '["2.0"]';
    $value = '1.0';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidVersion($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('isValidVersion test 3', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->versions = '["2.0"]';
    $serviceLicenseModel->validate_version = '3.3.3.3';
    $value = '1.0';

    $result = $service->isValidVersion($serviceLicenseModel, $value);
    expect($result)->toBeFalse();
});

test('isValidPath', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->paths = '{}';
    $value = '/var';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidPath($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('isValidPath test 2', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->paths = '["/"]';
    $value = '/var';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidPath($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('isValidPath test 3', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->paths = '["/"]';
    $serviceLicenseModel->validate_path = '/user';
    $value = '/var';

    $result = $service->isValidPath($serviceLicenseModel, $value);
    expect($result)->toBeFalse();
});

test('isValidHost', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->hosts = '{}';
    $value = 'site.com';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidHost($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('isValidHost test 2', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->hosts = '["fossbilling.org"]';
    $value = 'site.com';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->isValidHost($serviceLicenseModel, $value);
    expect($result)->toBeTrue();
});

test('isValidHost test 3', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->hosts = '["fossbilling.org"]';
    $serviceLicenseModel->validate_host = 'example.com';
    $value = 'site.com';

    $result = $service->isValidHost($serviceLicenseModel, $value);
    expect($result)->toBeFalse();
});

test('getAdditionalParams', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceLicenseModel->plugin = 'Simple';

    $result = $service->getAdditionalParams($serviceLicenseModel);
    expect($result)->toBeArray();
});

test('getOwnerName', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientModel->first_name = 'John';
    $clientModel->last_name = 'Smith';

    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());

    $expected = $clientModel->first_name . ' ' . $clientModel->last_name;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()
        ->once()
        ->andReturn($clientModel);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->getOwnerName($serviceLicenseModel);
    expect($result)->toBeString();
    expect($result)->toBe($expected);
});

test('getExpirationDate', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $expected = '2004-02-12 15:19:21';
    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientOrderModel->expires_at = $expected;

    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getServiceOrder')
        ->atLeast()
        ->once()
        ->andReturn($clientOrderModel);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->getExpirationDate($serviceLicenseModel);
    expect($result)->toBeString();
    expect($result)->toBe($expected);
});

test('toApiArray', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());

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

    $result = $service->toApiArray($serviceLicenseModel, false, new \Model_Admin());
    expect($result)->toBeArray();
    expect(count(array_diff(array_keys($expected), array_keys($result))) == 0)->toBeTrue('Missing array key values.');
});

test('update', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();
    $data = [
        'license_key' => '123456Licence',
        'validate_ip' => '1.1.1.1',
        'validate_host' => 'fossbilling.org',
        'validate_version' => '1.0',
        'validate_path' => '/usr',
        'ips' => '2.2.2.2\n',
        'pinged_at' => '',
        'plugin' => 'Simple',
    ];
    $serviceLicenseModel = new \Model_ServiceLicense();
    $serviceLicenseModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->update($serviceLicenseModel, $data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('checkLicenseDetails with format eq 2', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();

    $data = [
        'format' => 2,
    ];

    $licenseServerMock = Mockery::mock(\Box\Mod\Servicelicense\Server::class);
    $licenseServerMock->shouldReceive('process')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['license_server'] = $licenseServerMock;
    $service->setDi($di);

    $result = $service->checkLicenseDetails($data);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('error');
    expect($result)->toHaveKey('error_code');
});

test('checkLicenseDetails', function (): void {
    $service = new \Box\Mod\Servicelicense\Service();

    $data = [];

    $licenseServerMock = Mockery::mock(\Box\Mod\Servicelicense\Server::class);
    $licenseServerMock->shouldReceive('process')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['license_server'] = $licenseServerMock;
    $service->setDi($di);

    $result = $service->checkLicenseDetails($data);

    expect($result)->toBeArray();
});
