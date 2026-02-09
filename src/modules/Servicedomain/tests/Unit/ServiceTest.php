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
use Box\Mod\Servicedomain\Service;
use Box\Mod\Order\Service as OrderService;
use Box\Mod\System\Service as SystemService;

beforeEach(function () {
    $this->service = new Service();
});

afterEach(function () {
    Mockery::close();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

test('gets cart product title', function (array $data, string $expected) {
    $product = new \Model_CartProduct();
    $product->loadBean(new \Tests\Helpers\DummyBean());
    $product->title = 'Example.com Registration';

    $result = $this->service->getCartProductTitle($product, $data);

    expect($result)->toBe($expected);
})->with([
    [
        [
            'action' => 'register',
            'register_tld' => '.com',
            'register_sld' => 'example',
        ],
        'Domain example.com registration',
    ],
    [
        [
            'action' => 'transfer',
            'transfer_tld' => '.com',
            'transfer_sld' => 'example',
        ],
        'Domain example.com transfer',
    ],
    [
        [],
        'Example.com Registration',
    ],
]);

test('throws exception for invalid order data action', function () {
    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')
        ->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $this->service->setDi($di);

    $data = [
        'action' => 'NonExistingAction',
    ];

    expect(fn () => $this->service->validateOrderData($data))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('throws exception for transfer order data with invalid tld', function (array $data, array $isSldValidArr, array $tldFindOneByTldArr, array $canBeTransferred) {
    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')
        ->{$isSldValidArr['called']}()
        ->andReturn($isSldValidArr['returns']);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')
        ->zeroOrMoreTimes();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->{$tldFindOneByTldArr['called']}()
        ->andReturn($tldFindOneByTldArr['returns']);
    $serviceMock->shouldReceive('canBeTransferred')
        ->{$canBeTransferred['called']}()
        ->andReturn($canBeTransferred['returns']);

    $di = container();
    $di['validator'] = $validatorMock;
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->validateOrderData($data))
        ->toThrow(\FOSSBilling\Exception::class);
})->with(function () {
    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());
    $tldModel->tld = '.com';

    return [
        [
            [
                'action' => 'transfer',
                'transfer_sld' => 'example',
                'transfer_tld' => '.com',
            ],
            ['called' => 'atLeast', 'returns' => true],
            ['called' => 'atLeast', 'returns' => null],
            ['called' => 'never', 'returns' => true],
        ],
        [
            [
                'action' => 'transfer',
                'transfer_sld' => 'example',
                'transfer_tld' => '.com',
            ],
            ['called' => 'atLeast', 'returns' => true],
            ['called' => 'atLeast', 'returns' => $tldModel],
            ['called' => 'atLeast', 'returns' => false],
        ],
    ];
});

test('throws exception for register order data with invalid tld', function (array $data, array $isSldValidArr, array $tldFindOneByTldArr, array $isDomainAvailable) {
    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')
        ->{$isSldValidArr['called']}()
        ->andReturn($isSldValidArr['returns']);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')
        ->zeroOrMoreTimes();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->{$tldFindOneByTldArr['called']}()
        ->andReturn($tldFindOneByTldArr['returns']);
    $serviceMock->shouldReceive('isDomainAvailable')
        ->{$isDomainAvailable['called']}()
        ->andReturn($isDomainAvailable['returns']);

    $di = container();
    $di['validator'] = $validatorMock;
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->validateOrderData($data))
        ->toThrow(\FOSSBilling\Exception::class);
})->with(function () {
    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());
    $tldModel->tld = '.com';
    $tldModel->min_years = 2;

    return [
        [
            [
                'action' => 'register',
                'register_sld' => 'example',
                'register_years' => 2,
                'register_tld' => '.com',
            ],
            ['called' => 'atLeast', 'returns' => true],
            ['called' => 'atLeast', 'returns' => null],
            ['called' => 'never', 'returns' => true],
        ],
        [
            [
                'action' => 'register',
                'register_sld' => 'example',
                'register_years' => $tldModel->min_years - 1,
                'register_tld' => '.com',
            ],
            ['called' => 'atLeast', 'returns' => true],
            ['called' => 'atLeast', 'returns' => $tldModel],
            ['called' => 'never', 'returns' => true],
        ],
        [
            [
                'action' => 'register',
                'register_sld' => 'example',
                'register_tld' => '.com',
                'register_years' => 2,
            ],
            ['called' => 'atLeast', 'returns' => true],
            ['called' => 'atLeast', 'returns' => $tldModel],
            ['called' => 'atLeast', 'returns' => false],
        ],
    ];
});

test('creates action', function () {
    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());
    $tldModel->tld_registrar_id = 1;

    $data = [
        'action' => 'register',
        'register_sld' => 'example',
        'register_tld' => '.com',
        'register_years' => 2,
    ];

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn($data);

    $nameservers = [
        'nameserver_1' => 'ns1.example.com',
        'nameserver_2' => 'ns2.example.com',
        'nameserver_3' => 'ns3.example.com',
        'nameserver_4' => 'ns4.example.com',
    ];
    $systemServiceMock = Mockery::mock(SystemService::class);
    $systemServiceMock->shouldReceive('getNameservers')
        ->atLeast()->once()
        ->andReturn($nameservers);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('tldFindOneByTld')
        ->atLeast()->once()
        ->andReturn($tldModel);
    $serviceMock->shouldReceive('validateOrderData');

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->first_name = 'first_name';
    $client->last_name = 'last_name';
    $client->email = 'email';
    $client->company = 'company';
    $client->address_1 = 'address_1';
    $client->address_2 = 'address_2';
    $client->country = 'country';
    $client->city = 'city';
    $client->state = 'state';
    $client->postcode = 'postcode';
    $client->phone_cc = 'phone_cc';
    $client->phone = 'phone';

    $serviceDomainModel = new \Model_ServiceDomain();
    $serviceDomainModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($client);
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($serviceDomainModel);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock, $systemServiceMock) {
        if ($name == 'order') {
            return $orderServiceMock;
        }
        return $systemServiceMock;
    });
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;

    $result = $serviceMock->action_create($order);
    expect($result)->toBeInstanceOf(\Model_ServiceDomain::class);
});

test('throws exception when creating action with missing nameservers', function () {
    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());
    $tldModel->tld_registrar_id = 1;

    $data = [
        'action' => 'register',
        'register_sld' => 'example',
        'register_tld' => '.com',
        'register_years' => 2,
    ];

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn($data);

    $systemServiceMock = Mockery::mock(SystemService::class);
    $systemServiceMock->shouldReceive('getNameservers')
        ->atLeast()->once()
        ->andReturn([]);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('validateOrderData')
        ->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock, $systemServiceMock) {
        if ($name == 'order') {
            return $orderServiceMock;
        }
        return $systemServiceMock;
    });
    $serviceMock->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;

    expect(fn () => $serviceMock->action_create($order))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('activates action', function (string $action, string $registerDomainCalled, string $transferDomainCalled) {
    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());
    $tldModel->tld_registrar_id = 1;

    $domainModel = new \Model_ServiceDomain();
    $domainModel->loadBean(new \Tests\Helpers\DummyBean());
    $domainModel->tld_registrar_id = 1;
    $domainModel->action = $action;

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn($domainModel);

    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('registerDomain')
        ->{$registerDomainCalled}();
    $registrarAdapterMock->shouldReceive('transferDomain')
        ->{$transferDomainCalled}();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new \Registrar_Domain(), $registrarAdapterMock]);
    $serviceMock->shouldReceive('syncWhois')
        ->atLeast()->once()
        ->andReturn(null);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $di['db'] = $dbMock;
    $serviceMock->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $result = $serviceMock->action_activate($order);
    expect($result)->toBeInstanceOf(\Model_ServiceDomain::class);
})->with([
    ['register', 'atLeast', 'never'],
    ['transfer', 'never', 'atLeast'],
]);

test('throws exception when activating without order service', function () {
    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $this->service->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;

    expect(fn () => $this->service->action_activate($order))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('renews action', function () {
    $domainModel = new \Model_ServiceDomain();
    $domainModel->loadBean(new \Tests\Helpers\DummyBean());
    $domainModel->tld_registrar_id = 1;
    $domainModel->action = 'register';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn($domainModel);

    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('renewDomain')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new \Registrar_Domain(), $registrarAdapterMock]);
    $serviceMock->shouldReceive('syncWhois')
        ->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $serviceMock->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $result = $serviceMock->action_renew($order);

    expect($result)->toBeTrue();
});

test('throws exception when renewing without order service', function () {
    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $this->service->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->id = 1;
    $order->client_id = 1;

    expect(fn () => $this->service->action_renew($order))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('suspends action', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $result = $this->service->action_suspend($order);
    expect($result)->toBeTrue();
});

test('unsuspends action', function () {
    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $result = $this->service->action_unsuspend($order);
    expect($result)->toBeTrue();
});

test('cancels action', function () {
    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());
    $tldModel->tld_registrar_id = 1;

    $domainModel = new \Model_ServiceDomain();
    $domainModel->loadBean(new \Tests\Helpers\DummyBean());
    $domainModel->tld_registrar_id = 1;
    $domainModel->action = 'register';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn($domainModel);

    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('deleteDomain')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new \Registrar_Domain(), $registrarAdapterMock]);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $serviceMock->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $result = $serviceMock->action_cancel($order);

    expect($result)->toBeTrue();
});

test('throws exception when canceling without order service', function () {
    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $this->service->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->id = 1;
    $order->client_id = 1;

    expect(fn () => $this->service->action_cancel($order))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('uncancels action', function () {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('action_activate')
        ->atLeast()->once()
        ->andReturn(null);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->client_id = 1;
    $result = $serviceMock->action_uncancel($order);

    expect($result)->toBeTrue();
});

test('deletes action', function () {
    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());
    $tldModel->tld_registrar_id = 1;

    $domainModel = new \Model_ServiceDomain();
    $domainModel->loadBean(new \Tests\Helpers\DummyBean());
    $domainModel->tld_registrar_id = 1;
    $domainModel->action = 'register';

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn($domainModel);

    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('deleteDomain')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new \Registrar_Domain(), $registrarAdapterMock]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $di['db'] = $dbMock;
    $serviceMock->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
    $order->status = \Model_ClientOrder::STATUS_ACTIVE;
    $result = $serviceMock->action_delete($order);

    expect($result)->toBeNull();
});

test('updates nameservers', function () {
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('modifyNs')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new \Registrar_Domain(), $registrarAdapterMock]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $data = [
        'ns1' => 'ns1.example.com',
        'ns2' => 'ns2.example.com',
        'ns3' => 'ns3.example.com',
        'ns4' => 'ns4.example.com',
    ];

    $serviceDomainModel = new \Model_ServiceDomain();
    $serviceDomainModel->loadBean(new \Tests\Helpers\DummyBean());
    $result = $serviceMock->updateNameservers($serviceDomainModel, $data);

    expect($result)->toBeTrue();
});

test('throws exception when updating nameservers with missing ns1 or ns2', function (array $data) {
    $serviceDomainModel = new \Model_ServiceDomain();
    $serviceDomainModel->loadBean(new \Tests\Helpers\DummyBean());

    expect(fn () => $this->service->updateNameservers($serviceDomainModel, $data))
        ->toThrow(\FOSSBilling\Exception::class);
})->with([
    [['ns2' => 'ns2.example.com']],
    [['ns1' => 'ns1.example.com']],
]);

test('updates contacts', function () {
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('modifyContact')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new \Registrar_Domain(), $registrarAdapterMock]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')
        ->zeroOrMoreTimes();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['validator'] = $validatorMock;
    $serviceMock->setDi($di);

    $data = [
        'contact' => [
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'email' => 'email',
            'company' => 'company',
            'address1' => 'address1',
            'address2' => 'address2',
            'country' => 'country',
            'city' => 'city',
            'state' => 'state',
            'postcode' => 'postcode',
            'phone_cc' => 'phone_cc',
            'phone' => 'phone',
        ],
    ];
    $serviceDomainModel = new \Model_ServiceDomain();
    $serviceDomainModel->loadBean(new \Tests\Helpers\DummyBean());
    $result = $serviceMock->updateContacts($serviceDomainModel, $data);

    expect($result)->toBeTrue();
});

test('gets transfer code', function () {
    $epp = 'EPPCODE';

    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('getEpp')
        ->atLeast()->once()
        ->andReturn($epp);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new \Registrar_Domain(), $registrarAdapterMock]);

    $serviceDomainModel = new \Model_ServiceDomain();
    $serviceDomainModel->loadBean(new \Tests\Helpers\DummyBean());
    $result = $serviceMock->getTransferCode($serviceDomainModel);

    expect($result)->toBeString();
    expect($result)->toBe($epp);
});

test('locks domain', function () {
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('lock')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new \Registrar_Domain(), $registrarAdapterMock]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $serviceDomainModel = new \Model_ServiceDomain();
    $serviceDomainModel->loadBean(new \Tests\Helpers\DummyBean());
    $result = $serviceMock->lock($serviceDomainModel);

    expect($result)->toBeTrue();
});

test('unlocks domain', function () {
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('unlock')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new \Registrar_Domain(), $registrarAdapterMock]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $serviceDomainModel = new \Model_ServiceDomain();
    $serviceDomainModel->loadBean(new \Tests\Helpers\DummyBean());
    $result = $serviceMock->unlock($serviceDomainModel);

    expect($result)->toBeTrue();
});

test('enables privacy protection', function () {
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('enablePrivacyProtection')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new \Registrar_Domain(), $registrarAdapterMock]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $serviceDomainModel = new \Model_ServiceDomain();
    $serviceDomainModel->loadBean(new \Tests\Helpers\DummyBean());
    $result = $serviceMock->enablePrivacyProtection($serviceDomainModel);

    expect($result)->toBeTrue();
});

test('disables privacy protection', function () {
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('disablePrivacyProtection')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new \Registrar_Domain(), $registrarAdapterMock]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $serviceDomainModel = new \Model_ServiceDomain();
    $serviceDomainModel->loadBean(new \Tests\Helpers\DummyBean());
    $result = $serviceMock->disablePrivacyProtection($serviceDomainModel);

    expect($result)->toBeTrue();
});

test('checks if domain can be transferred', function () {
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('isDomaincanBeTransferred')
        ->atLeast()->once()
        ->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('registrarGetRegistrarAdapter')
        ->atLeast()->once()
        ->andReturn($registrarAdapterMock);

    $tldRegistrar = new \Model_TldRegistrar();
    $tldRegistrar->loadBean(new \Tests\Helpers\DummyBean());
    $tldRegistrar->tld_registrar_id = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($tldRegistrar);

    $di = container();
    $di['db'] = $dbMock;
    $serviceMock->setDi($di);

    $tld = new \Model_Tld();
    $tld->loadBean(new \Tests\Helpers\DummyBean());
    $tld->allow_transfer = true;
    $tld->tld = '.com';
    $tld->tld_registrar_id = 1;

    $result = $serviceMock->canBeTransferred($tld, 'example');

    expect($result)->toBeTrue();
});

test('throws exception when checking transfer with empty sld', function () {
    expect(fn () => $this->service->canBeTransferred(new \Model_Tld(), ''))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('throws exception when checking transfer not allowed', function () {
    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());
    $tldModel->allow_transfer = false;

    expect(fn () => $this->service->canBeTransferred($tldModel, 'example'))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('checks if domain is available', function () {
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('isDomainAvailable')
        ->atLeast()->once()
        ->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('registrarGetRegistrarAdapter')
        ->atLeast()->once()
        ->andReturn($registrarAdapterMock);

    $tldRegistrar = new \Model_TldRegistrar();
    $tldRegistrar->loadBean(new \Tests\Helpers\DummyBean());
    $tldRegistrar->tld_registrar_id = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($tldRegistrar);

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;
    $di['validator'] = $validatorMock;
    $serviceMock->setDi($di);

    $tld = new \Model_Tld();
    $tld->loadBean(new \Tests\Helpers\DummyBean());
    $tld->allow_register = true;
    $tld->tld = '.com';
    $tld->tld_registrar_id = 1;

    $result = $serviceMock->isDomainAvailable($tld, 'example');

    expect($result)->toBeTrue();
});

test('throws exception when checking availability with empty sld', function () {
    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());

    expect(fn () => $this->service->isDomainAvailable($tldModel, ''))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('throws exception when checking availability with invalid sld', function () {
    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')
        ->atLeast()->once()
        ->andReturn(false);

    $di = container();
    $di['validator'] = $validatorMock;
    $this->service->setDi($di);

    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());

    expect(fn () => $this->service->isDomainAvailable($tldModel, 'example'))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('throws exception when checking availability not allowed to register', function () {
    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['validator'] = $validatorMock;
    $this->service->setDi($di);

    $model = new \Model_Tld();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->allow_register = false;

    expect(fn () => $this->service->isDomainAvailable($model, 'example'))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('syncs expiration date', function () {
    $model = new \Model_ServiceDomain();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $result = $this->service->syncExpirationDate($model);

    expect($result)->toBeNull();
});

test('converts to api array', function (?\Model_Admin $identity, string $dbLoadCalled) {
    $model = new \Model_ServiceDomain();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $model->sld = 'sld';
    $model->tld = 'tld';
    $model->ns1 = 'ns1.example.com';
    $model->ns2 = 'ns2.example.com';
    $model->ns3 = 'ns3.example.com';
    $model->ns4 = 'ns4.example.com';
    $model->period = 'period';
    $model->privacy = 'privacy';
    $model->locked = 'locked';
    $model->registered_at = date('Y-m-d H:i:s');
    $model->expires_at = date('Y-m-d H:i:s');

    $model->contact_first_name = 'first_name';
    $model->contact_last_name = 'last_name';
    $model->contact_email = 'email';
    $model->contact_company = 'company';
    $model->contact_address1 = 'address1';
    $model->contact_address2 = 'address2';
    $model->contact_country = 'country';
    $model->contact_city = 'city';
    $model->contact_state = 'state';
    $model->contact_postcode = 'postcode';
    $model->contact_phone_cc = 'phone_cc';
    $model->contact_phone = 'phone';
    $model->transfer_code = 'EPPCODE';
    $model->tld_registrar_id = 1;

    $tldRegistrar = new \Model_TldRegistrar();
    $tldRegistrar->loadBean(new \Tests\Helpers\DummyBean());
    $tldRegistrar->name = 'ResellerClub';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->{$dbLoadCalled}()
        ->andReturn($tldRegistrar);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->toApiArray($model, true, $identity);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('domain');
    expect($result)->toHaveKey('sld');
    expect($result)->toHaveKey('tld');
    expect($result)->toHaveKey('ns1');
    expect($result)->toHaveKey('ns2');
    expect($result)->toHaveKey('ns3');
    expect($result)->toHaveKey('ns4');
    expect($result)->toHaveKey('period');
    expect($result)->toHaveKey('privacy');
    expect($result)->toHaveKey('locked');
    expect($result)->toHaveKey('registered_at');
    expect($result)->toHaveKey('expires_at');
    expect($result)->toHaveKey('contact');

    $contact = $result['contact'];
    expect($contact)->toBeArray();
    expect($contact)->toHaveKey('first_name');
    expect($contact)->toHaveKey('last_name');
    expect($contact)->toHaveKey('email');
    expect($contact)->toHaveKey('company');
    expect($contact)->toHaveKey('address1');
    expect($contact)->toHaveKey('address2');
    expect($contact)->toHaveKey('country');
    expect($contact)->toHaveKey('city');
    expect($contact)->toHaveKey('state');
    expect($contact)->toHaveKey('postcode');
    expect($contact)->toHaveKey('phone_cc');
    expect($contact)->toHaveKey('phone');

    expect($result['domain'])->toBe($model->sld . $model->tld);
    expect($result['sld'])->toBe($model->sld);
    expect($result['tld'])->toBe($model->tld);
    expect($result['ns1'])->toBe($model->ns1);
    expect($result['ns2'])->toBe($model->ns2);
    expect($result['ns3'])->toBe($model->ns3);
    expect($result['ns4'])->toBe($model->ns4);
    expect($result['period'])->toBe($model->period);
    expect($result['privacy'])->toBe($model->privacy);
    expect($result['locked'])->toBe($model->locked);
    expect($result['registered_at'])->toBe($model->registered_at);
    expect($result['expires_at'])->toBe($model->expires_at);

    expect($contact['first_name'])->toBe($model->contact_first_name);
    expect($contact['last_name'])->toBe($model->contact_last_name);
    expect($contact['email'])->toBe($model->contact_email);
    expect($contact['company'])->toBe($model->contact_company);
    expect($contact['address1'])->toBe($model->contact_address1);
    expect($contact['address2'])->toBe($model->contact_address2);
    expect($contact['country'])->toBe($model->contact_country);
    expect($contact['city'])->toBe($model->contact_city);
    expect($contact['state'])->toBe($model->contact_state);
    expect($contact['postcode'])->toBe($model->contact_postcode);
    expect($contact['phone_cc'])->toBe($model->contact_phone_cc);
    expect($contact['phone'])->toBe($model->contact_phone);

    if ($identity instanceof \Model_Admin) {
        expect($result)->toHaveKey('transfer_code');
        expect($result)->toHaveKey('registrar');
        expect($result['transfer_code'])->toBe($model->transfer_code);
        expect($result['registrar'])->toBe($tldRegistrar->name);
    }
})->with([
    [function () {
        $model = new \Model_Admin();
        $model->loadBean(new \Tests\Helpers\DummyBean());
        return $model;
    }, 'atLeast'],
    [null, 'never'],
]);

test('handles on before admin cron run event', function () {
    $di = container();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('batchSyncExpirationDates')
        ->atLeast()->once()
        ->andReturn(true);
    $di['mod_service'] = $di->protect(fn ($serviceName): \Mockery\MockInterface => $serviceMock);

    $boxEventMock = Mockery::mock('\Box_Event');
    $boxEventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $result = $this->service->onBeforeAdminCronRun($boxEventMock);

    expect($result)->toBeTrue();
});

test('batch syncs expiration dates', function () {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('syncExpirationDate')
        ->atLeast()->once();

    $systemServiceMock = Mockery::mock(SystemService::class);
    $systemServiceMock->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn(null);
    $systemServiceMock->shouldReceive('setParamValue')
        ->atLeast()->once();

    $domains = [
        'domain1.com',
        'domain2.com',
        'domain3.com',
        'domain4.com',
    ];
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($domains);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $systemServiceMock);
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $result = $serviceMock->batchSyncExpirationDates();

    expect($result)->toBeTrue();
});

test('returns false when batch sync already run today', function () {
    $systemServiceMock = Mockery::mock(SystemService::class);
    $systemServiceMock->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn(date('Y-m-d H:i:s'));
    $systemServiceMock->shouldReceive('setParamValue')
        ->never();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->never()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $systemServiceMock);
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->batchSyncExpirationDates();

    expect($result)->toBeFalse();
});

test('gets tld search query', function (array $data, string $expectedQuery, array $expectedBindings) {
    $di = container();

    $this->service->setDi($di);
    [$query, $bindings] = $this->service->tldGetSearchQuery($data);

    expect($query)->toBe($expectedQuery);
    expect($bindings)->toBeArray();
    expect($bindings)->toBe($expectedBindings);
})->with([
    [
        [],
        'SELECT * FROM tld ORDER BY id ASC',
        [],
    ],
    [
        ['hide_inactive' => true],
        'SELECT * FROM tld WHERE active = 1 ORDER BY id ASC',
        [],
    ],
    [
        ['allow_register' => true],
        'SELECT * FROM tld WHERE allow_register = 1 ORDER BY id ASC',
        [],
    ],
    [
        ['allow_transfer' => true],
        'SELECT * FROM tld WHERE allow_transfer = 1 ORDER BY id ASC',
        [],
    ],
    [
        ['hide_inactive' => true, 'allow_register' => true, 'allow_transfer' => true],
        'SELECT * FROM tld WHERE active = 1 AND allow_register = 1 AND allow_transfer = 1 ORDER BY id ASC',
        [],
    ],
]);

test('finds all active tlds', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->tldFindAllActive();

    expect($result)->toBeArray();
});

test('finds one active tld by id', function () {
    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($tldModel);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->tldFindOneActiveById(1);

    expect($result)->toBeInstanceOf(\Model_Tld::class);
});

test('gets tld pairs', function () {
    $returns = [
        0 => '.com',
    ];
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn($returns);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->tldGetPairs();

    expect($result)->toBeArray();
    expect($result)->toBe($returns);
});

test('checks if tld is already registered', function () {
    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($tldModel);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->tldAlreadyRegistered('.com');

    expect($result)->toBeTrue();
});

test('checks if tld is not registered', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->tldAlreadyRegistered('.com');

    expect($result)->toBeFalse();
});

test('removes tld', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $model = new \Model_Tld();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $result = $this->service->tldRm($model);

    expect($result)->toBeTrue();
});

test('converts tld to api array', function () {
    $tldRegistrar = new \Model_TldRegistrar();
    $tldRegistrar->loadBean(new \Tests\Helpers\DummyBean());
    $tldRegistrar->name = 'ResellerClub';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($tldRegistrar);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $model = new \Model_Tld();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->tld = '.com';
    $model->price_registration = 1;
    $model->price_renew = 1;
    $model->price_transfer = 1;
    $model->active = 1;
    $model->allow_register = 1;
    $model->allow_transfer = 1;
    $model->min_years = 2;
    $model->tld_registrar_id = 1;

    $result = $this->service->tldToApiArray($model);
    expect($result)->toBeArray();

    expect($result)->toHaveKey('tld');
    expect($result)->toHaveKey('price_registration');
    expect($result)->toHaveKey('price_renew');
    expect($result)->toHaveKey('price_transfer');
    expect($result)->toHaveKey('active');
    expect($result)->toHaveKey('allow_register');
    expect($result)->toHaveKey('allow_transfer');
    expect($result)->toHaveKey('min_years');
    expect($result)->toHaveKey('registrar');

    $registrar = $result['registrar'];
    expect($registrar)->toBeArray();
    expect($registrar)->toHaveKey('id');
    expect($registrar)->toHaveKey('title');

    expect($result['tld'])->toBe($model->tld);
    expect($result['price_registration'])->toBe($model->price_registration);
    expect($result['price_renew'])->toBe($model->price_renew);
    expect($result['price_transfer'])->toBe($model->price_transfer);
    expect($result['active'])->toBe($model->active);
    expect($result['allow_register'])->toBe($model->allow_register);
    expect($result['allow_transfer'])->toBe($model->allow_transfer);
    expect($result['min_years'])->toBe($model->min_years);

    expect($registrar['id'])->toBe($model->tld_registrar_id);
    expect($registrar['title'])->toBe($tldRegistrar->name);
});

test('finds one tld by tld', function () {
    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($tldModel);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->tldFindOneByTld('.com');

    expect($result)->toBeInstanceOf(\Model_Tld::class);
});

test('gets registrar search query', function () {
    [$query, $bindings] = $this->service->registrarGetSearchQuery([]);

    expect($query)->toBe('SELECT * FROM tld_registrar ORDER BY name ASC');
    expect($bindings)->toBeArray();
    expect($bindings)->toBe([]);
});

test('gets available registrars', function () {
    $registrars = [
        'Resellerclub' => 'Reseller Club',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn($registrars);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->registrarGetAvailable();
    expect($result)->toBeArray();
});

test('gets registrar pairs', function () {
    $registrars = [
        1 => 'Resellerclub',
        2 => 'Email',
        3 => 'Custom',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn($registrars);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->registrarGetPairs();

    expect($result)->toBeArray();
    expect($result)->toHaveCount(3);
});

test('gets active registrar', function () {
    $tldRegistrarModel = new \Model_TldRegistrar();
    $tldRegistrarModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($tldRegistrarModel);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->registrarGetActiveRegistrar();

    expect($result)->toBeInstanceOf(\Model_TldRegistrar::class);
});

test('gets registrar configuration', function () {
    $config = [
        'config_param' => 'config_value',
    ];

    $di = container();
    $this->service->setDi($di);

    $model = new \Model_TldRegistrar();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->config = json_encode($config);

    $result = $this->service->registrarGetConfiguration($model);

    expect($result)->toBeArray();
    expect($result)->toBe($config);
});

test('gets registrar adapter config', function () {
    $model = new \Model_TldRegistrar();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->registrar = 'Custom';

    $result = $this->service->registrarGetRegistrarAdapterConfig($model);
    expect($result)->toBeArray();
});

test('throws exception when getting registrar adapter config for non-existing registrar', function () {
    $model = new \Model_TldRegistrar();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->registrar = 'NonExisting';

    expect(fn () => $this->service->registrarGetRegistrarAdapterConfig($model))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('gets registrar adapter', function () {
    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);
    $model = new \Model_TldRegistrar();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->registrar = 'Custom';

    $result = $this->service->registrarGetRegistrarAdapter($model);

    expect($result)->toBeInstanceOf('Registrar_Adapter_' . $model->registrar);
});

test('throws exception when getting registrar adapter for non-existing registrar', function () {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('registrarGetConfiguration')
        ->atLeast()->once()
        ->andReturn([]);

    $model = new \Model_TldRegistrar();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->registrar = 'NonExisting';

    expect(fn () => $serviceMock->registrarGetRegistrarAdapter($model))
        ->toThrow(\Error::class);
});

test('removes registrar', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([]);
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $model = new \Model_TldRegistrar();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;
    $model->name = 'ResellerClub';

    $result = $this->service->registrarRm($model);

    expect($result)->toBeTrue();
});

test('throws exception when removing registrar with domains', function () {
    $serviceDomainModel = new \Model_ServiceDomain();
    $serviceDomainModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->with('ServiceDomain', 'tld_registrar_id = :registrar_id', [':registrar_id' => 1])
        ->atLeast()->once()
        ->andReturn([$serviceDomainModel]);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $model = new \Model_TldRegistrar();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    expect(fn () => $this->service->registrarRm($model))
        ->toThrow(\FOSSBilling\InformationException::class, 'Registrar is used by 1 domains');
});

test('converts registrar to api array', function () {
    $config = [
        'label' => 'Label',
        'form' => 1,
    ];

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('registrarGetRegistrarAdapterConfig')
        ->atLeast()->once()
        ->andReturn($config);
    $serviceMock->shouldReceive('registrarGetConfiguration')
        ->atLeast()->once()
        ->andReturn(['param1' => 'value1']);

    $model = new \Model_TldRegistrar();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;
    $model->name = 'ResellerClub';
    $model->test_mode = true;

    $serviceMock->registrarToApiArray($model);
});

test('creates tld', function () {
    $data = [
        'tld' => '.com',
        'tld_registrar_id' => 1,
        'price_registration' => 1,
        'price_renew' => 1,
        'price_transfer' => 1,
        'min_years' => random_int(1, 5),
        'allow_register' => 1,
        'allow_transfer' => 1,
        'updated_at' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $randId = 1;

    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($tldModel);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $result = $this->service->tldCreate($data);

    expect($result)->toBeInt();
    expect($result)->toBe($randId);
});

test('updates tld', function () {
    $data = [
        'tld' => '.com',
        'tld_registrar_id' => 1,
        'price_registration' => 1,
        'price_renew' => 1,
        'price_transfer' => 1,
        'min_years' => random_int(1, 5),
        'allow_register' => true,
        'allow_transfer' => true,
        'active' => true,
        'updated_at' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $randId = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $model = new \Model_Tld();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->tld = '.com';

    $result = $this->service->tldUpdate($model, $data);

    expect($result)->toBeTrue();
});

test('creates registrar', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $model = new \Model_TldRegistrar();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $result = $this->service->registrarCreate('ResellerClub');

    expect($result)->toBeTrue();
});

test('copies registrar', function () {
    $newId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($newId);

    $model = new \Model_TldRegistrar();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $model = new \Model_TldRegistrar();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->name = 'ResellerClub';
    $model->registrar = 'ResellerClub';
    $model->test_mode = 1;

    $result = $this->service->registrarCopy($model);

    expect($result)->toBeInt();
    expect($result)->toBe($newId);
});

test('updates registrar', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $data = [
        'title' => 'ResellerClub',
        'test_mode' => 1,
        'config' => [
            'param1' => 'value1',
        ],
    ];

    $model = new \Model_TldRegistrar();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->registrar = 'Custom';

    $result = $this->service->registrarUpdate($model, $data);

    expect($result)->toBeTrue();
});

test('updates domain', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $data = [
        'ns1' => 'ns1.example.com',
        'ns2' => 'ns2.example.com',
        'ns3' => 'ns3.example.com',
        'ns4' => 'ns4.example.com',
        'period' => 1,
        'privacy' => 1,
        'locked' => 1,
        'transfer_code' => 'EPPCODE',
    ];

    $model = new \Model_ServiceDomain();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $result = $this->service->updateDomain($model, $data);

    expect($result)->toBeTrue();
});
