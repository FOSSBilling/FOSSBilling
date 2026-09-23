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
use Box\Mod\Servicedomain\Api\Client;
use Box\Mod\Servicedomain\Entity\ServiceDomain;
use Box\Mod\Servicedomain\Event\AfterClientChangeNameserversEvent;
use Box\Mod\Servicedomain\Event\BeforeClientChangeNameserversEvent;
use Box\Mod\Servicedomain\Service;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\setEntityId;

test('updates nameservers', function (): void {
    $model = (new ServiceDomain())->setClientId(17);
    setEntityId($model, 42);

    $clientApiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods());
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $timeline = [];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateNameservers')
        ->once()
        ->with($model, [
            'ns1' => 'ns1.example.test',
            'ns2' => 'ns2.example.test',
            'ns3' => null,
            'ns4' => null,
            'token' => 'private-token',
            'config' => ['registrar_password' => 'secret'],
        ])
        ->andReturnUsing(function () use (&$timeline): bool {
            expect($timeline)->toBe(['before']);
            $timeline[] = 'service';

            return true;
        });

    $clientApiMock->setService($serviceMock);

    $eventDispatcher = new EventDispatcher();
    $eventDispatcher->addListener(BeforeClientChangeNameserversEvent::class, static function (BeforeClientChangeNameserversEvent $event) use (&$timeline): void {
        expect(get_object_vars($event))->toBe([
            'domainId' => 42,
            'clientId' => 17,
            'ns1' => 'ns1.example.test',
            'ns2' => 'ns2.example.test',
            'ns3' => null,
            'ns4' => null,
        ]);
        $timeline[] = 'before';
    });
    $eventDispatcher->addListener(AfterClientChangeNameserversEvent::class, static function (AfterClientChangeNameserversEvent $event) use (&$timeline): void {
        expect(get_object_vars($event))->toBe([
            'domainId' => 42,
            'clientId' => 17,
            'ns1' => 'ns1.example.test',
            'ns2' => 'ns2.example.test',
            'ns3' => null,
            'ns4' => null,
        ]);
        $timeline[] = 'after';
    });

    $di = container();
    $di['event_dispatcher'] = $eventDispatcher;
    $clientApiMock->setDi($di);

    $data = [
        'ns1' => 'ns1.example.test',
        'ns2' => 'ns2.example.test',
        'ns3' => null,
        'ns4' => null,
        'token' => 'private-token',
        'config' => ['registrar_password' => 'secret'],
    ];
    $result = $clientApiMock->update_nameservers($data);

    expect($result)->toBeTrue();
    expect($timeline)->toBe(['before', 'service', 'after']);
});

test('does not dispatch after nameserver event when registrar update fails', function (): void {
    $model = new ServiceDomain();
    $clientApiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods());
    $clientApiMock->shouldReceive('_getService')->once()->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateNameservers')
        ->once()
        ->andThrow(new RuntimeException('Registrar update failed'));
    $clientApiMock->setService($serviceMock);

    $timeline = [];
    $eventDispatcher = new EventDispatcher();
    $eventDispatcher->addListener(BeforeClientChangeNameserversEvent::class, static function () use (&$timeline): void {
        $timeline[] = 'before';
    });
    $eventDispatcher->addListener(AfterClientChangeNameserversEvent::class, static function () use (&$timeline): void {
        $timeline[] = 'after';
    });

    $di = container();
    $di['event_dispatcher'] = $eventDispatcher;
    $clientApiMock->setDi($di);

    expect(fn () => $clientApiMock->update_nameservers(['ns1' => 'ns1.example.test', 'ns2' => 'ns2.example.test']))
        ->toThrow(RuntimeException::class, 'Registrar update failed');
    expect($timeline)->toBe(['before']);
});

test('updates contacts', function (): void {
    $clientApi = apiEndpoint(new Client());
    $api = apiEndpoint(new Client());
    $model = new ServiceDomain();

    $clientApiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods());
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateContacts')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApiMock->setService($serviceMock);

    $data = [];
    $result = $clientApiMock->update_contacts($data);

    expect($result)->toBeTrue();
});

test('enables privacy protection', function (): void {
    $clientApi = apiEndpoint(new Client());
    $api = apiEndpoint(new Client());
    $model = new ServiceDomain();

    $clientApiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods());
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('enablePrivacyProtection')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApiMock->setService($serviceMock);

    $data = [];
    $result = $clientApiMock->enable_privacy_protection($data);

    expect($result)->toBeTrue();
});

test('disables privacy protection', function (): void {
    $clientApi = apiEndpoint(new Client());
    $api = apiEndpoint(new Client());
    $model = new ServiceDomain();

    $clientApiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods());
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('disablePrivacyProtection')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApiMock->setService($serviceMock);

    $data = [];
    $result = $clientApiMock->disable_privacy_protection($data);

    expect($result)->toBeTrue();
});

test('synchronizes domain with registrar', function (): void {
    $clientApi = apiEndpoint(new Client());
    $api = apiEndpoint(new Client());
    $model = new ServiceDomain();

    $clientApiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods());
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('synchronizeDomain')
        ->atLeast()->once()
        ->with($model);

    $clientApiMock->setService($serviceMock);

    $data = [];
    $result = $clientApiMock->sync($data);

    expect($result)->toBeTrue();
});

test('gets transfer code', function (): void {
    $clientApi = apiEndpoint(new Client());
    $api = apiEndpoint(new Client());
    $model = new ServiceDomain();

    $clientApiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods());
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getTransferCode')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApiMock->setService($serviceMock);

    $data = [];
    $result = $clientApiMock->get_transfer_code($data);

    expect($result)->toBeTrue();
});

test('locks domain', function (): void {
    $clientApi = apiEndpoint(new Client());
    $api = apiEndpoint(new Client());
    $model = new ServiceDomain();

    $clientApiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods());
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('lock')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApiMock->setService($serviceMock);

    $data = [];
    $result = $clientApiMock->lock($data);

    expect($result)->toBeTrue();
});

test('unlocks domain', function (): void {
    $clientApi = apiEndpoint(new Client());
    $api = apiEndpoint(new Client());
    $model = new ServiceDomain();

    $clientApiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods());
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('unlock')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApiMock->setService($serviceMock);

    $data = [];
    $result = $clientApiMock->unlock($data);

    expect($result)->toBeTrue();
});

test('gets service', function (): void {
    $clientApi = apiEndpoint(new Client());
    $api = apiEndpoint(new Client());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('lock')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApi->setService($serviceMock);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $order = createEntity(Order::class, ['status' => Order::STATUS_ACTIVE]);
    $orderServiceMock->shouldReceive('findForClientById')
        ->atLeast()->once()
        ->andReturn($order);
    $orderServiceMock->shouldReceive('assertOrderUsable')
        ->atLeast()->once();
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(new ServiceDomain());

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);
    $clientApi->setDi($di);

    $clientApi->setIdentity(new Box\Mod\Client\Entity\Client());

    $data = [
        'order_id' => 1,
    ];
    $result = $clientApi->lock($data);

    expect($result)->toBeTrue();
});

test('throws exception when getting service without order_id', function (): void {
    $clientApi = apiEndpoint(new Client());
    $api = apiEndpoint(new Client());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('lock')
        ->never();

    $clientApi->setService($serviceMock);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('findForClientById')
        ->never();
    $orderServiceMock->shouldReceive('getOrderService')
        ->never();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);
    $clientApi->setDi($di);

    $clientApi->setIdentity(new Box\Mod\Client\Entity\Client());

    $data = [];

    expect(fn () => $clientApi->lock($data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('throws exception when getting service order not found', function (): void {
    $clientApi = apiEndpoint(new Client());
    $api = apiEndpoint(new Client());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('lock')
        ->never();

    $clientApi->setService($serviceMock);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('findForClientById')
        ->atLeast()->once()
        ->andReturn(null);
    $orderServiceMock->shouldReceive('getOrderService')
        ->never();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);
    $clientApi->setDi($di);

    $clientApi->setIdentity(new Box\Mod\Client\Entity\Client());

    $data = [
        'order_id' => 1,
    ];

    expect(fn () => $clientApi->lock($data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('throws exception when getting service order not activated', function (): void {
    $clientApi = apiEndpoint(new Client());
    $api = apiEndpoint(new Client());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('lock')
        ->never();

    $clientApi->setService($serviceMock);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('findForClientById')
        ->atLeast()->once()
        ->andReturn(createEntity(Order::class));
    $orderServiceMock->shouldReceive('assertOrderUsable')
        ->atLeast()->once();
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);
    $clientApi->setDi($di);

    $clientApi->setIdentity(new Box\Mod\Client\Entity\Client());

    $data = [
        'order_id' => 1,
    ];

    expect(fn () => $clientApi->lock($data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('throws exception when getting service for expired order', function (): void {
    $clientApi = apiEndpoint(new Client());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('lock')->never();
    $clientApi->setService($serviceMock);

    $expiredOrder = createEntity(Order::class, [
        'status' => Order::STATUS_ACTIVE,
        'expires_at' => date('Y-m-d H:i:s', time() - 3600),
    ]);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('findForClientById')
        ->atLeast()->once()
        ->andReturn($expiredOrder);
    $orderServiceMock->shouldReceive('assertOrderUsable')
        ->once()
        ->with($expiredOrder)
        ->andThrow(new FOSSBilling\InformationException('Subscription expired'));
    $orderServiceMock->shouldReceive('getOrderService')->never();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);
    $clientApi->setDi($di);

    $clientApi->setIdentity(new Box\Mod\Client\Entity\Client());

    $data = [
        'order_id' => 1,
    ];

    expect(fn () => $clientApi->lock($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Subscription expired');
});
