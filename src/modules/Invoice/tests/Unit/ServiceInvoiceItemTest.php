<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use Box\Mod\Invoice\Service as InvoiceService;
use Box\Mod\Invoice\ServiceInvoiceItem;
use Box\Mod\Order\Service as OrderService;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function invoiceItemService(?InvoiceItemRepository $repo = null, ?EntityManagerInterface $em = null): ServiceInvoiceItem
{
    $service = new ServiceInvoiceItem();
    $di = container();
    $em ??= Mockery::mock(EntityManagerInterface::class);
    $repo ??= Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);
    $di['em'] = $em;
    $service->setDi($di);

    return $service;
}

test('gets dependency injection container', function (): void {
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $service = invoiceItemService($repo);

    expect($service->getDi())->toBeInstanceOf(Pimple\Container::class)
        ->and($service->getInvoiceItemRepository())->toBe($repo);
});

test('marks item as paid', function (): void {
    $item = createEntity(InvoiceItem::class, []);

    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial();
    $serviceMock->shouldReceive('creditInvoiceItem')
        ->atLeast()->once();
    $serviceMock->shouldReceive('getOrderId')
        ->atLeast()->once()
        ->andReturn(1);

    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('unsetUnpaidInvoice')
        ->with($clientOrder);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('persist')
        ->atLeast()->once();
    $em->shouldReceive('flush')
        ->atLeast()->once();
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($clientOrder);

    $di = container();
    $di['em'] = $em;
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);
    $serviceMock->setDi($di);

    $serviceMock->markAsPaid($item);
});

test('returns true when executing task on already executed item', function (): void {
    $service = new ServiceInvoiceItem();
    $item = createEntity(InvoiceItem::class, ['status' => InvoiceItem::STATUS_EXECUTED]);

    $di = container();
    $em = Mockery::mock(EntityManagerInterface::class);
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);
    $di['em'] = $em;
    $service->setDi($di);

    $result = $service->executeTask($item);
    expect($result)->toBeTrue();
});

test('throws exception when executing task for order type with client order not found', function (): void {
    $service = new ServiceInvoiceItem();
    $item = createEntity(InvoiceItem::class, ['type' => InvoiceItem::TYPE_ORDER]);
    $orderId = 22;

    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial();
    $serviceMock->shouldReceive('getOrderId')
        ->atLeast()->once()
        ->andReturn($orderId);

    $em = Mockery::mock(EntityManagerInterface::class);
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['em'] = $em;
    $di['db'] = $dbMock;
    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->executeTask($item))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Could not activate proforma item. Order %d not found', $orderId));
});

test('executes task for hook call type', function (): void {
    $item = createEntity(InvoiceItem::class, ['type' => InvoiceItem::TYPE_HOOK_CALL, 'rel_id' => '{}']);

    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('markAsExecuted')
        ->atLeast()->once();

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->atLeast()->once();

    $em = Mockery::mock(EntityManagerInterface::class);
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $di = container();
    $di['em'] = $em;
    $di['events_manager'] = $eventManagerMock;
    $serviceMock->setDi($di);

    $serviceMock->executeTask($item);
});

test('executes task for deposit type', function (): void {
    $item = createEntity(InvoiceItem::class, ['type' => InvoiceItem::TYPE_DEPOSIT]);

    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('markAsExecuted')
        ->atLeast()->once();

    $em = Mockery::mock(EntityManagerInterface::class);
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $di = container();
    $di['em'] = $em;
    $serviceMock->setDi($di);

    $serviceMock->executeTask($item);
});

test('executes task for custom type', function (): void {
    $item = createEntity(InvoiceItem::class, ['type' => InvoiceItem::TYPE_CUSTOM]);

    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('markAsExecuted')
        ->atLeast()->once();

    $em = Mockery::mock(EntityManagerInterface::class);
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $di = container();
    $di['em'] = $em;
    $serviceMock->setDi($di);

    $serviceMock->executeTask($item);
});

test('adds new item', function (): void {
    $data = [
        'title' => 'Discount',
        'price' => -10,
    ];
    $newId = 1;

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('persist')
        ->once()
        ->withArgs(function (InvoiceItem $pi) use ($newId): bool {
            $pi->setId($newId);

            return true;
        });
    $em->shouldReceive('flush')->once();
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $service = new ServiceInvoiceItem();
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $result = $service->addNew($invoiceModel, $data);
    expect($result)->toBeInt()->toBe($newId);
});

test('gets total', function (): void {
    $service = invoiceItemService();
    $price = 5;
    $quantity = 3;
    $item = createEntity(InvoiceItem::class, ['price' => $price, 'quantity' => $quantity]);

    $expected = $price * $quantity;

    $result = $service->getTotal($item);
    expect($result)->toBeFloat();
    expect($result)->toEqual($expected);
});

test('gets tax', function (): void {
    $rate = 0.21;
    $price = 12;
    $item = createEntity(InvoiceItem::class, ['invoice_id' => 2, 'taxed' => true, 'price' => $price]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn($rate);

    $service = invoiceItemService();
    $service->getDi()['db'] = $dbMock;

    $result = $service->getTax($item);
    $expected = round($price * $rate / 100, 2);
    expect($result)->toBeFloat();
    expect($result)->toBe($expected);
});

test('updates an item', function (): void {
    $item = createEntity(InvoiceItem::class, ['quantity' => 3]);

    $data = [
        'title' => 'New Engine',
        'price' => 12,
        'taxed' => true,
    ];

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('persist')
        ->atLeast()->once();
    $em->shouldReceive('flush')
        ->atLeast()->once();
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $service = new ServiceInvoiceItem();
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    $service->update($item, $data);

    expect($item->getQuantity())->toBe(3);
});

test('removes an item', function (): void {
    $item = createEntity(InvoiceItem::class, ['id' => 7]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('remove')->with($item)->once();
    $em->shouldReceive('flush')->once();
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $service = new ServiceInvoiceItem();
    $di = container();
    $di['em'] = $em;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->remove($item);
    expect($result)->toBeTrue();
});

test('generates for add funds', function (): void {
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $amount = 11;

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('persist')
        ->atLeast()->once();
    $em->shouldReceive('flush')
        ->atLeast()->once();
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $service = new ServiceInvoiceItem();
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    $service->generateForAddFunds($invoiceModel, $amount);
});

test('credits invoice item', function (): void {
    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial();
    $serviceMock->shouldReceive('getTotalWithTax')
        ->atLeast()->once()
        ->andReturn(11.2);

    $item = createEntity(InvoiceItem::class, []);
    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());
    $clientBalanceModel = new Model_Client();
    $clientBalanceModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $callCount = 0;
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturnUsing(function () use ($invoiceModel, $clientModel, &$callCount) {
            return ++$callCount === 1 ? $invoiceModel : $clientModel;
        });
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($clientBalanceModel);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $em = Mockery::mock(EntityManagerInterface::class);
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $invoiceServiceMock = Mockery::mock(InvoiceService::class);
    $invoiceServiceMock->shouldReceive('addNote')
        ->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $invoiceServiceMock);

    $serviceMock->setDi($di);
    $serviceMock->creditInvoiceItem($item);
});

test('gets total with tax', function (): void {
    $total = 5.0;
    $tax = 0.5;
    $quantity = 3;
    $item = createEntity(InvoiceItem::class, ['quantity' => $quantity]);

    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial();
    $serviceMock->shouldReceive('getTotal')
        ->atLeast()->once()
        ->andReturn($total);
    $serviceMock->shouldReceive('getTax')
        ->atLeast()->once()
        ->andReturn($tax);

    $em = Mockery::mock(EntityManagerInterface::class);
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $di = container();
    $di['em'] = $em;
    $serviceMock->setDi($di);

    $result = $serviceMock->getTotalWithTax($item);
    expect($result)->toBeFloat();
    $expected = $total + $tax * $quantity;
    expect($result)->toBe($expected);
});

test('gets order id', function (): void {
    $service = invoiceItemService();
    $orderId = 2;
    $item = createEntity(InvoiceItem::class, ['type' => InvoiceItem::TYPE_ORDER, 'rel_id' => $orderId]);

    $result = $service->getOrderId($item);
    expect($result)->toBeInt()->toBe($orderId);
});

test('returns zero when invoice item type is not order', function (): void {
    $service = invoiceItemService();
    $item = createEntity(InvoiceItem::class, []);

    $result = $service->getOrderId($item);
    expect($result)->toBeInt()->toBe(0);
});

test('gets all not execute paid items', function (): void {
    $service = invoiceItemService();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn([]);

    $service->getDi()['db'] = $dbMock;

    $result = $service->getAllNotExecutePaidItems();
    expect($result)->toBeArray();
});
