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
use Box\Mod\Order\Service as OrderService;
use Box\Mod\Servicedomain\Entity\ServiceDomain;
use Box\Mod\Servicedomain\Entity\Tld;
use Box\Mod\Servicedomain\Entity\TldRegistrar;
use Box\Mod\Servicedomain\Repository\DomainRepository;
use Box\Mod\Servicedomain\Repository\TldRegistrarRepository;
use Box\Mod\Servicedomain\Repository\TldRepository;
use Box\Mod\Servicedomain\Service;
use Box\Mod\System\Service as SystemService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\setEntityId;

class ServicedomainServiceSyncProbe extends Service
{
    public function syncWhoisPublic(ServiceDomain $model, Order $order): void
    {
        $this->syncWhois($model, $order);
    }
}

afterEach(function (): void {
    Mockery::close();
});

test('gets dependency injection container', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('gets cart product title', function (array $data, string $expected): void {
    $service = new Service();
    $product = (new Box\Mod\Product\Entity\Product())->setTitle('Example.com Registration');

    $result = $service->getCartProductTitle($product, $data);

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

test('throws exception for invalid order data action', function (): void {
    $service = new Service();
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')
        ->atLeast()->once();

    $di = container();
    $di['validator'] = $validatorMock;
    $service->setDi($di);

    $data = [
        'action' => 'NonExistingAction',
    ];

    expect(fn () => $service->validateOrderData($data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('throws exception for transfer order data with invalid tld', function (array $data, array $isSldValidArr, array $tldFindOneByTldArr, array $canBeTransferred): void {
    $service = new Service();
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
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
        ->toThrow(FOSSBilling\Exception::class);
})->with(function () {
    $tldModel = new Tld();
    $tldModel->setTld('.com');
    $tldModel->setActive(true);

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

test('throws exception for register order data with invalid tld', function (array $data, array $isSldValidArr, array $tldFindOneByTldArr, array $isDomainAvailable): void {
    $service = new Service();
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
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
        ->toThrow(FOSSBilling\Exception::class);
})->with(function () {
    $tldModel = new Tld();
    $tldModel->setTld('.com');
    $tldModel->setMinYears(2);
    $tldModel->setActive(true);

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
                'register_years' => $tldModel->getMinYears() - 1,
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

test('rejects a registration period outside the tld allowed periods list', function (): void {
    $tldModel = new Tld();
    $tldModel->setTld('.com');
    $tldModel->setMinYears(1);
    $tldModel->setPeriods('1,2,5');
    $tldModel->setActive(true);

    $data = [
        'action' => 'register',
        'register_sld' => 'example',
        'register_years' => 3,
        'register_tld' => '.com',
    ];

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')->atLeast()->once()->andReturn(true);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->zeroOrMoreTimes();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('tldFindOneByTld')->atLeast()->once()->andReturn($tldModel);

    $di = container();
    $di['validator'] = $validatorMock;
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->validateOrderData($data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('accepts a registration period within the tld allowed periods list', function (): void {
    $tldModel = new Tld();
    $tldModel->setTld('.com');
    $tldModel->setMinYears(1);
    $tldModel->setPeriods('1,2,5');
    $tldModel->setActive(true);

    $data = [
        'action' => 'register',
        'register_sld' => 'example',
        'register_years' => 5,
        'register_tld' => '.com',
    ];

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')->atLeast()->once()->andReturn(true);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->zeroOrMoreTimes();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('tldFindOneByTld')->atLeast()->once()->andReturn($tldModel);
    $serviceMock->shouldReceive('isDomainAvailable')->atLeast()->once()->andReturn(true);

    $di = container();
    $di['validator'] = $validatorMock;
    $serviceMock->setDi($di);

    $serviceMock->validateOrderData($data);

    expect($data['period'])->toBe('5Y');
});

test('rejects a crafted order for an inactive tld before contacting the registrar', function (string $action): void {
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')->atLeast()->once();
    $validatorMock->shouldReceive('isSldValid')->once()->andReturnTrue();

    $tld = new Tld();
    $tld->setTld('.com');
    $tld->setActive(false);
    $tld->setMinYears(1);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('tldFindOneByTld')->once()->andReturn($tld);
    $service->shouldReceive('isDomainAvailable')->never();
    $service->shouldReceive('canBeTransferred')->never();

    $di = container();
    $di['validator'] = $validatorMock;
    $service->setDi($di);

    $data = $action === 'register'
        ? ['action' => 'register', 'register_sld' => 'example', 'register_tld' => '.com', 'register_years' => 1]
        : ['action' => 'transfer', 'transfer_sld' => 'example', 'transfer_tld' => '.com'];

    expect(fn () => $service->validateOrderData($data))
        ->toThrow(FOSSBilling\InformationException::class, 'TLD is not active');
})->with(['register', 'transfer']);

test('creates action', function (int|string $registerYears): void {
    $service = new Service();
    $tldModel = new Tld();
    $tldModel->setRegistrar(new TldRegistrar());

    $data = [
        'action' => 'register',
        'register_sld' => 'example',
        'register_tld' => '.com',
        'register_years' => $registerYears,
        'ns2' => 'custom-ns2.example.com',
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

    $client = createEntity(Box\Mod\Client\Entity\Client::class, [
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'email' => 'client@example.com',
        'company' => 'company',
        'address_1' => 'address_1',
        'address_2' => 'address_2',
        'country' => 'country',
        'city' => 'city',
        'state' => 'state',
        'postcode' => 'postcode',
        'phone_cc' => 'phone_cc',
        'phone' => 'phone',
    ]);

    $clientRepo = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepo->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($client);

    $di = container();
    $di['mod_service'] = $di->protect(function ($name) use ($orderServiceMock, $systemServiceMock) {
        if ($name == 'order') {
            return $orderServiceMock;
        }

        return $systemServiceMock;
    });
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->andReturn($clientRepo);

    $serviceMock->setDi($di);

    $order = createEntity(Order::class, ['client_id' => 1]);

    $result = $serviceMock->action_create($order);
    expect($result)->toBeInstanceOf(ServiceDomain::class);
    expect($result->getNs2())->toBe('custom-ns2.example.com');
    expect($result->getPeriod())->toBe(2);
})->with([
    'int register_years' => [2],
    'string register_years' => ['2'],
]);

test('throws exception when creating action with missing nameservers', function (): void {
    $service = new Service();
    $tldModel = new Tld();
    $tldModel->setRegistrar(new TldRegistrar());

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

    $order = createEntity(Order::class, ['client_id' => 1]);

    expect(fn () => $serviceMock->action_create($order))
        ->toThrow(FOSSBilling\Exception::class);
});

test('activates action', function (string $action, string $registerDomainCalled, string $transferDomainCalled): void {
    $service = new Service();

    $domainModel = new ServiceDomain();
    $domainModel->setRegistrar(new TldRegistrar());
    $domainModel->setAction($action);

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
        ->andReturn([new Registrar_Domain(), $registrarAdapterMock]);
    $serviceMock->shouldReceive('syncWhois')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $serviceMock->setDi($di);

    $order = createEntity(Order::class, ['client_id' => 1]);
    $result = $serviceMock->action_activate($order);
    expect($result)->toBeInstanceOf(ServiceDomain::class);
})->with([
    ['register', 'atLeast', 'never'],
    ['transfer', 'never', 'atLeast'],
]);

test('throws exception when activating without order service', function (): void {
    $service = new Service();
    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $service->setDi($di);

    $order = createEntity(Order::class, ['client_id' => 1]);

    expect(fn (): ServiceDomain => $service->action_activate($order))
        ->toThrow(FOSSBilling\Exception::class);
});

test('renews action', function (): void {
    $service = new Service();
    $domainModel = new ServiceDomain();
    $domainModel->setRegistrar(new TldRegistrar());
    $domainModel->setAction('register');

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
        ->andReturn([new Registrar_Domain(), $registrarAdapterMock]);
    $serviceMock->shouldReceive('syncWhois')
        ->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $serviceMock->setDi($di);

    $order = createEntity(Order::class, ['client_id' => 1]);
    $result = $serviceMock->action_renew($order);

    expect($result)->toBeTrue();
});

test('throws exception when renewing without order service', function (): void {
    $service = new Service();
    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $service->setDi($di);

    $order = createEntity(Order::class, ['id' => 1, 'client_id' => 1]);

    expect(fn (): bool => $service->action_renew($order))
        ->toThrow(FOSSBilling\Exception::class);
});

test('suspends action', function (): void {
    $service = new Service();
    $order = createEntity(Order::class);
    $result = $service->action_suspend($order);
    expect($result)->toBeTrue();
});

test('unsuspends action', function (): void {
    $service = new Service();
    $order = createEntity(Order::class);
    $result = $service->action_unsuspend($order);
    expect($result)->toBeTrue();
});

test('cancels action', function (): void {
    $service = new Service();

    $domainModel = new ServiceDomain();
    $domainModel->setRegistrar(new TldRegistrar());
    $domainModel->setAction('register');

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
        ->andReturn([new Registrar_Domain(), $registrarAdapterMock]);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $serviceMock->setDi($di);

    $order = createEntity(Order::class, ['client_id' => 1]);
    $result = $serviceMock->action_cancel($order);

    expect($result)->toBeTrue();
});

test('throws exception when canceling without order service', function (): void {
    $service = new Service();
    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $service->setDi($di);

    $order = createEntity(Order::class, ['id' => 1, 'client_id' => 1]);

    expect(fn (): bool => $service->action_cancel($order))
        ->toThrow(FOSSBilling\Exception::class);
});

test('uncancels action', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('action_activate')
        ->atLeast()->once()
        ->andReturn(new ServiceDomain());

    $order = createEntity(Order::class, ['client_id' => 1]);
    $result = $serviceMock->action_uncancel($order);

    expect($result)->toBeTrue();
});

test('deletes action', function (): void {
    $service = new Service();

    $domainModel = new ServiceDomain();
    $domainModel->setRegistrar(new TldRegistrar());
    $domainModel->setAction('register');

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
        ->andReturn([new Registrar_Domain(), $registrarAdapterMock]);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $orderServiceMock);
    $serviceMock->setDi($di);

    $order = createEntity(Order::class, ['status' => Order::STATUS_ACTIVE]);
    $result = $serviceMock->action_delete($order);

    expect($result)->toBeNull();
});

test('updates nameservers', function (): void {
    $service = new Service();
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('modifyNs')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new Registrar_Domain(), $registrarAdapterMock]);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $data = [
        'ns1' => 'ns1.example.com',
        'ns2' => 'ns2.example.com',
        'ns3' => 'ns3.example.com',
        'ns4' => 'ns4.example.com',
    ];

    $serviceDomainModel = new ServiceDomain();
    $result = $serviceMock->updateNameservers($serviceDomainModel, $data);

    expect($result)->toBeTrue();
});

test('throws exception when updating nameservers with missing ns1 or ns2', function (array $data): void {
    $service = new Service();
    $serviceDomainModel = new ServiceDomain();

    expect(fn (): bool => $service->updateNameservers($serviceDomainModel, $data))
        ->toThrow(FOSSBilling\Exception::class);
})->with([
    [['ns2' => 'ns2.example.com']],
    [['ns1' => 'ns1.example.com']],
]);

test('updates contacts', function (): void {
    $service = new Service();
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('modifyContact')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new Registrar_Domain(), $registrarAdapterMock]);

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray')
        ->zeroOrMoreTimes();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['validator'] = $validatorMock;
    $serviceMock->setDi($di);

    $data = [
        'contact' => [
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'email' => 'email',
            'address1' => 'address1',
            'country' => 'country',
            'city' => 'city',
            'state' => 'state',
            'postcode' => 'postcode',
            'phone_cc' => 'phone_cc',
            'phone' => 'phone',
        ],
    ];
    $serviceDomainModel = new ServiceDomain();
    $result = $serviceMock->updateContacts($serviceDomainModel, $data);

    expect($result)->toBeTrue();
    expect($serviceDomainModel->getContactCompany())->toBeNull();
    expect($serviceDomainModel->getContactAddress2())->toBeNull();
});

test('gets transfer code', function (): void {
    $service = new Service();
    $epp = 'EPPCODE';

    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('getEpp')
        ->atLeast()->once()
        ->andReturn($epp);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new Registrar_Domain(), $registrarAdapterMock]);

    $serviceDomainModel = new ServiceDomain();
    $result = $serviceMock->getTransferCode($serviceDomainModel);

    expect($result)->toBeString();
    expect($result)->toBe($epp);
});

test('locks domain', function (): void {
    $service = new Service();
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('lock')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new Registrar_Domain(), $registrarAdapterMock]);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $serviceDomainModel = new ServiceDomain();
    $result = $serviceMock->lock($serviceDomainModel);

    expect($result)->toBeTrue();
});

test('unlocks domain', function (): void {
    $service = new Service();
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('unlock')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new Registrar_Domain(), $registrarAdapterMock]);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $serviceDomainModel = new ServiceDomain();
    $result = $serviceMock->unlock($serviceDomainModel);

    expect($result)->toBeTrue();
});

test('enables privacy protection', function (): void {
    $service = new Service();
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('enablePrivacyProtection')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new Registrar_Domain(), $registrarAdapterMock]);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $serviceDomainModel = new ServiceDomain();
    $result = $serviceMock->enablePrivacyProtection($serviceDomainModel);

    expect($result)->toBeTrue();
});

test('disables privacy protection', function (): void {
    $service = new Service();
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('disablePrivacyProtection')
        ->atLeast()->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('_getD')
        ->atLeast()->once()
        ->andReturn([new Registrar_Domain(), $registrarAdapterMock]);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $serviceDomainModel = new ServiceDomain();
    $result = $serviceMock->disablePrivacyProtection($serviceDomainModel);

    expect($result)->toBeTrue();
});

test('checks if domain can be transferred', function (): void {
    $service = new Service();
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('isDomaincanBeTransferred')
        ->atLeast()->once()
        ->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('registrarValidateConfiguration')->once();
    $serviceMock->shouldReceive('registrarGetRegistrarAdapter')
        ->once()
        ->andReturn($registrarAdapterMock);

    $tldRegistrar = new TldRegistrar();
    setEntityId($tldRegistrar, 1);

    $di = container();
    $serviceMock->setDi($di);

    $tld = new Tld();
    $tld->setAllowTransfer(true);
    $tld->setTld('.com');
    $tld->setRegistrar($tldRegistrar);

    $result = $serviceMock->canBeTransferred($tld, 'example');

    expect($result)->toBeTrue();
});

test('throws exception when checking transfer with empty sld', function (): void {
    $service = new Service();
    expect(fn () => $service->canBeTransferred(new Tld(), ''))
        ->toThrow(FOSSBilling\Exception::class);
});

test('throws exception when checking transfer not allowed', function (): void {
    $service = new Service();
    $tldModel = new Tld();
    $tldModel->setAllowTransfer(false);

    expect(fn () => $service->canBeTransferred($tldModel, 'example'))
        ->toThrow(FOSSBilling\Exception::class);
});

test('throws registrar not found when checking transfer without registrar', function (): void {
    $service = new Service();
    $tldModel = new Tld();
    $tldModel->setAllowTransfer(true);
    $tldModel->setTld('.com');

    expect(fn () => $service->canBeTransferred($tldModel, 'example'))
        ->toThrow(FOSSBilling\Exception::class, 'Registrar not found');
});

test('checks if domain is available', function (): void {
    $service = new Service();
    $registrarAdapterMock = Mockery::mock('Registrar_Adapter_Custom');
    $registrarAdapterMock->shouldReceive('isDomainAvailable')
        ->atLeast()->once()
        ->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('registrarValidateConfiguration')->once();
    $serviceMock->shouldReceive('registrarGetRegistrarAdapter')
        ->once()
        ->andReturn($registrarAdapterMock);

    $tldRegistrar = new TldRegistrar();
    setEntityId($tldRegistrar, 1);

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['validator'] = $validatorMock;
    $serviceMock->setDi($di);

    $tld = new Tld();
    $tld->setAllowRegister(true);
    $tld->setTld('.com');
    $tld->setRegistrar($tldRegistrar);

    $result = $serviceMock->isDomainAvailable($tld, 'example');

    expect($result)->toBeTrue();
});

test('throws exception when checking availability with empty sld', function (): void {
    $service = new Service();
    $tldModel = new Tld();

    expect(fn () => $service->isDomainAvailable($tldModel, ''))
        ->toThrow(FOSSBilling\Exception::class);
});

test('throws exception when checking availability with invalid sld', function (): void {
    $service = new Service();
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')
        ->atLeast()->once()
        ->andReturn(false);

    $di = container();
    $di['validator'] = $validatorMock;
    $service->setDi($di);

    $tldModel = new Tld();

    expect(fn () => $service->isDomainAvailable($tldModel, 'example'))
        ->toThrow(FOSSBilling\Exception::class);
});

test('throws exception when checking availability not allowed to register', function (): void {
    $service = new Service();
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isSldValid')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['validator'] = $validatorMock;
    $service->setDi($di);

    $model = new Tld();
    $model->setAllowRegister(false);

    expect(fn () => $service->isDomainAvailable($model, 'example'))
        ->toThrow(FOSSBilling\Exception::class);
});

test('syncs expiration date from the registrar', function (): void {
    $whois = new Registrar_Domain();
    $whois->setExpirationTime((new DateTime('2030-06-15 00:00:00', new DateTimeZone('UTC')))->getTimestamp());

    $adapter = Mockery::mock(Registrar_Adapter_Custom::class);
    $adapter->shouldReceive('getDomainDetails')
        ->once()
        ->andReturn($whois);

    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('_getD')
        ->once()
        ->andReturn([new Registrar_Domain(), $adapter]);

    $model = new ServiceDomain();

    $di = container();
    $service->setDi($di);

    $service->syncExpirationDate($model);

    expect($model->getExpiresAt()?->format('Y-m-d H:i:s'))->toBe('2030-06-15 00:00:00');
});

test('preserves the existing expiration date when the registrar has none', function (): void {
    $whois = new Registrar_Domain();

    $adapter = Mockery::mock(Registrar_Adapter_Custom::class);
    $adapter->shouldReceive('getDomainDetails')
        ->once()
        ->andReturn($whois);

    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('_getD')
        ->once()
        ->andReturn([new Registrar_Domain(), $adapter]);

    $model = new ServiceDomain();
    $model->setExpiresAt(new DateTime('2030-06-15 00:00:00', new DateTimeZone('UTC')));

    $di = container();
    $service->setDi($di);

    $service->syncExpirationDate($model);

    expect($model->getExpiresAt()?->format('Y-m-d H:i:s'))->toBe('2030-06-15 00:00:00');
});

test('syncWhois stores null dates when registrar dates are unavailable', function (): void {
    $whois = new Registrar_Domain();
    $contact = new Registrar_Domain_Contact();
    $contact->setName('Test User')
        ->setEmail('test@example.com')
        ->setCompany('Example')
        ->setAddress1('Address 1')
        ->setAddress2('Address 2')
        ->setCountry('US')
        ->setCity('City')
        ->setState('State')
        ->setZip('12345')
        ->setTelCc('1')
        ->setTel('5551234567');
    $whois->setContactRegistrar($contact);

    $adapter = Mockery::mock(Registrar_Adapter_Custom::class);
    $adapter->shouldReceive('getDomainDetails')
        ->once()
        ->andReturn($whois);

    $service = Mockery::mock(ServicedomainServiceSyncProbe::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('_getD')
        ->once()
        ->andReturn([new Registrar_Domain(), $adapter]);

    $model = new ServiceDomain();

    $order = createEntity(Order::class);

    $di = container();
    $service->setDi($di);

    $service->syncWhoisPublic($model, $order);

    expect($model->getExpiresAt())->toBeNull()
        ->and($model->getRegisteredAt())->toBeNull();
});

test('converts to api array', function (?Box\Mod\Staff\Entity\Admin $identity, string $dbLoadCalled): void {
    $service = new Service();
    $model = new ServiceDomain();

    $model->setSld('sld');
    $model->setTld('tld');
    $model->setNs1('ns1.example.com');
    $model->setNs2('ns2.example.com');
    $model->setNs3('ns3.example.com');
    $model->setNs4('ns4.example.com');
    $model->setPeriod(1);
    $model->setPrivacy(true);
    $model->setLocked(true);
    $model->setRegisteredAt(new DateTime(date('Y-m-d H:i:s')));
    $model->setExpiresAt(new DateTime(date('Y-m-d H:i:s')));

    $model->setContactFirstName('first_name');
    $model->setContactLastName('last_name');
    $model->setContactEmail('email');
    $model->setContactCompany('company');
    $model->setContactAddress1('address1');
    $model->setContactAddress2('address2');
    $model->setContactCountry('country');
    $model->setContactCity('city');
    $model->setContactState('state');
    $model->setContactPostcode('postcode');
    $model->setContactPhoneCc('phone_cc');
    $model->setContactPhone('phone');
    $model->setTransferCode('EPPCODE');

    $tldRegistrar = new TldRegistrar();
    $tldRegistrar->setName('ResellerClub');
    setEntityId($tldRegistrar, 1);
    $model->setRegistrar($tldRegistrar);

    $di = container();
    $service->setDi($di);

    $result = $service->toApiArray($model, true, $identity);

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

    expect($result['domain'])->toBe($model->getSld() . $model->getTld());
    expect($result['sld'])->toBe($model->getSld());
    expect($result['tld'])->toBe($model->getTld());
    expect($result['ns1'])->toBe($model->getNs1());
    expect($result['ns2'])->toBe($model->getNs2());
    expect($result['ns3'])->toBe($model->getNs3());
    expect($result['ns4'])->toBe($model->getNs4());
    expect($result['period'])->toBe($model->getPeriod());
    expect($result['privacy'])->toBe($model->getPrivacy());
    expect($result['locked'])->toBe($model->isLocked());

    expect($contact['first_name'])->toBe($model->getContactFirstName());
    expect($contact['last_name'])->toBe($model->getContactLastName());
    expect($contact['email'])->toBe($model->getContactEmail());
    expect($contact['company'])->toBe($model->getContactCompany());
    expect($contact['address1'])->toBe($model->getContactAddress1());
    expect($contact['address2'])->toBe($model->getContactAddress2());
    expect($contact['country'])->toBe($model->getContactCountry());
    expect($contact['city'])->toBe($model->getContactCity());
    expect($contact['state'])->toBe($model->getContactState());
    expect($contact['postcode'])->toBe($model->getContactPostcode());
    expect($contact['phone_cc'])->toBe($model->getContactPhoneCc());
    expect($contact['phone'])->toBe($model->getContactPhone());

    if ($identity instanceof Box\Mod\Staff\Entity\Admin) {
        expect($result)->toHaveKey('transfer_code');
        expect($result)->toHaveKey('registrar');
        expect($result['transfer_code'])->toBe($model->getTransferCode());
        expect($result['registrar'])->toBe($tldRegistrar->getName());
    }
})->with([
    [\Tests\Helpers\admin(...), 'atLeast'],
    [null, 'never'],
]);

test('converts admin domain to api array without a registrar', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldNotReceive('getRepository');

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->toApiArray(new ServiceDomain(), false, \Tests\Helpers\admin());

    expect($result['registrar'])->toBeNull();
});

test('handles on before admin cron run event', function (): void {
    $service = new Service();
    $di = container();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('batchSyncExpirationDates')
        ->atLeast()->once()
        ->andReturn(true);
    $di['mod_service'] = $di->protect(fn ($serviceName): Mockery\MockInterface => $serviceMock);

    $boxEventMock = Mockery::mock('\Box_Event');
    $boxEventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $result = $service->onBeforeAdminCronRun($boxEventMock);

    expect($result)->toBeTrue();
});

test('batch syncs expiration dates', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('syncExpirationDate')
        ->atLeast()->once();

    $systemServiceMock = Mockery::mock(SystemService::class);
    $systemServiceMock->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn(null);
    $systemServiceMock->shouldReceive('setParamValue')
        ->atLeast()->once();

    $domainModel = new ServiceDomain();
    $domainRepo = Mockery::mock(DomainRepository::class);
    $domainRepo->shouldReceive('findAll')->andReturn([$domainModel]);
    $domainRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(ServiceDomain::class)->andReturn($domainRepo);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn ($name) => $systemServiceMock);
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $result = $serviceMock->batchSyncExpirationDates();

    expect($result)->toBeTrue();
});

test('does not advance the last sync marker when a domain sync fails', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('syncExpirationDate')
        ->atLeast()->once()
        ->andThrow(new Exception('registrar unavailable'));

    $systemServiceMock = Mockery::mock(SystemService::class);
    $systemServiceMock->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn(null);
    $systemServiceMock->shouldReceive('setParamValue')
        ->never();

    $domainModel = new ServiceDomain();
    $domainRepo = Mockery::mock(DomainRepository::class);
    $domainRepo->shouldReceive('findAll')->andReturn([$domainModel]);
    $domainRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(ServiceDomain::class)->andReturn($domainRepo);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn ($name) => $systemServiceMock);
    $di['logger'] = new Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $result = $serviceMock->batchSyncExpirationDates();

    expect($result)->toBeTrue();
});

test('returns false when batch sync already run today', function (): void {
    $service = new Service();
    $systemServiceMock = Mockery::mock(SystemService::class);
    $systemServiceMock->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn(date('Y-m-d H:i:s'));
    $systemServiceMock->shouldReceive('setParamValue')
        ->never();

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name) => $systemServiceMock);
    $service->setDi($di);

    $result = $service->batchSyncExpirationDates();

    expect($result)->toBeFalse();
});

test('gets tld search query', function (array $data, array $expectedConditions): void {
    $service = new Service();
    $query = Mockery::mock(QueryBuilder::class);
    foreach ($expectedConditions as [$condition, $parameter, $value]) {
        $query->shouldReceive('andWhere')->once()->with($condition)->andReturnSelf();
        $query->shouldReceive('setParameter')->once()->with($parameter, $value)->andReturnSelf();
    }
    $query->shouldReceive('orderBy')->once()->with('t.id', 'ASC')->andReturnSelf();

    $tldRepo = Mockery::mock(TldRepository::class);
    $tldRepo->shouldReceive('createQueryBuilder')->once()->with('t')->andReturn($query);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->once()->with(Tld::class)->andReturn($tldRepo);

    $di = container();
    $di['em'] = $emMock;

    $service->setDi($di);

    expect($service->tldGetSearchQuery($data))->toBe($query);
})->with([
    [
        [],
        [],
    ],
    [
        ['hide_inactive' => true],
        [['t.active = :active', 'active', true]],
    ],
    [
        ['allow_register' => true],
        [['t.allowRegister = :allowRegister', 'allowRegister', true]],
    ],
    [
        ['allow_register' => false],
        [['t.allowRegister = :allowRegister', 'allowRegister', false]],
    ],
    [
        ['allow_transfer' => true],
        [['t.allowTransfer = :allowTransfer', 'allowTransfer', true]],
    ],
    [
        ['allow_transfer' => false],
        [['t.allowTransfer = :allowTransfer', 'allowTransfer', false]],
    ],
    [
        ['hide_inactive' => true, 'allow_register' => true, 'allow_transfer' => true],
        [
            ['t.active = :active', 'active', true],
            ['t.allowRegister = :allowRegister', 'allowRegister', true],
            ['t.allowTransfer = :allowTransfer', 'allowTransfer', true],
        ],
    ],
]);

test('finds all active tlds', function (): void {
    $service = new Service();

    $tldRepo = Mockery::mock(TldRepository::class);
    $tldRepo->shouldReceive('findAllActive')->atLeast()->once()->andReturn([]);
    $tldRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(Tld::class)->andReturn($tldRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->tldFindAllActive();

    expect($result)->toBeArray();
});

test('finds one active tld by id', function (): void {
    $service = new Service();
    $tldModel = new Tld();
    setEntityId($tldModel, 1);

    $tldRepo = Mockery::mock(TldRepository::class);
    $tldRepo->shouldReceive('findOneActiveById')->with(1)->andReturn($tldModel);
    $tldRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(Tld::class)->andReturn($tldRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->tldFindOneActiveById(1);

    expect($result)->toBeInstanceOf(Tld::class);
});

test('gets tld pairs', function (): void {
    $service = new Service();
    $returns = [
        0 => '.com',
    ];

    $tldRepo = Mockery::mock(TldRepository::class);
    $tldRepo->shouldReceive('getIdTldPairs')->andReturn($returns);
    $tldRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(Tld::class)->andReturn($tldRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->tldGetPairs();

    expect($result)->toBeArray();
    expect($result)->toBe($returns);
});

test('checks if tld is already registered', function (): void {
    $service = new Service();
    $tldModel = new Tld();
    $tldModel->setTld('.com');

    $tldRepo = Mockery::mock(TldRepository::class);
    $tldRepo->shouldReceive('findOneByTld')->with('.com')->andReturn($tldModel);
    $tldRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(Tld::class)->andReturn($tldRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->tldAlreadyRegistered('.com');

    expect($result)->toBeTrue();
});

test('checks if tld is not registered', function (): void {
    $service = new Service();

    $tldRepo = Mockery::mock(TldRepository::class);
    $tldRepo->shouldReceive('findOneByTld')->with('.com')->andReturn(null);
    $tldRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(Tld::class)->andReturn($tldRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->tldAlreadyRegistered('.com');

    expect($result)->toBeFalse();
});

test('removes tld', function (): void {
    $service = new Service();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = new Tld();
    setEntityId($model, 1);

    $result = $service->tldRm($model);

    expect($result)->toBeTrue();
});

test('converts tld to api array', function (): void {
    $service = new Service();
    $tldRegistrar = new TldRegistrar();
    $tldRegistrar->setName('ResellerClub');
    setEntityId($tldRegistrar, 1);

    $di = container();
    $service->setDi($di);

    $model = new Tld();
    setEntityId($model, 1);
    $model->setTld('.com');
    $model->setPriceRegistration('1.00');
    $model->setPriceRenew('1.00');
    $model->setPriceTransfer('1.00');
    $model->setActive(true);
    $model->setAllowRegister(true);
    $model->setAllowTransfer(true);
    $model->setMinYears(2);
    $model->setPeriods('5,2,2,10');
    $model->setRegistrar($tldRegistrar);

    $result = $service->tldToApiArray($model, \Tests\Helpers\admin());
    expect($result)->toBeArray();

    expect($result)->toHaveKey('tld');
    expect($result)->toHaveKey('price_registration');
    expect($result)->toHaveKey('price_renew');
    expect($result)->toHaveKey('price_transfer');
    expect($result)->toHaveKey('active');
    expect($result)->toHaveKey('allow_register');
    expect($result)->toHaveKey('allow_transfer');
    expect($result)->toHaveKey('min_years');
    expect($result)->toHaveKey('periods');
    expect($result)->toHaveKey('registrar');

    $registrar = $result['registrar'];
    expect($registrar)->toBeArray();
    expect($registrar)->toHaveKey('id');
    expect($registrar)->toHaveKey('title');

    expect($result['tld'])->toBe($model->getTld());
    expect($result['price_registration'])->toBe($model->getPriceRegistration());
    expect($result['price_renew'])->toBe($model->getPriceRenew());
    expect($result['price_transfer'])->toBe($model->getPriceTransfer());
    expect($result['active'])->toBe($model->isActive());
    expect($result['allow_register'])->toBe($model->isAllowRegister());
    expect($result['allow_transfer'])->toBe($model->isAllowTransfer());
    expect($result['min_years'])->toBe($model->getMinYears());
    expect($result['periods'])->toBe([2, 5, 10]);

    expect($registrar['id'])->toBe($tldRegistrar->getId());
    expect($registrar['title'])->toBe($tldRegistrar->getName());
});

test('converts admin tld to api array without a registrar', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldNotReceive('getRepository');

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->tldToApiArray(new Tld(), \Tests\Helpers\admin());

    expect($result['registrar'])->toBe([
        'id' => null,
        'title' => null,
    ]);
});

test('finds one tld by tld', function (): void {
    $service = new Service();
    $tldModel = new Tld();

    $tldRepo = Mockery::mock(TldRepository::class);
    $tldRepo->shouldReceive('findOneByTld')->once()->with('.com')->andReturn(null);
    $tldRepo->shouldReceive('findOneByTld')->once()->with('com')->andReturn($tldModel);
    $tldRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(Tld::class)->andReturn($tldRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->tldFindOneByTld(' COM. ');

    expect($result)->toBeInstanceOf(Tld::class);
});

test('normalizes tlds', function (string $input, string $expected): void {
    $service = new Service();

    expect($service->normalizeTld($input))->toBe($expected);
})->with([
    'leading dot' => ['.com', '.com'],
    'missing leading dot' => ['com', '.com'],
    'case and whitespace' => [' .COM.UA. ', '.com.ua'],
    'internationalized tld' => ['.рф', '.xn--p1ai'],
]);

test('finds one tld by id', function (): void {
    $service = new Service();
    $tldModel = new Tld();
    $tldModel->setTld('not a valid tld');

    $tldRepo = Mockery::mock(TldRepository::class);
    $tldRepo->shouldReceive('find')->with(1)->andReturn($tldModel);
    $tldRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(Tld::class)->andReturn($tldRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    expect($service->tldFindOneById(1))->toBe($tldModel);
    expect($tldModel->getTld())->toBe('not a valid tld');
});

test('rejects invalid tlds', function (string $input): void {
    $service = new Service();

    expect(fn (): string => $service->normalizeTld($input))
        ->toThrow(FOSSBilling\InformationException::class, 'TLD is invalid');
})->with([
    'empty' => '',
    'only dots' => '..',
    'empty label' => '.com..ua',
    'whitespace within label' => '.com ua',
    'leading hyphen' => '.-com',
    'trailing hyphen' => '.com-',
]);

test('gets registrar search query', function (): void {
    $service = new Service();
    $query = Mockery::mock(QueryBuilder::class);
    $query->shouldReceive('orderBy')->once()->with('tr.name', 'ASC')->andReturnSelf();

    $registrarRepo = Mockery::mock(TldRegistrarRepository::class);
    $registrarRepo->shouldReceive('createQueryBuilder')->once()->with('tr')->andReturn($query);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->once()->with(TldRegistrar::class)->andReturn($registrarRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    expect($service->registrarGetSearchQuery([]))->toBe($query);
});

test('gets available registrars', function (): void {
    $service = new Service();
    $registrars = [
        ['registrar' => 'ResellerClub'],
    ];

    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn($registrars);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->andReturn($connectionMock);
    $emMock->shouldReceive('getRepository')->byDefault()->andReturn(Mockery::mock()->shouldIgnoreMissing());

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->registrarGetAvailable();
    expect($result)->toBeArray();
});

test('gets registrar pairs', function (): void {
    $service = new Service();
    $registrars = [
        1 => 'Resellerclub',
        2 => 'Email',
        3 => 'Custom',
    ];

    $trRepo = Mockery::mock(TldRegistrarRepository::class);
    $trRepo->shouldReceive('getIdNamePairs')->andReturn($registrars);
    $trRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(TldRegistrar::class)->andReturn($trRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->registrarGetPairs();

    expect($result)->toBeArray();
    expect($result)->toHaveCount(3);
});

test('gets active registrar', function (): void {
    $service = new Service();
    $tldRegistrarModel = new TldRegistrar();
    $tldRegistrarModel->setConfig(json_encode(['key' => 'val']));

    $trRepo = Mockery::mock(TldRegistrarRepository::class);
    $trRepo->shouldReceive('findActiveRegistrar')->andReturn($tldRegistrarModel);
    $trRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(TldRegistrar::class)->andReturn($trRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->registrarGetActiveRegistrar();

    expect($result)->toBeInstanceOf(TldRegistrar::class);
});

test('gets registrar configuration', function (): void {
    $service = new Service();
    $config = [
        'config_param' => 'config_value',
    ];

    $di = container();
    $service->setDi($di);

    $model = new TldRegistrar();
    $model->setConfig(json_encode($config));

    $result = $service->registrarGetConfiguration($model);

    expect($result)->toBeArray();
    expect($result)->toBe($config);
});

test('gets registrar adapter config', function (): void {
    $service = new Service();
    $model = new TldRegistrar();
    $model->setRegistrar('Custom');

    $result = $service->registrarGetRegistrarAdapterConfig($model);
    expect($result)->toBeArray();
});

test('throws exception when getting registrar adapter config for non-existing registrar', function (): void {
    $service = new Service();
    $model = new TldRegistrar();
    $model->setRegistrar('NonExisting');

    expect(fn () => $service->registrarGetRegistrarAdapterConfig($model))
        ->toThrow(FOSSBilling\Exception::class);
});

test('gets registrar adapter', function (): void {
    $service = new Service();
    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);
    $model = new TldRegistrar();
    $model->setRegistrar('Custom');

    $result = $service->registrarGetRegistrarAdapter($model);

    expect($result)->toBeInstanceOf('Registrar_Adapter_' . $model->getRegistrar());
});

test('validates required registrar fields even when the adapter constructor does not', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('registrarGetConfiguration')->once()->andReturn([]);
    $service->shouldReceive('registrarGetRegistrarAdapterConfig')->once()->andReturn([
        'form' => [
            'host' => ['text', ['label' => 'EPP host', 'required' => true]],
        ],
    ]);
    $model = new TldRegistrar();
    $model->setName('Namingo EPP');
    $model->setRegistrar('Namingo');
    $model->setTestMode(false);

    expect(fn () => $service->registrarValidateConfiguration($model))
        ->toThrow(FOSSBilling\InformationException::class, 'missing required configuration');
});

test('validates conditional registrar fields using boolean snake case accessors', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('registrarGetConfiguration')->once()->andReturn([]);
    $service->shouldReceive('registrarGetRegistrarAdapterConfig')->once()->andReturn([
        'form' => [
            'sandbox_key' => ['text', [
                'required_when' => ['test_mode' => true],
            ]],
        ],
    ]);

    $model = new TldRegistrar();
    $model->setName('Custom');
    $model->setTestMode(true);

    expect(fn () => $service->registrarValidateConfiguration($model))
        ->toThrow(FOSSBilling\InformationException::class, 'missing required configuration');
});

test('throws exception when getting registrar adapter for non-existing registrar', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('registrarGetConfiguration')
        ->atLeast()->once()
        ->andReturn([]);

    $model = new TldRegistrar();
    $model->setRegistrar('NonExisting');

    expect(fn () => $serviceMock->registrarGetRegistrarAdapter($model))
        ->toThrow(Error::class);
});

test('removes registrar', function (): void {
    $service = new Service();

    $model = new TldRegistrar();
    setEntityId($model, 1);
    $model->setName('ResellerClub');

    $domainRepo = Mockery::mock(DomainRepository::class);
    $domainRepo->shouldReceive('findBy')->with(['registrar' => $model])->andReturn([]);
    $domainRepo->shouldIgnoreMissing();

    $tldRepo = Mockery::mock(TldRepository::class);
    $tldRepo->shouldReceive('findBy')->with(['registrar' => $model])->andReturn([]);
    $tldRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(ServiceDomain::class)->andReturn($domainRepo);
    $emMock->shouldReceive('getRepository')->with(Tld::class)->andReturn($tldRepo);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->registrarRm($model);

    expect($result)->toBeTrue();
});

test('throws exception when removing registrar with domains', function (): void {
    $service = new Service();
    $serviceDomainModel = new ServiceDomain();

    $model = new TldRegistrar();
    setEntityId($model, 1);

    $domainRepo = Mockery::mock(DomainRepository::class);
    $domainRepo->shouldReceive('findBy')->with(['registrar' => $model])->andReturn([$serviceDomainModel]);
    $domainRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(ServiceDomain::class)->andReturn($domainRepo);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    expect(fn (): bool => $service->registrarRm($model))
        ->toThrow(FOSSBilling\InformationException::class, 'Registrar is used by 1 domains');
});

test('converts registrar to api array', function (): void {
    $service = new Service();
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

    $model = new TldRegistrar();
    setEntityId($model, 1);
    $model->setName('ResellerClub');
    $model->setTestMode(true);

    $serviceMock->registrarToApiArray($model);
});

test('creates tld', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('registrarValidateConfiguration')->once();
    $data = [
        'tld' => '.com',
        'tld_registrar_id' => 1,
        'price_registration' => 1,
        'price_renew' => 1,
        'price_transfer' => 1,
        'min_years' => random_int(1, 5),
        'periods' => '5,1,1,3',
        'allow_register' => 1,
        'allow_transfer' => 1,
    ];

    $tldRegistrar = new TldRegistrar();

    $trRepo = Mockery::mock(TldRegistrarRepository::class);
    $trRepo->shouldReceive('find')->with(1)->andReturn($tldRegistrar);
    $trRepo->shouldIgnoreMissing();

    $createdModel = null;

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(TldRegistrar::class)->andReturn($trRepo);
    $emMock->shouldReceive('persist')->once()->andReturnUsing(function ($model) use (&$createdModel): void {
        setEntityId($model, 1);
        $createdModel = $model;
    });
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->tldCreate($data);

    expect($result)->toBeInt();
    expect($result)->toBe(1);
    expect($createdModel->getPeriods())->toBe('1,3,5');
});

test('updates tld', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('registrarValidateConfiguration')->once();
    $data = [
        'tld' => '.com',
        'tld_registrar_id' => 1,
        'price_registration' => 1,
        'price_renew' => 1,
        'price_transfer' => 1,
        'min_years' => random_int(1, 5),
        'periods' => '10,2',
        'allow_register' => true,
        'allow_transfer' => true,
        'active' => true,
    ];

    $tldRegistrar = new TldRegistrar();

    $trRepo = Mockery::mock(TldRegistrarRepository::class);
    $trRepo->shouldReceive('find')->with(1)->andReturn($tldRegistrar);
    $trRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(TldRegistrar::class)->andReturn($trRepo);
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $model = new Tld();
    $model->setTld('.com');

    $result = $service->tldUpdate($model, $data);

    expect($result)->toBeTrue();
    expect($model->getPeriods())->toBe('2,10');
});

test('clears tld periods when an empty value is provided', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('registrarValidateConfiguration')->never();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = new Tld();
    $model->setTld('.com');
    $model->setPeriods('1,2,5');

    $result = $service->tldUpdate($model, ['tld' => '.com', 'periods' => '']);

    expect($result)->toBeTrue();
    expect($model->getPeriods())->toBeNull();
});

test('rejects invalid tld pricing and minimum periods', function (array $data): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('registrarValidateConfiguration')->never();

    $di = container();
    $service->setDi($di);

    expect(fn () => $service->tldCreate(array_replace([
        'tld' => '.com',
        'tld_registrar_id' => 1,
        'price_registration' => 1,
        'price_renew' => 1,
        'price_transfer' => 1,
        'min_years' => 1,
    ], $data)))->toThrow(FOSSBilling\InformationException::class);
})->with([
    'negative registration price' => [['price_registration' => -1]],
    'non-numeric renewal price' => [['price_renew' => 'free']],
    'non-positive minimum period' => [['min_years' => 0]],
    'non-numeric period in periods list' => [['periods' => '1,2,five']],
    'non-positive period in periods list' => [['periods' => '1,0,3']],
]);

test('creates registrar', function (): void {
    $service = new Service();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->registrarCreate('ResellerClub');

    expect($result)->toBeTrue();
});

test('copies registrar', function (): void {
    $service = new Service();

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('persist')->once()->andReturnUsing(function ($model): void {
        setEntityId($model, 1);
    });
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = new TldRegistrar();
    $model->setName('ResellerClub');
    $model->setRegistrar('ResellerClub');
    $model->setTestMode(true);

    $result = $service->registrarCopy($model);

    expect($result)->toBeInt();
    expect($result)->toBe(1);
});

test('updates registrar', function (): void {
    $service = new Service();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $data = [
        'title' => 'ResellerClub',
        'test_mode' => 1,
        'config' => [
            'param1' => 'value1',
        ],
    ];

    $model = new TldRegistrar();
    $model->setRegistrar('Custom');

    $result = $service->registrarUpdate($model, $data);

    expect($result)->toBeTrue();
});

test('preserves registrar configuration fields omitted from an update', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('registrarGetRegistrarAdapterConfig')
        ->once()
        ->andReturn(['form' => []]);
    $service->shouldReceive('registrarValidateConfiguration')->once();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = new TldRegistrar();
    $model->setRegistrar('Custom');
    $model->setConfig(json_encode(['existing' => 'value']));

    $service->registrarUpdate($model, ['config' => ['new' => 'value']]);

    expect(json_decode((string) $model->getConfig(), true))->toBe([
        'existing' => 'value',
        'new' => 'value',
    ]);
});

test('allows registrar metadata updates with an incomplete existing configuration', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('registrarValidateConfiguration')->never();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = new TldRegistrar();
    $model->setName('Old name');
    $model->setRegistrar('Custom');
    $model->setConfig(null);

    expect($service->registrarUpdate($model, ['title' => 'New name']))->toBeTrue();
    expect($model->getName())->toBe('New name');
});

test('updates domain', function (): void {
    $service = new Service();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

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

    $model = new ServiceDomain();
    setEntityId($model, 1);

    $result = $service->updateDomain($model, $data);

    expect($result)->toBeTrue();
});
