<?php

declare(strict_types=1);

namespace Box\Mod\Invoice;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceInvoiceItemTest extends \BBTestCase
{
    protected ?ServiceInvoiceItem $service;

    public function setUp(): void
    {
        $this->service = new ServiceInvoiceItem();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testMarkAsPaid(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(ServiceInvoiceItem::class)
            ->onlyMethods(['creditInvoiceItem', 'getOrderId'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('creditInvoiceItem');
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderId')
            ->willReturn(1);

        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('unsetUnpaidInvoice')
            ->with($clientOrder);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($clientOrder);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $serviceMock->setDi($di);

        $serviceMock->markAsPaid($invoiceItemModel);
    }

    public function testExecuteTaskItemAlreadyExecuted(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->status = \Model_InvoiceItem::STATUS_EXECUTED;

        $result = $this->service->executeTask($invoiceItemModel);
        $this->assertTrue($result);
    }

    public function testExecuteTaskTypeOrderClientOrderNotFound(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->type = \Model_InvoiceItem::TYPE_ORDER;
        $orderId = 22;

        $serviceMock = $this->getMockBuilder(ServiceInvoiceItem::class)
            ->onlyMethods(['getOrderId'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderId')
            ->willReturn($orderId);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Could not activate proforma item. Order %d not found', $orderId));
        $serviceMock->executeTask($invoiceItemModel);
    }

    public function testExecuteTaskTypeDeposit(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->type = \Model_InvoiceItem::TYPE_DEPOSIT;

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $di = $this->getDi();
        $dbMock = $this->createMock('\Box_Database');
        $di['db'] = $dbMock;

        $clientServiceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $di['mod_service'] = $di->protect(function ($serviceName) use ($clientServiceMock) {
            if ($serviceName == 'Client') {
                return $clientServiceMock;
            }
        });

        $serviceMock = $this->getMockBuilder(ServiceInvoiceItem::class)
            ->onlyMethods(['markAsExecuted'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('markAsExecuted');
        $serviceMock->setDi($di);

        $serviceMock->executeTask($invoiceItemModel);
    }

    public function testExecuteTaskTypeCustom(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->type = \Model_InvoiceItem::TYPE_CUSTOM;

        $serviceMock = $this->getMockBuilder(ServiceInvoiceItem::class)
            ->onlyMethods(['markAsExecuted'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('markAsExecuted');

        $serviceMock->executeTask($invoiceItemModel);
    }

    public function testAddNew(): void
    {
        $data = [
            'title' => 'Guacamole',
        ];
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $newId = 1;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($invoiceItemModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newId);

        $periodMock = $this->getMockBuilder('\Box_Period')
            ->disableOriginalConstructor()
            ->getMock();

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $result = $this->service->addNew($invoiceModel, $data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testGetTotal(): void
    {
        $price = 5;
        $quantity = 3;
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->price = $price;
        $invoiceItemModel->quantity = $quantity;

        $expected = $price * $quantity;

        $result = $this->service->getTotal($invoiceItemModel);
        $this->assertIsFloat($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetTax(): void
    {
        $rate = 0.21;
        $price = 12;
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->invoice_id = 2;
        $invoiceItemModel->taxed = true;
        $invoiceItemModel->price = $price;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn($rate);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getTax($invoiceItemModel);
        $expected = round($price * $rate / 100, 2);
        $this->assertIsFloat($result);
        $this->assertEquals($expected, $result);
    }

    public function testUpdate(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $data = [
            'title' => 'New Engine',
            'price' => 12,
            'taxed' => true,
        ];

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->service->update($invoiceItemModel, $data);
    }

    public function testRemove(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $data = [
            'title' => 'New Engine',
            'price' => 12,
            'taxed' => true,
        ];

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $result = $this->service->remove($invoiceItemModel);
        $this->assertTrue($result);
    }

    public function testGenerateForAddFunds(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $amount = 11;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $this->service->generateForAddFunds($invoiceModel, $amount);
    }

    public function testCreditInvoiceItem(): void
    {
        $serviceMock = $this->getMockBuilder(ServiceInvoiceItem::class)
            ->onlyMethods(['getTotalWithTax'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTotalWithTax')
            ->willReturn(11.2);

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientBalanceModel = new \Model_Client();
        $clientBalanceModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturnOnConsecutiveCalls($invoiceModel, $clientModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($clientBalanceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $invoiceServiceMock = $this->createMock(Service::class);
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('addNote');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $invoiceServiceMock);

        $serviceMock->setDi($di);
        $serviceMock->creditInvoiceItem($invoiceItemModel);
    }

    public function testGetTotalWithTax(): void
    {
        $total = 5.0;
        $tax = 0.5;
        $quantity = 3;
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->quantity = $quantity;

        $serviceMock = $this->getMockBuilder(ServiceInvoiceItem::class)
            ->onlyMethods(['getTotal', 'getTax'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTotal')
            ->willReturn($total);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTax')
            ->willReturn($tax);

        $result = $serviceMock->getTotalWithTax($invoiceItemModel);
        $this->assertIsFloat($result);
        $expected = $total + $tax * $quantity;
        $this->assertEquals($expected, $result);
    }

    public function testGetOrderId(): void
    {
        $orderId = 2;
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->rel_id = $orderId;
        $invoiceItemModel->type = \Model_InvoiceItem::TYPE_ORDER;

        $result = $this->service->getOrderId($invoiceItemModel);
        $this->assertIsInt($result);
        $this->assertEquals($orderId, $result);
    }

    public function testGetOrderIdInvoiceItemTypeIsNotOrder(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $result = $this->service->getOrderId($invoiceItemModel);
        $this->assertIsInt($result);
        $expected = 0;
        $this->assertEquals($expected, $result);
    }

    public function testGetAllNotExecutePaidItems(): void
    {
        $di = $this->getDi();

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([]);

        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getAllNotExecutePaidItems();
        $this->assertIsArray($result);
    }
}
