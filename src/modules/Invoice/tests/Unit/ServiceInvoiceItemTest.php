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
use Box\Mod\Invoice\Service as InvoiceService;
use Box\Mod\Invoice\ServiceInvoiceItem;
use Box\Mod\Order\Service as OrderService;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('gets dependency injection container', function (): void {
    $service = new ServiceInvoiceItem();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('marks item as paid', function (): void {
    $service = new ServiceInvoiceItem();
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class);

    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('creditInvoiceItem')
        ->atLeast()->once();
    $serviceMock->shouldReceive('getOrderId')
        ->atLeast()->once()
        ->andReturn(1);

    $orderEntity = new Box\Mod\Order\Entity\Order();

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('unsetUnpaidInvoice')
        ->with($orderEntity);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $orderRepoMock->shouldReceive('find')
        ->with(1)
        ->andReturn($orderEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Order\Entity\Order::class)
        ->andReturn($orderRepoMock);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);
    $serviceMock->setDi($di);

    $serviceMock->markAsPaid($invoiceItemModel);
});

test('returns true when executing task on already executed item', function (): void {
    $service = new ServiceInvoiceItem();
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, ['status' => \Box\Mod\Invoice\Entity\InvoiceItem::STATUS_EXECUTED]);

    $result = $service->executeTask($invoiceItemModel);
    expect($result)->toBeTrue();
});

test('throws exception when executing task for order type with client order not found', function (): void {
    $service = new ServiceInvoiceItem();
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, ['type' => \Box\Mod\Invoice\Entity\InvoiceItem::TYPE_ORDER]);
    $orderId = 22;

    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderId')
        ->atLeast()->once()
        ->andReturn($orderId);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $orderRepoMock->shouldReceive('find')
        ->with($orderId)
        ->andReturn(null);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Order\Entity\Order::class)
        ->andReturn($orderRepoMock);

    $di = container();
    $di['em'] = $emMock;
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->executeTask($invoiceItemModel))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Could not activate proforma item. Order %d not found', $orderId));
});

test('executes task for hook call type', function (): void {
    $service = new ServiceInvoiceItem();
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, [
        'type' => \Box\Mod\Invoice\Entity\InvoiceItem::TYPE_HOOK_CALL,
        'rel_id' => '{}',
    ]);

    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('markAsExecuted')
        ->atLeast()->once();

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventManagerMock;
    $serviceMock->setDi($di);

    $serviceMock->executeTask($invoiceItemModel);
});

test('executes task for deposit type', function (): void {
    $service = new ServiceInvoiceItem();
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, ['type' => \Box\Mod\Invoice\Entity\InvoiceItem::TYPE_DEPOSIT]);

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class);

    $di = container();
    $dbMock = Mockery::mock('\Box_Database');
    $di['db'] = $dbMock;

    $clientServiceMock = Mockery::mock(ClientService::class);
    $di['mod_service'] = $di->protect(function ($serviceName) use ($clientServiceMock) {
        if ($serviceName == 'Client') {
            return $clientServiceMock;
        }
    });

    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('markAsExecuted')
        ->atLeast()->once();
    $serviceMock->setDi($di);

    $serviceMock->executeTask($invoiceItemModel);
});

test('executes task for custom type', function (): void {
    $service = new ServiceInvoiceItem();
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, ['type' => \Box\Mod\Invoice\Entity\InvoiceItem::TYPE_CUSTOM]);

    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('markAsExecuted')
        ->atLeast()->once();

    $serviceMock->executeTask($invoiceItemModel);
});

test('adds new item', function (): void {
    $service = new ServiceInvoiceItem();
    $data = [
        'title' => 'Discount',
        'price' => -10,
    ];

    $di = container();
    $di['em'] = Tests\Helpers\entityManagerWithIds($di);

    $service->setDi($di);

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);
    $result = $service->addNew($invoiceModel, $data);
    expect($result)->toBeInt()->toBe(1);
});

test('gets total', function (): void {
    $service = new ServiceInvoiceItem();
    $price = 5;
    $quantity = 3;
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, [
        'price' => $price,
        'quantity' => $quantity,
    ]);

    $expected = $price * $quantity;

    $result = $service->getTotal($invoiceItemModel);
    expect($result)->toBeFloat();
    expect($result)->toEqual($expected);
});

test('gets tax', function (): void {
    $service = new ServiceInvoiceItem();
    $rate = 0.21;
    $price = 12;
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, [
        'invoice_id' => 2,
        'taxed' => true,
        'price' => $price,
    ]);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('fetchOne')
        ->atLeast()->once()
        ->andReturn($rate);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $result = $service->getTax($invoiceItemModel);
    $expected = round($price * $rate / 100, 2);
    expect($result)->toBeFloat();
    expect($result)->toBe($expected);
});

test('updates an item', function (): void {
    $service = new ServiceInvoiceItem();
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, ['quantity' => 3]);

    $data = [
        'title' => 'New Engine',
        'price' => 12,
        'taxed' => true,
    ];

    $di = container();
    $service->setDi($di);

    $service->update($invoiceItemModel, $data);

    expect($invoiceItemModel->quantity)->toBe(3);
});

test('removes an item', function (): void {
    $service = new ServiceInvoiceItem();
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->remove($invoiceItemModel);
    expect($result)->toBeTrue();
});

test('generates for add funds', function (): void {
    $service = new ServiceInvoiceItem();
    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);
    $amount = 11;

    $di = container();
    $service->setDi($di);

    $service->generateForAddFunds($invoiceModel, $amount);
    expect(true)->toBeTrue();
});

test('credits invoice item', function (): void {
    $service = new ServiceInvoiceItem();
    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getTotalWithTax')
        ->atLeast()->once()
        ->andReturn(11.2);

    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, ['invoice_id' => 1]);

    $clientEntity = new Box\Mod\Client\Entity\Client();
    $clientIdProp = new ReflectionProperty($clientEntity, 'id');
    $clientIdProp->setValue($clientEntity, 1);

    $invoiceEntity = new Box\Mod\Invoice\Entity\Invoice();
    $invoiceIdProp = new ReflectionProperty($invoiceEntity, 'id');
    $invoiceIdProp->setValue($invoiceEntity, 1);
    $clientIdInvProp = new ReflectionProperty($invoiceEntity, 'clientId');
    $clientIdInvProp->setValue($invoiceEntity, 1);

    $invoiceServiceMock = Mockery::mock(InvoiceService::class);
    $invoiceServiceMock->shouldReceive('addNote')
        ->atLeast()->once();

    $clientRepo = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepo->shouldReceive('find')->with(1)->andReturn($clientEntity);

    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('find')->with(1)->andReturn($invoiceEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\Invoice::class)->andReturn($invoiceRepo);
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->andReturn($clientRepo);

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $invoiceServiceMock);

    $serviceMock->setDi($di);
    $serviceMock->creditInvoiceItem($invoiceItemModel);
});

test('gets total with tax', function (): void {
    $service = new ServiceInvoiceItem();
    $total = 5.0;
    $tax = 0.5;
    $quantity = 3;
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, ['quantity' => $quantity]);

    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getTotal')
        ->atLeast()->once()
        ->andReturn($total);
    $serviceMock->shouldReceive('getTax')
        ->atLeast()->once()
        ->andReturn($tax);

    $result = $serviceMock->getTotalWithTax($invoiceItemModel);
    expect($result)->toBeFloat();
    $expected = $total + $tax * $quantity;
    expect($result)->toBe($expected);
});

test('gets order id', function (): void {
    $service = new ServiceInvoiceItem();
    $orderId = 2;
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class, [
        'rel_id' => $orderId,
        'type' => \Box\Mod\Invoice\Entity\InvoiceItem::TYPE_ORDER,
    ]);

    $result = $service->getOrderId($invoiceItemModel);
    expect($result)->toBeInt()->toBe($orderId);
});

test('returns zero when invoice item type is not order', function (): void {
    $service = new ServiceInvoiceItem();
    $invoiceItemModel = createEntity(\Box\Mod\Invoice\Entity\InvoiceItem::class);

    $result = $service->getOrderId($invoiceItemModel);
    expect($result)->toBeInt()->toBe(0);
});

test('gets all not execute paid items', function (): void {
    $service = new ServiceInvoiceItem();
    $di = container();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn([]);

    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $result = $service->getAllNotExecutePaidItems();
    expect($result)->toBeArray();
});
