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
use Box\Mod\Servicedomain\Api\Admin;
use Box\Mod\Servicedomain\Service;
use FOSSBilling\Pagination;

use function Tests\Helpers\container;

test('updates domain', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $model = new Model_ServiceDomain();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $adminApiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $adminApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateDomain')
        ->atLeast()->once()
        ->andReturn(true);

    $adminApiMock->setService($serviceMock);

    $data = [];
    $result = $adminApiMock->update($data);

    expect($result)->toBeTrue();
});

test('updates nameservers', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $model = new Model_ServiceDomain();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $adminApiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $adminApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateNameservers')
        ->atLeast()->once()
        ->andReturn(true);

    $adminApiMock->setService($serviceMock);

    $data = [];
    $result = $adminApiMock->update_nameservers($data);

    expect($result)->toBeTrue();
});

test('updates contacts', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $model = new Model_ServiceDomain();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $adminApiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $adminApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateContacts')
        ->atLeast()->once()
        ->andReturn(true);

    $adminApiMock->setService($serviceMock);

    $data = [];
    $result = $adminApiMock->update_contacts($data);

    expect($result)->toBeTrue();
});

test('enables privacy protection', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $model = new Model_ServiceDomain();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $adminApiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $adminApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('enablePrivacyProtection')
        ->atLeast()->once()
        ->andReturn(true);

    $adminApiMock->setService($serviceMock);

    $data = [];
    $result = $adminApiMock->enable_privacy_protection($data);

    expect($result)->toBeTrue();
});

test('disables privacy protection', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $model = new Model_ServiceDomain();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $adminApiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $adminApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('disablePrivacyProtection')
        ->atLeast()->once()
        ->andReturn(true);

    $adminApiMock->setService($serviceMock);

    $data = [];
    $result = $adminApiMock->disable_privacy_protection($data);

    expect($result)->toBeTrue();
});

test('gets transfer code', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $model = new Model_ServiceDomain();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $adminApiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $adminApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getTransferCode')
        ->atLeast()->once()
        ->andReturn(true);

    $adminApiMock->setService($serviceMock);

    $data = [];
    $result = $adminApiMock->get_transfer_code($data);

    expect($result)->toBeTrue();
});

test('locks domain', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $model = new Model_ServiceDomain();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $adminApiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $adminApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('lock')
        ->atLeast()->once()
        ->andReturn(true);

    $adminApiMock->setService($serviceMock);

    $data = [];
    $result = $adminApiMock->lock($data);

    expect($result)->toBeTrue();
});

test('unlocks domain', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $model = new Model_ServiceDomain();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $adminApiMock = Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $adminApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('unlock')
        ->atLeast()->once()
        ->andReturn(true);

    $adminApiMock->setService($serviceMock);

    $data = [];
    $result = $adminApiMock->unlock($data);

    expect($result)->toBeTrue();
});

test('gets tld list', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $paginatorMock = Mockery::mock(Pagination::class);
    $paginatorMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(100);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn(['list' => []]);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldGetSearchQuery')
        ->atLeast()->once()
        ->andReturn(['query', []]);

    $di = container();
    $di['pager'] = $paginatorMock;

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [];
    $result = $adminApi->tld_get_list($data);

    expect($result)->toBeArray();
});

test('gets tld', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(new Model_Tld());
    $serviceMock->shouldReceive('tldToApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
    ];
    $result = $adminApi->tld_get($data);

    expect($result)->toBeArray();
});

test('throws exception when getting tld not found', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(null);
    $serviceMock->shouldReceive('tldToApiArray')
        ->never();

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
    ];

    expect(fn () => $adminApi->tld_get($data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('deletes tld', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $tldMock = new Model_Tld();
    $tldMock->loadBean(new Tests\Helpers\DummyBean());
    $tldMock->tld = '.com';

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn($tldMock);
    $serviceMock->shouldReceive('tldRm')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->once()
        ->with('ServiceDomain', 'tld = :tld', [':tld' => $tldMock->tld])
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
    ];
    $result = $adminApi->tld_delete($data);

    expect($result)->toBeTrue();
});

test('throws exception when deleting tld not found', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(null);
    $serviceMock->shouldReceive('tldRm')
        ->never();

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
    ];

    expect(fn () => $adminApi->tld_delete($data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('creates tld', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldAlreadyRegistered')
        ->atLeast()->once()
        ->andReturn(false);
    $serviceMock->shouldReceive('tldCreate')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
        'tld_registrar_id' => 1,
        'price_registration' => 1,
        'price_renew' => 1,
        'price_transfer' => 1,
    ];

    $result = $adminApi->tld_create($data);
    expect($result)->toBeInt();
});

test('throws exception when creating already registered tld', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldAlreadyRegistered')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
    ];

    expect(fn () => $adminApi->tld_create($data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('updates tld', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(new Model_Tld());
    $serviceMock->shouldReceive('tldUpdate')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
    ];
    $result = $adminApi->tld_update($data);

    expect($result)->toBeTrue();
});

test('throws exception when updating tld not found', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(null);
    $serviceMock->shouldReceive('tldUpdate')
        ->never();

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
    ];

    expect(fn () => $adminApi->tld_update($data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('gets registrar list', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $paginatorMock = Mockery::mock(Pagination::class);
    $paginatorMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(100);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn(['list' => []]);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarGetSearchQuery')
        ->atLeast()->once()
        ->andReturn(['query', []]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['db'] = $dbMock;

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [];
    $result = $adminApi->registrar_get_list($data);

    expect($result)->toBeArray();
});

test('gets registrar pairs', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarGetPairs')
        ->atLeast()->once()
        ->andReturn([]);

    $adminApi->setService($serviceMock);

    $result = $adminApi->registrar_get_pairs([]);

    expect($result)->toBeArray();
});

test('gets available registrars', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarGetAvailable')
        ->atLeast()->once()
        ->andReturn([]);

    $adminApi->setService($serviceMock);

    $result = $adminApi->registrar_get_available([]);

    expect($result)->toBeArray();
});

test('installs registrar', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $registrars = [
        'ResellerClub', 'Custom',
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarGetAvailable')
        ->atLeast()->once()
        ->andReturn($registrars);
    $serviceMock->shouldReceive('registrarCreate')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'code' => 'ResellerClub',
    ];
    $result = $adminApi->registrar_install($data);

    expect($result)->toBeTrue();
});

test('throws exception when installing unavailable registrar', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $registrars = [
        'Custom',
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarGetAvailable')
        ->atLeast()->once()
        ->andReturn($registrars);
    $serviceMock->shouldReceive('registrarCreate')
        ->never();

    $di = container();
    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'code' => 'ResellerClub',
    ];

    expect(fn () => $adminApi->registrar_install($data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('throws exception when deleting registrar without id', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $apiHandler = new Api_Handler(new Model_Admin());
    $reflection = new ReflectionClass($apiHandler);
    $method = $reflection->getMethod('validateRequiredParams');

    expect(fn () => $method->invokeArgs($apiHandler, [$adminApi, 'registrar_delete', []]))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('copies registrar', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $registrar = new Model_TldRegistrar();
    $registrar->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($registrar);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarCopy')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['validator'] = new FOSSBilling\Validate();
    $di['db'] = $dbMock;

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $adminApi->registrar_copy($data);

    expect($result)->toBeTrue();
});

test('throws exception when copying registrar without id', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $apiHandler = new Api_Handler(new Model_Admin());
    $reflection = new ReflectionClass($apiHandler);
    $method = $reflection->getMethod('validateRequiredParams');

    expect(fn () => $method->invokeArgs($apiHandler, [$adminApi, 'registrar_copy', []]))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('gets registrar', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $registrar = new Model_TldRegistrar();
    $registrar->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($registrar);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarToApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $adminApi->registrar_get($data);

    expect($result)->toBeArray();
});

test('throws exception when getting registrar without id', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $registrar = new Model_TldRegistrar();
    $registrar->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->never();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarToApiArray')
        ->never();

    $di = container();
    $di['db'] = $dbMock;
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [];

    $apiHandler = new Api_Handler(new Model_Admin());
    $reflection = new ReflectionClass($apiHandler);
    $method = $reflection->getMethod('validateRequiredParams');

    expect(fn () => $method->invokeArgs($apiHandler, [$adminApi, 'registrar_get', []]))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('batch syncs expiration dates', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('batchSyncExpirationDates')
        ->atLeast()->once()
        ->andReturn(true);

    $adminApi->setService($serviceMock);

    $result = $adminApi->batch_sync_expiration_dates([]);

    expect($result)->toBeTrue();
});

test('updates registrar', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $registrar = new Model_TldRegistrar();
    $registrar->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($registrar);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarUpdate')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $adminApi->registrar_update($data);

    expect($result)->toBeTrue();
});

test('throws exception when updating registrar without id', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $registrar = new Model_TldRegistrar();
    $registrar->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->never();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarUpdate')
        ->never();

    $di = container();
    $di['db'] = $dbMock;
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [];

    $apiHandler = new Api_Handler(new Model_Admin());
    $reflection = new ReflectionClass($apiHandler);
    $method = $reflection->getMethod('validateRequiredParams');

    expect(fn () => $method->invokeArgs($apiHandler, [$adminApi, 'registrar_update', []]))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('gets service', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateDomain')
        ->atLeast()->once()
        ->andReturn(true);

    $adminApi->setService($serviceMock);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn(new Model_ClientOrder());

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(new Model_ServiceDomain());

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn () => $orderServiceMock);
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi->setDi($di);

    $data = [
        'order_id' => 1,
    ];
    $result = $adminApi->update($data);

    expect($result)->toBeTrue();
});

test('throws exception when getting service without order_id', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateDomain')
        ->never();

    $adminApi->setService($serviceMock);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->never();

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->never();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn () => $orderServiceMock);
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi->setDi($di);

    $data = [];

    $apiHandler = new Api_Handler(new Model_Admin());
    $reflection = new ReflectionClass($apiHandler);
    $method = $reflection->getMethod('validateRequiredParams');

    expect(fn () => $method->invokeArgs($apiHandler, [$adminApi, 'update', []]))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('throws exception when getting service for not activated order', function (): void {
    $adminApi = new Admin();
    $api = new Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateDomain')
        ->never();

    $adminApi->setService($serviceMock);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn(new Model_ClientOrder());

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn () => $orderServiceMock);
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi->setDi($di);

    $data = [
        'order_id' => 1,
    ];

    expect(fn () => $adminApi->update($data))
        ->toThrow(FOSSBilling\Exception::class);
});
