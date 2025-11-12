<?php

namespace Box\Mod\Invoice;

class ServiceInvoiceItemTest extends \BBTestCase
{
    /**
     * @var ServiceInvoiceItem
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new ServiceInvoiceItem();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testmarkAsPaid(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)
            ->onlyMethods(['creditInvoiceItem', 'getOrderId'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('creditInvoiceItem');
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderId')
            ->willReturn(1);

        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)
            ->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('unsetUnpaidInvoice')
            ->with($clientOrder);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($clientOrder);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $serviceMock->setDi($di);

        $serviceMock->markAsPaid($invoiceItemModel);
    }

    public function testexecuteTaskItemAlreadyExecuted(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->status = \Model_InvoiceItem::STATUS_EXECUTED;

        $result = $this->service->executeTask($invoiceItemModel);
        $this->assertTrue($result);
    }

    public function testexecuteTaskTypeOrderClientOrderNotFound(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->type = \Model_InvoiceItem::TYPE_ORDER;
        $orderId = 22;

        $serviceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)
            ->onlyMethods(['getOrderId'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderId')
            ->willReturn($orderId);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Could not activate proforma item. Order %d not found', $orderId));
        $serviceMock->executeTask($invoiceItemModel);
    }

    public function testexecuteTaskTypeHookCall(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->type = \Model_InvoiceItem::TYPE_HOOK_CALL;
        $invoiceItemModel->rel_id = '{}';

        $serviceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)
            ->onlyMethods(['markAsExecuted'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('markAsExecuted');

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventManagerMock;
        $serviceMock->setDi($di);

        $serviceMock->executeTask($invoiceItemModel);
    }

    public function testexecuteTaskTypeDeposit(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->type = \Model_InvoiceItem::TYPE_DEPOSIT;

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $di['db'] = $dbMock;

        $clientServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($clientServiceMock) {
            if ($serviceName == 'Client') {
                return $clientServiceMock;
            }
        });

        $serviceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)
            ->onlyMethods(['markAsExecuted'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('markAsExecuted');
        $serviceMock->setDi($di);

        $serviceMock->executeTask($invoiceItemModel);
    }

    public function testexecuteTaskTypeCustom(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->type = \Model_InvoiceItem::TYPE_CUSTOM;

        $serviceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)
            ->onlyMethods(['markAsExecuted'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('markAsExecuted');

        $serviceMock->executeTask($invoiceItemModel);
    }

    public function testaddNew(): void
    {
        $data = [
            'title' => 'Guacamole',
        ];
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $newId = 1;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($invoiceItemModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newId);

        $periodMock = $this->getMockBuilder('\Box_Period')
            ->disableOriginalConstructor()
            ->getMock();

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $result = $this->service->addNew($invoiceModel, $data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testgetTotal(): void
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

    public function testgetTax(): void
    {
        $rate = 0.21;
        $price = 12;
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->invoice_id = 2;
        $invoiceItemModel->taxed = true;
        $invoiceItemModel->price = $price;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn($rate);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getTax($invoiceItemModel);
        $expected = round($price * $rate / 100, 2);
        $this->assertIsFloat($result);
        $this->assertEquals($expected, $result);
    }

    public function testupdate(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $data = [
            'title' => 'New Engine',
            'price' => 12,
            'taxed' => true,
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $this->service->update($invoiceItemModel, $data);
    }

    public function testremove(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $data = [
            'title' => 'New Engine',
            'price' => 12,
            'taxed' => true,
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $result = $this->service->remove($invoiceItemModel);
        $this->assertTrue($result);
    }

    public function testgenerateForAddFunds(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $amount = 11;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $this->service->generateForAddFunds($invoiceModel, $amount);
    }

    public function testcreditInvoiceItem(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturnOnConsecutiveCalls($invoiceModel, $clientModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($clientBalanceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $invoiceServiceMock = $this->getMockBuilder('\\' . Service::class)->getMock();
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('addNote');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $invoiceServiceMock);

        $serviceMock->setDi($di);
        $serviceMock->creditInvoiceItem($invoiceItemModel);
    }

    public function testgetTotalWithTax(): void
    {
        $total = 5.0;
        $tax = 0.5;
        $quantity = 3;
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->quantity = $quantity;

        $serviceMock = $this->getMockBuilder('\\' . ServiceInvoiceItem::class)
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

    public function testgetOrderId(): void
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

    public function testgetOrderIdInvoiceItemTypeIsNotOrder(): void
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());

        $result = $this->service->getOrderId($invoiceItemModel);
        $this->assertIsInt($result);
        $expected = 0;
        $this->assertEquals($expected, $result);
    }

    public function testgetAllNotExecutePaidItems(): void
    {
        $di = new \Pimple\Container();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([]);

        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getAllNotExecutePaidItems();
        $this->assertIsArray($result);
    }
}
