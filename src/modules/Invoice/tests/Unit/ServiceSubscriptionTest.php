<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Service as ClientService;
use Box\Mod\Invoice\ServicePayGateway;
use Box\Mod\Invoice\ServiceSubscription;

use function Tests\Helpers\container;

test('gets dependency injection container', function (): void {
    $service = new ServiceSubscription();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('creates a subscription', function (): void {
    $service = new ServiceSubscription();
    $subscriptionModel = new Model_Subscription();
    $subscriptionModel->loadBean(new Tests\Helpers\DummyBean());
    $newId = 10;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($subscriptionModel);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($newId);

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventsMock;

    $service->setDi($di);

    $data = [
        'client_id' => 1,
        'gateway_id' => 2,
    ];

    $result = $service->create(new Model_Client(), new Model_PayGateway(), $data);
    expect($result)->toBeInt()->toBe($newId);
});

test('updates a subscription', function (): void {
    $service = new ServiceSubscription();
    $subscriptionModel = new Model_Subscription();
    $subscriptionModel->loadBean(new Tests\Helpers\DummyBean());
    $data = [
        'status' => '',
        'sid' => '',
        'period' => '',
        'amount' => '',
        'currency' => '',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->update($subscriptionModel, $data);
    expect($result)->toBeTrue();
});

test('converts to api array', function (): void {
    $service = new ServiceSubscription();
    $subscriptionModel = new Model_Subscription();
    $subscriptionModel->loadBean(new Tests\Helpers\DummyBean());

    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());

    $gatewayModel = new Model_PayGateway();
    $gatewayModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $callCount = 0;
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturnUsing(function () use ($clientModel, $gatewayModel, &$callCount) {
            return ++$callCount === 1 ? $clientModel : $gatewayModel;
        });

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $payGatewayService = Mockery::mock(ServicePayGateway::class);
    $payGatewayService->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($clientServiceMock, $payGatewayService) {
        if ($serviceName == 'Client') {
            return $clientServiceMock;
        }
        if ($sub == 'PayGateway') {
            return $payGatewayService;
        }
    });
    $di['db'] = $dbMock;
    $service->setDi($di);

    $expected = [
        'id' => '',
        'sid' => '',
        'period' => '',
        'amount' => '',
        'currency' => '',
        'status' => '',
        'created_at' => '',
        'updated_at' => '',
        'client' => [],
        'gateway' => [],
    ];

    $result = $service->toApiArray($subscriptionModel);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('client');
    expect($result)->toHaveKey('gateway');
    expect($result['client'])->toBeArray();
    expect($result['gateway'])->toBeArray();
});

test('deletes a subscription', function (): void {
    $service = new ServiceSubscription();
    $subscriptionModel = new Model_Subscription();
    $subscriptionModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once();

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventsMock;
    $service->setDi($di);

    $result = $service->delete($subscriptionModel);
    expect($result)->toBeTrue();
});

test('gets search query with various parameters', function (array $data, string $expectedSqlPart, array $expectedParams) {
    $service = new ServiceSubscription();
    $di = container();

    $service->setDi($di);
    $result = $service->getSearchQuery($data);

    expect($result)->toBeArray();
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect($result[1])->toBe($expectedParams);
    expect(str_contains($result[0], $expectedSqlPart))->toBeTrue();
})->with([
    [
        [], 'FROM subscription', [],
    ],
    [
        ['status' => 'active'], 'AND status = :status', [':status' => 'active'],
    ],
    [
        ['invoice_id' => '1'], 'AND invoice_id = :invoice_id', ['invoice_id' => '1'],
    ],
    [
        ['gateway_id' => '2'], 'AND gateway_id = :gateway_id', [':gateway_id' => '2'],
    ],
    [
        ['client_id' => '3'], 'AND client_id  = :client_id', [':client_id' => '3'],
    ],
    [
        ['currency' => 'EUR'], 'AND currency =  :currency', [':currency' => 'EUR'],
    ],
    [
        ['date_from' => '1234567'], 'AND UNIX_TIMESTAMP(created_at) >= :date_from', [':date_from' => '1234567'],
    ],
    [
        ['date_to' => '1234567'], 'AND UNIX_TIMESTAMP(created_at) <= :date_to', [':date_to' => '1234567'],
    ],
    [
        ['id' => '10'], 'AND id = :id', [':id' => '10'],
    ],
    [
        ['sid' => '10'], 'AND sid = :sid', [':sid' => '10'],
    ],
]);

test('returns false when invoice is not subscribable', function (): void {
    $service = new ServiceSubscription();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(['']);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $invoice_id = 2;
    $result = $service->isSubscribable($invoice_id);
    expect($result)->toBeBool()->toBeFalse();
});

test('checks if invoice is subscribable', function (): void {
    $service = new ServiceSubscription();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(null);

    $getAllResults = [
        0 => ['period' => '1W'],
    ];
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn($getAllResults);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $invoice_id = 2;
    $result = $service->isSubscribable($invoice_id);
    expect($result)->toBeBool()->toBeTrue();
});

test('gets subscription period', function (): void {
    $service = new ServiceSubscription();
    $serviceMock = Mockery::mock(ServiceSubscription::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('isSubscribable')
        ->atLeast()->once()
        ->andReturn(true);

    $period = '1W';
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn($period);

    $di = container();
    $di['db'] = $dbMock;
    $serviceMock->setDi($di);

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());

    $result = $serviceMock->getSubscriptionPeriod($invoiceModel);
    expect($result)->toBeString()->toBe($period);
});

test('unsubscribes', function (): void {
    $service = new ServiceSubscription();
    $subscriptionModel = new Model_Subscription();
    $subscriptionModel->loadBean(new Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $service->unsubscribe($subscriptionModel);
});
