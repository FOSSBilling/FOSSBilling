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
    $serviceMock->shouldReceive('getTotalWithTax')
        ->atLeast()
        ->once()
        ->andReturn(11.2);
    $serviceMock->shouldReceive('getOrderId')
        ->atLeast()
        ->once()
        ->andReturn(1);

    $invoiceModel = new Model_Invoice();
    $invoiceModel->loadBean(new Tests\Helpers\DummyBean());
    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());
    $clientOrder = new Model_ClientOrder();
    $clientOrder->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()
        ->once()
        ->andReturnUsing(fn (string $model) => $model === 'Client' ? $clientModel : $invoiceModel);
    $dbMock->shouldReceive('load')
        ->atLeast()
        ->once()
        ->andReturn($clientOrder);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('unsetUnpaidInvoice')
        ->with($clientOrder);

    $invoiceServiceMock = Mockery::mock(InvoiceService::class);
    $invoiceServiceMock->shouldReceive('addNote')
        ->atLeast()
        ->once();

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('wrapInTransaction')
        ->atLeast()
        ->once()
        ->andReturnUsing(fn (callable $func): mixed => $func($em));
    $em->shouldReceive('persist')
        ->atLeast()
        ->once();
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $di = container();
    $di['em'] = $em;
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (string $module): Mockery\MockInterface => $module === 'Order' ? $orderServiceMock : $invoiceServiceMock);
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

test('records failure when executing task for order type with client order not found', function (): void {
    $item = createEntity(InvoiceItem::class, ['type' => InvoiceItem::TYPE_ORDER, 'status' => InvoiceItem::STATUS_PENDING_SETUP]);

    $serviceMock = Mockery::mock(ServiceInvoiceItem::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getOrderId')
        ->atLeast()->once()
        ->andReturn(22);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('persist')->once();
    $em->shouldReceive('flush')->once();
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

    $serviceMock->executeTask($item);

    expect($item->getAttempts())->toBe(1)
        ->and($item->getStatus())->toBe(InvoiceItem::STATUS_PENDING_SETUP);
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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturnUsing(fn (string $model) => $model === 'Client' ? $clientModel : $invoiceModel);

    $em = Mockery::mock(EntityManagerInterface::class);
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

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

test('gets all not execute paid items excluding executed and failed', function (): void {
    $service = invoiceItemService();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->withArgs(fn (string $sql, array $bindings): bool => str_contains($sql, 'NOT IN (:status_executed, :status_failed)')
            && $bindings[':status_executed'] === InvoiceItem::STATUS_EXECUTED
            && $bindings[':status_failed'] === InvoiceItem::STATUS_FAILED)
        ->atLeast()
        ->once()
        ->andReturn([]);

    $service->getDi()['db'] = $dbMock;

    $result = $service->getAllNotExecutePaidItems();
    expect($result)->toBeArray();
});

test('increments attempts on hook call failure and keeps pending setup under the cap', function (): void {
    $item = createEntity(InvoiceItem::class, [
        'type' => InvoiceItem::TYPE_HOOK_CALL,
        'rel_id' => '{}',
        'status' => InvoiceItem::STATUS_PENDING_SETUP,
        'attempts' => 0,
    ]);

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->andThrow(new Exception('hook failed'));

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('persist')->once();
    $em->shouldReceive('flush')->once();
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $service = new ServiceInvoiceItem();
    $di = container();
    $di['em'] = $em;
    $di['events_manager'] = $eventManagerMock;
    $service->setDi($di);

    $service->executeTask($item);

    expect($item->getAttempts())->toBe(1)
        ->and($item->getStatus())->toBe(InvoiceItem::STATUS_PENDING_SETUP);
});

test('marks item as failed when hook call failure reaches the attempt cap', function (): void {
    $item = createEntity(InvoiceItem::class, [
        'type' => InvoiceItem::TYPE_HOOK_CALL,
        'rel_id' => '{}',
        'status' => InvoiceItem::STATUS_PENDING_SETUP,
        'attempts' => ServiceInvoiceItem::MAX_TASK_ATTEMPTS - 1,
    ]);

    $eventManagerMock = Mockery::mock('\Box_EventManager');
    $eventManagerMock->shouldReceive('fire')
        ->andThrow(new Exception('hook failed'));

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('persist')->once();
    $em->shouldReceive('flush')->once();
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);

    $service = new ServiceInvoiceItem();
    $di = container();
    $di['em'] = $em;
    $di['events_manager'] = $eventManagerMock;
    $service->setDi($di);

    $service->executeTask($item);

    expect($item->getAttempts())->toBe(ServiceInvoiceItem::MAX_TASK_ATTEMPTS)
        ->and($item->getStatus())->toBe(InvoiceItem::STATUS_FAILED);
});

test('gets failed items via repository', function (): void {
    $failedItem = createEntity(InvoiceItem::class, ['id' => 1, 'status' => InvoiceItem::STATUS_FAILED]);
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $repo->shouldReceive('findFailed')
        ->once()
        ->andReturn([$failedItem]);

    $service = invoiceItemService($repo);

    expect($service->getFailedItems())->toBe([$failedItem]);
});

test('requeues a failed item resetting status and attempts', function (): void {
    $item = createEntity(InvoiceItem::class, [
        'id' => 7,
        'status' => InvoiceItem::STATUS_FAILED,
        'attempts' => ServiceInvoiceItem::MAX_TASK_ATTEMPTS,
    ]);

    $repo = Mockery::mock(InvoiceItemRepository::class);
    $repo->shouldReceive('find')->with(7)->andReturn($item);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(InvoiceItem::class)->andReturn($repo);
    $em->shouldReceive('persist')->once()->with($item);
    $em->shouldReceive('flush')->once();

    $service = new ServiceInvoiceItem();
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    $result = $service->requeueItem(7);

    expect($result)->toBe($item)
        ->and($item->getStatus())->toBe(InvoiceItem::STATUS_PENDING_SETUP)
        ->and($item->getAttempts())->toBe(0);
});

test('requeue throws when item is not found', function (): void {
    $repo = Mockery::mock(InvoiceItemRepository::class);
    $repo->shouldReceive('find')->with(99)->andReturn(null);

    $service = invoiceItemService($repo);

    expect(fn (): InvoiceItem => $service->requeueItem(99))->toThrow(FOSSBilling\InformationException::class);
});

test('requeue throws when item is not in a failed state', function (): void {
    $item = createEntity(InvoiceItem::class, [
        'id' => 7,
        'status' => InvoiceItem::STATUS_EXECUTED,
        'attempts' => 0,
    ]);

    $repo = Mockery::mock(InvoiceItemRepository::class);
    $repo->shouldReceive('find')->with(7)->andReturn($item);

    $service = invoiceItemService($repo);

    expect(fn (): InvoiceItem => $service->requeueItem(7))->toThrow(FOSSBilling\InformationException::class);
});
