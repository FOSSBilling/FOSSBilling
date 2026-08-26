<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Repository\OrderRepository;
use Box\Mod\Order\Service as OrderService;
use Box\Mod\Servicedomain\Api\Admin;
use Box\Mod\Servicedomain\Entity\ServiceDomain;
use Box\Mod\Servicedomain\Entity\Tld;
use Box\Mod\Servicedomain\Entity\TldRegistrar;
use Box\Mod\Servicedomain\Repository\TldRegistrarRepository;
use Box\Mod\Servicedomain\Service;
use Doctrine\ORM\EntityManagerInterface;
use FOSSBilling\Pagination\Options as PaginationOptions;
use FOSSBilling\Pagination\Service as PaginationService;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\setEntityId;

test('updates domain', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $model = new ServiceDomain();

    $adminApiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods());
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
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $model = new ServiceDomain();

    $adminApiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods());
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
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $model = new ServiceDomain();

    $adminApiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods());
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
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $model = new ServiceDomain();

    $adminApiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods());
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
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $model = new ServiceDomain();

    $adminApiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods());
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

test('synchronizes domain with registrar', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $model = new ServiceDomain();

    $adminApiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods());
    $adminApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('synchronizeDomain')
        ->atLeast()->once()
        ->with($model);

    $adminApiMock->setService($serviceMock);

    $data = [];
    $result = $adminApiMock->sync($data);

    expect($result)->toBeTrue();
});

test('throws exception when synchronizing domain without order_id', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $dispatcher = new FOSSBilling\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($adminApi, 'sync', []))
        ->toThrow(FOSSBilling\Exception\InformationException::class);
});

test('gets transfer code', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $model = new ServiceDomain();

    $adminApiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods());
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
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $model = new ServiceDomain();

    $adminApiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods());
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
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $model = new ServiceDomain();

    $adminApiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial()->shouldAllowMockingProtectedMethods());
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
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $query = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $paginatorMock = Mockery::mock(PaginationService::class);
    $paginatorMock->shouldReceive('paginateMappedQuery')
        ->atLeast()->once()
        ->with($query, Mockery::type(PaginationOptions::class), Mockery::type('callable'))
        ->andReturn(['list' => []]);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldGetSearchQuery')
        ->atLeast()->once()
        ->andReturn($query);

    $di = container();
    $di['pager'] = $paginatorMock;

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [];
    $result = $adminApi->tld_get_list($data);

    expect($result)->toBeArray();
});

test('gets tld', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(new Tld());
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
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
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
        ->toThrow(FOSSBilling\Exception\InformationException::class);
});

test('deletes tld', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $tldMock = new Tld();
    $tldMock->setTld('.com');

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('normalizeTld')
        ->once()
        ->with('.com')
        ->andReturn('.com');
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn($tldMock);
    $serviceMock->shouldReceive('tldRm')
        ->atLeast()->once()
        ->andReturn(true);

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchAllAssociative')
        ->once()
        ->andReturn([]);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')
        ->once()
        ->andReturn($connectionMock);

    $di = container();
    $di['em'] = $emMock;

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'tld' => '.com',
    ];
    $result = $adminApi->tld_delete($data);

    expect($result)->toBeTrue();
});

test('prevents deleting a tld used by a legacy uppercase domain row', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $tld = new Tld();
    $tld->setTld('.com');

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('normalizeTld')->once()->with('.COM.')->andReturn('.com');
    $serviceMock->shouldReceive('tldFindOneByTld')->once()->with('.com')->andReturn($tld);
    $serviceMock->shouldReceive('tldRm')->never();

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchAllAssociative')
        ->once()
        ->andReturn([['id' => 1]]);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')
        ->once()
        ->andReturn($connectionMock);

    $di = container();
    $di['em'] = $emMock;
    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    expect(fn () => $adminApi->tld_delete(['tld' => '.COM.']))
        ->toThrow(FOSSBilling\Exception\InformationException::class, 'TLD is used by 1 domains');
});

test('throws exception when deleting tld not found', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('normalizeTld')
        ->once()
        ->with('.com')
        ->andReturn('.com');
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
        ->toThrow(FOSSBilling\Exception\InformationException::class);
});

test('creates tld', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
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
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
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
        ->toThrow(FOSSBilling\Exception\InformationException::class);
});

test('updates tld', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn(new Tld());
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
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
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
        ->toThrow(FOSSBilling\Exception\BaseException::class);
});

test('gets registrar list', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $query = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $paginatorMock = Mockery::mock(PaginationService::class);
    $paginatorMock->shouldReceive('paginateMappedQuery')
        ->atLeast()->once()
        ->with($query, Mockery::type(PaginationOptions::class), Mockery::type('callable'))
        ->andReturn(['list' => []]);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarGetSearchQuery')
        ->atLeast()->once()
        ->andReturn($query);

    $di = container();
    $di['pager'] = $paginatorMock;

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [];
    $result = $adminApi->registrar_get_list($data);

    expect($result)->toBeArray();
});

test('gets registrar pairs', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarGetPairs')
        ->atLeast()->once()
        ->andReturn([]);

    $adminApi->setService($serviceMock);

    $result = $adminApi->registrar_get_pairs([]);

    expect($result)->toBeArray();
});

test('gets available registrars', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarGetAvailable')
        ->atLeast()->once()
        ->andReturn([]);

    $adminApi->setService($serviceMock);

    $result = $adminApi->registrar_get_available([]);

    expect($result)->toBeArray();
});

test('installs registrar', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
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
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
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
        ->toThrow(FOSSBilling\Exception\BaseException::class);
});

test('throws exception when deleting registrar without id', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $dispatcher = new FOSSBilling\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($adminApi, 'registrar_delete', []))
        ->toThrow(FOSSBilling\Exception\InformationException::class);
});

test('copies registrar', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $registrar = new TldRegistrar();
    setEntityId($registrar, 1);

    $trRepo = Mockery::mock(TldRegistrarRepository::class);
    $trRepo->shouldReceive('find')
        ->atLeast()->once()
        ->with(1)
        ->andReturn($registrar);
    $trRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->with(TldRegistrar::class)
        ->andReturn($trRepo);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarCopy')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['validator'] = new \FOSSBilling\Validation\Validator();
    $di['em'] = $emMock;

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $adminApi->registrar_copy($data);

    expect($result)->toBe(1);
});

test('throws exception when copying registrar without id', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $dispatcher = new FOSSBilling\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($adminApi, 'registrar_copy', []))
        ->toThrow(FOSSBilling\Exception\InformationException::class);
});

test('gets registrar', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $registrar = new TldRegistrar();
    setEntityId($registrar, 1);

    $trRepo = Mockery::mock(TldRegistrarRepository::class);
    $trRepo->shouldReceive('find')
        ->atLeast()->once()
        ->with(1)
        ->andReturn($registrar);
    $trRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->with(TldRegistrar::class)
        ->andReturn($trRepo);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarToApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['em'] = $emMock;
    $di['validator'] = new \FOSSBilling\Validation\Validator();

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $adminApi->registrar_get($data);

    expect($result)->toBeArray();
});

test('throws exception when getting registrar without id', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $registrar = new TldRegistrar();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarToApiArray')
        ->never();

    $di = container();
    $di['validator'] = new \FOSSBilling\Validation\Validator();

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [];

    $dispatcher = new FOSSBilling\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($adminApi, 'registrar_get', []))
        ->toThrow(FOSSBilling\Exception\InformationException::class);
});

test('batch syncs expiration dates', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('batchSyncExpirationDates')
        ->atLeast()->once()
        ->andReturn(true);

    $adminApi->setService($serviceMock);

    $result = $adminApi->batch_sync_expiration_dates([]);

    expect($result)->toBeTrue();
});

test('updates registrar', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $registrar = new TldRegistrar();
    setEntityId($registrar, 1);

    $trRepo = Mockery::mock(TldRegistrarRepository::class);
    $trRepo->shouldReceive('find')
        ->atLeast()->once()
        ->with(1)
        ->andReturn($registrar);
    $trRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->with(TldRegistrar::class)
        ->andReturn($trRepo);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarUpdate')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['em'] = $emMock;
    $di['validator'] = new \FOSSBilling\Validation\Validator();

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $adminApi->registrar_update($data);

    expect($result)->toBeTrue();
});

test('throws exception when updating registrar without id', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $registrar = new TldRegistrar();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('registrarUpdate')
        ->never();

    $di = container();
    $di['validator'] = new \FOSSBilling\Validation\Validator();

    $adminApi->setDi($di);
    $adminApi->setService($serviceMock);

    $data = [];

    $dispatcher = new FOSSBilling\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($adminApi, 'registrar_update', []))
        ->toThrow(FOSSBilling\Exception\InformationException::class);
});

test('gets service', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateDomain')
        ->atLeast()->once()
        ->andReturn(true);

    $adminApi->setService($serviceMock);

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(createEntity(Order::class));

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(new ServiceDomain());
    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class)->shouldIgnoreMissing();
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')
        ->atLeast()->once()
        ->with('servicedomain', 'manage_domains', Mockery::any(), Mockery::any());

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(fn (string $name = ''): Mockery\MockInterface => strtolower($name) === 'staff' ? $staffServiceMock : $orderServiceMock);
    $di['validator'] = new \FOSSBilling\Validation\Validator();

    $adminApi->setDi($di);

    $data = [
        'order_id' => 1,
    ];
    $result = $adminApi->update($data);

    expect($result)->toBeTrue();
});

test('throws exception when getting service without order_id', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateDomain')
        ->never();

    $adminApi->setService($serviceMock);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->never();
    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class)->shouldIgnoreMissing();
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')
        ->never();

    $di = container();
    $di['em']->shouldReceive('getConnection')->never();
    $di['mod_service'] = $di->protect(fn (string $name = ''): Mockery\MockInterface => strtolower($name) === 'staff' ? $staffServiceMock : $orderServiceMock);
    $di['validator'] = new \FOSSBilling\Validation\Validator();

    $adminApi->setDi($di);

    $data = [];

    $dispatcher = new FOSSBilling\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($adminApi, 'update', []))
        ->toThrow(FOSSBilling\Exception\InformationException::class);
});

test('throws exception when getting service for not activated order', function (): void {
    $adminApi = apiEndpoint(new Admin());
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateDomain')
        ->never();

    $adminApi->setService($serviceMock);

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(createEntity(Order::class));

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(null);
    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class)->shouldIgnoreMissing();
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')
        ->atLeast()->once()
        ->with('servicedomain', 'manage_domains', Mockery::any(), Mockery::any());

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(fn (string $name = ''): Mockery\MockInterface => strtolower($name) === 'staff' ? $staffServiceMock : $orderServiceMock);
    $di['validator'] = new \FOSSBilling\Validation\Validator();

    $adminApi->setDi($di);

    $data = [
        'order_id' => 1,
    ];

    expect(fn () => $adminApi->update($data))
        ->toThrow(FOSSBilling\Exception\BaseException::class);
});
