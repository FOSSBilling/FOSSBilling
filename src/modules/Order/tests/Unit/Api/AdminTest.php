<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Order\Api\Admin;
use Box\Mod\Order\Repository\OrderRepository;
use Box\Mod\Order\Service;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('gets an order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $apiMock->setService($serviceMock);

    $data = ['id' => 1];
    $result = $apiMock->get($data);

    expect($result)->toBeArray();
});

test('gets list of orders', function (): void {
    $api = apiEndpoint(new Admin());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getSearchQuery')->atLeast()->once()->andReturn(['query', []]);
    $serviceMock->shouldReceive('getBatchForApi')->atLeast()->once()->andReturn([]);

    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getPaginatedResultSet')->atLeast()->once()->andReturn(['list' => []]);

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')->atLeast()->once()->andReturn(['show_addons' => 0]);

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->get_list([]);

    expect($result)->toBeArray();
});

test('creates an order', function (): void {
    $api = apiEndpoint(new Admin());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('createOrder')->atLeast()->once()->andReturn(1);

    $productModel = new Box\Mod\Product\Entity\Product();
    $productServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $productServiceMock->shouldReceive('findProductById')->once()->with(1)->andReturn($productModel);

    $clientEntity = new Box\Mod\Client\Entity\Client();

    $clientRepoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepoMock->shouldReceive('find')->with(1)->once()->andReturn($clientEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Client\Entity\Client::class)
        ->once()
        ->andReturn($clientRepoMock);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (string $name): Mockery\MockInterface => match (strtolower($name)) {
        'product' => $productServiceMock,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $api->setDi($di);
    $api->setService($serviceMock);

    $data = ['client_id' => 1, 'product_id' => 1];
    $result = $api->create($data);

    expect($result)->toBeInt();
    expect($result)->toBe(1);
});

test('rejects order create with mark invoice paid when invoice permission is missing', function (): void {
    $api = apiEndpoint(new Admin());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('createOrder')->never();

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffServiceMock->shouldReceive('hasPermission')->byDefault()->andReturn(true);
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')
        ->byDefault()
        ->andReturn(true);
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')
        ->once()
        ->with('invoice', null, null, Mockery::any())
        ->andThrow(new FOSSBilling\InformationException('Denied', [], 403));

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $name): Mockery\MockInterface => match (strtolower($name)) {
        'staff' => $staffServiceMock,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $api->setDi($di);
    $api->setService($serviceMock);

    expect(fn () => $api->create([
        'client_id' => 1,
        'product_id' => 1,
        'invoice_option' => 'issue-invoice',
        'mark_invoice_paid' => 1,
        'gateway_id' => 5,
    ]))->toThrow(FOSSBilling\InformationException::class);
});

test('uses invoice service to validate mark paid request when permission granted', function (): void {
    $api = apiEndpoint(new Admin());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('createOrder')
        ->once()
        ->with(
            Mockery::type(Box\Mod\Client\Entity\Client::class),
            Mockery::type(Box\Mod\Product\Entity\Product::class),
            Mockery::on(fn (array $data): bool => $data['gateway_id'] === 5
                && $data['invoice_option'] === 'issue-invoice'
                && $data['mark_invoice_paid'] === true)
        )
        ->andReturn(55);

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffServiceMock->shouldReceive('hasPermission')->byDefault()->andReturn(true);
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')
        ->byDefault()
        ->andReturn(true);
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')
        ->once()
        ->with('invoice', null, null, Mockery::any());

    $payGateway = new Model_PayGateway();
    $payGateway->loadBean(new Tests\Helpers\DummyBean());

    $invoiceServiceMock = Mockery::mock(Box\Mod\Invoice\Service::class);
    $invoiceServiceMock->shouldReceive('validateAdminMarkAsPaidRequest')
        ->once()
        ->with(Mockery::on(fn (array $data): bool => $data['gateway_id'] === 5
            && $data['invoice_option'] === 'issue-invoice'
            && $data['mark_invoice_paid'] === true))
        ->andReturn($payGateway);

    $productModel = new Box\Mod\Product\Entity\Product();

    $productServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $productServiceMock->shouldReceive('findProductById')->once()->with(1)->andReturn($productModel);

    $clientEntity = new Box\Mod\Client\Entity\Client();

    $clientRepoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepoMock->shouldReceive('find')->with(1)->once()->andReturn($clientEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Client\Entity\Client::class)
        ->once()
        ->andReturn($clientRepoMock);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (string $name): Mockery\MockInterface => match (strtolower($name)) {
        'staff' => $staffServiceMock,
        'invoice' => $invoiceServiceMock,
        'product' => $productServiceMock,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->create([
        'client_id' => 1,
        'product_id' => 1,
        'invoice_option' => 'issue-invoice',
        'mark_invoice_paid' => 1,
        'gateway_id' => 5,
    ]);

    expect($result)->toBe(55);
});

test('rejects invalid invoice payment payload before order creation', function (): void {
    $api = apiEndpoint(new Admin());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('createOrder')->never();

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffServiceMock->shouldReceive('hasPermission')->byDefault()->andReturn(true);
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')
        ->byDefault()
        ->andReturn(true);
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')
        ->once()
        ->with('invoice', null, null, Mockery::any());

    $invoiceServiceMock = Mockery::mock(Box\Mod\Invoice\Service::class);
    $invoiceServiceMock->shouldReceive('validateAdminMarkAsPaidRequest')
        ->once()
        ->andThrow(new FOSSBilling\InformationException('Transaction ID is required when using the Custom payment gateway.'));

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $name): Mockery\MockInterface => match (strtolower($name)) {
        'staff' => $staffServiceMock,
        'invoice' => $invoiceServiceMock,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $api->setDi($di);
    $api->setService($serviceMock);

    expect(fn () => $api->create([
        'client_id' => 1,
        'product_id' => 1,
        'invoice_option' => 'issue-invoice',
        'mark_invoice_paid' => 1,
        'gateway_id' => 5,
    ]))->toThrow(FOSSBilling\InformationException::class, 'Transaction ID is required when using the Custom payment gateway.');
});

test('updates an order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateOrder')->atLeast()->once()->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = ['client_id' => 1, 'product_id' => 1];
    $result = $apiMock->update($data);

    expect($result)->toBeTrue();
});

test('activates an order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('activateOrder')->atLeast()->once()->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = ['client_id' => 1, 'product_id' => 1];
    $result = $apiMock->activate($data);

    expect($result)->toBeTrue();
});

test('renews an order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('renewOrder')->atLeast()->once()->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = [];
    $result = $apiMock->renew($data);

    expect($result)->toBeTrue();
});

test('renewing a pending setup order delegates to activate', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);
    $order->status = Box\Mod\Order\Entity\Order::STATUS_PENDING_SETUP;

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);
    $apiMock->shouldReceive('activate')->atLeast()->once()->andReturn(true);

    $data = [];
    $result = $apiMock->renew($data);

    expect($result)->toBeTrue();
});

test('suspends an order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('suspendFromOrder')->atLeast()->once()->andReturn(true);

    $di = container();
    $apiMock->setDi($di);
    $apiMock->setService($serviceMock);

    $data = [];
    $result = $apiMock->suspend($data);

    expect($result)->toBeTrue();
});

test('unsuspends a suspended order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);
    $order->status = Box\Mod\Order\Entity\Order::STATUS_SUSPENDED;

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('unsuspendFromOrder')->atLeast()->once()->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = [];
    $result = $apiMock->unsuspend($data);

    expect($result)->toBeTrue();
});

test('throws exception when unsuspending non-suspended order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);
    $order->status = Box\Mod\Order\Entity\Order::STATUS_ACTIVE;

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('unsuspendFromOrder')->never()->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = [];

    expect(fn () => $apiMock->unsuspend($data))->toThrow(FOSSBilling\Exception::class);
});

test('cancels an order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('scheduleCancellationFromOrder')
        ->once()
        ->with($order, 'Customer request')
        ->andReturn(true);

    $di = container();
    $apiMock->setDi($di);
    $apiMock->setService($serviceMock);

    $data = ['reason' => 'Customer request', 'cancel_at_period_end' => '1'];
    $result = $apiMock->cancel($data);

    expect($result)->toBeTrue();
});

test('cancels an order immediately when cancellation timing is omitted', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $api = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $api->shouldAllowMockingProtectedMethods();
    $api->shouldReceive('_getOrder')->once()->andReturn($order);

    $service = Mockery::mock(Service::class);
    $service->shouldReceive('cancelFromOrder')
        ->once()
        ->with($order, 'Customer request', false)
        ->andReturn(true);

    $api->setService($service);

    expect($api->cancel(['reason' => 'Customer request']))->toBeTrue();
});

test('checks whether an order supports cancellation at period end', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $api = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $api->shouldAllowMockingProtectedMethods();
    $api->shouldReceive('_getOrder')->once()->andReturn($order);

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $legacyOrder = new Model_ClientOrder();
    $legacyOrder->loadBean(new Tests\Helpers\DummyBean(['id' => 1]));
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('getLegacyOrder')->once()->with($order)->andReturn($legacyOrder);
    $subscriptionService->shouldReceive('canCancelAtPeriodEndForOrder')->once()->with($legacyOrder)->andReturn(true);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('checkPermissionsAndThrowException')->once()->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $module) => strtolower($module) === 'staff' ? $staffService : $subscriptionService);
    $api->setDi($di);
    $api->setService($service);

    expect($api->can_cancel_at_period_end(['id' => 1]))->toBeTrue();
});

test('uncancels a canceled order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);
    $order->status = Box\Mod\Order\Entity\Order::STATUS_CANCELED;

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('uncancelFromOrder')->atLeast()->once()->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = [];
    $result = $apiMock->uncancel($data);

    expect($result)->toBeTrue();
});

test('throws exception when uncanceling non-canceled order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);
    $order->status = Box\Mod\Order\Entity\Order::STATUS_ACTIVE;

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('uncancelFromOrder')->never()->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = [];

    expect(fn () => $apiMock->uncancel($data))->toThrow(FOSSBilling\Exception::class);
});

test('deletes an order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('deleteFromOrder')->atLeast()->once()->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = [];
    $result = $apiMock->delete($data);

    expect($result)->toBeTrue();
});

test('deletes an order with addons', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('deleteFromOrder')->atLeast()->once()->andReturn(true);
    $serviceMock->shouldReceive('getOrderAddonsList')->atLeast()->once()->andReturn([createEntity(Box\Mod\Order\Entity\Order::class)]);

    $apiMock->setService($serviceMock);

    $data = ['delete_addons' => true];
    $result = $apiMock->delete($data);

    expect($result)->toBeTrue();
});

test('batch suspends expired orders', function (): void {
    $api = apiEndpoint(new Admin());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('batchSuspendExpired')->atLeast()->once()->andReturn(true);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->batch_suspend_expired($data);

    expect($result)->toBeTrue();
});

test('batch cancels suspended orders', function (): void {
    $api = apiEndpoint(new Admin());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('batchCancelSuspended')->atLeast()->once()->andReturn(true);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->batch_cancel_suspended($data);

    expect($result)->toBeTrue();
});

test('updates order config', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateOrderConfig')->atLeast()->once()->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = ['config' => []];
    $result = $apiMock->update_config($data);

    expect($result)->toBeTrue();
});

test('throws exception when config is not set', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateOrderConfig')->never()->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = [];

    expect(fn () => $apiMock->update_config($data))->toThrow(FOSSBilling\Exception::class);
});

test('gets order service', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getOrderServiceData')->atLeast()->once()->andReturn([]);

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean(['id' => 1]));

    $apiMock->setService($serviceMock);
    $apiMock->setIdentity($admin);

    $data = ['id' => 1];
    $result = $apiMock->service($data);

    expect($result)->toBeArray();
});

test('gets order status history', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getOrderStatusSearchQuery')->atLeast()->once()->andReturn(['query', []]);

    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getPaginatedResultSet')->atLeast()->once()->andReturn([]);

    $di = container();
    $di['pager'] = $paginatorMock;

    $apiMock->setDi($di);
    $apiMock->setService($serviceMock);

    $result = $apiMock->status_history_get_list([]);

    expect($result)->toBeArray();
});

test('adds order status history', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('orderStatusAdd')->atLeast()->once()->andReturn(true);

    $di = container();
    $apiMock->setDi($di);
    $apiMock->setService($serviceMock);

    $data = ['status' => Box\Mod\Order\Entity\Order::STATUS_ACTIVE];
    $result = $apiMock->status_history_add($data);

    expect($result)->toBeTrue();
});

test('deletes order status history', function (): void {
    $api = apiEndpoint(new Admin());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('orderStatusRm')->atLeast()->once()->andReturn(true);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $data = ['id' => 1];
    $result = $api->status_history_delete($data);

    expect($result)->toBeTrue();
});

test('gets statuses', function (): void {
    $api = apiEndpoint(new Admin());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('counter')->atLeast()->once()->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->get_statuses();

    expect($result)->toBeArray();
});

test('gets invoice options', function (): void {
    $api = apiEndpoint(new Admin());
    $result = $api->get_invoice_options([]);

    expect($result)->toBeArray();
});

test('gets status pairs', function (): void {
    $api = apiEndpoint(new Admin());
    $result = $api->get_status_pairs([]);

    expect($result)->toBeArray();
});

test('gets addons for an order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getOrderAddonsList')->atLeast()->once()->andReturn([createEntity(Box\Mod\Order\Entity\Order::class)]);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $apiMock->setService($serviceMock);

    $data = ['status' => Box\Mod\Order\Entity\Order::STATUS_ACTIVE];
    $result = $apiMock->addons($data);

    expect($result)->toBeArray();
    expect($result[0])->toBeArray();
});

test('gets an order via getOrder', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class, ['id' => 1]);
    $orderRepository = Mockery::mock(OrderRepository::class);
    $orderRepository->shouldReceive('find')->once()->with(1)->andReturn($order);

    $api = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $api->shouldAllowMockingProtectedMethods();
    $api->shouldReceive('getOrderRepository')->once()->andReturn($orderRepository);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $api->setService($serviceMock);

    $data = ['id' => 1];
    $api->get($data);
});

test('batch deletes orders', function (): void {
    $api = apiEndpoint(new Admin());

    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('delete')->atLeast()->once()->andReturn(true);

    $di = container();
    $apiMock->setDi($di);

    $result = $apiMock->batch_delete(['ids' => [1, 2, 3]]);

    expect($result)->toBeTrue();
});
