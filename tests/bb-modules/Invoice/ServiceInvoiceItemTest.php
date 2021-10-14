<?php


namespace Box\Mod\Invoice;


class ServiceInvoiceItemTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Invoice\ServiceInvoiceItem
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Invoice\ServiceInvoiceItem();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testmarkAsPaid()
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')
            ->setMethods(array('creditInvoiceItem', 'getOrderId'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('creditInvoiceItem');
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderId')
            ->willReturn(1);

        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')
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

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock){ return $orderServiceMock;});
        $serviceMock->setDi($di);

        $serviceMock->markAsPaid($invoiceItemModel);
    }

    public function testexecuteTaskItemAlreadyExecuted()
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceItemModel->status = \Model_InvoiceItem::STATUS_EXECUTED;

        $result = $this->service->executeTask($invoiceItemModel);
        $this->assertTrue($result);
    }

    public function testexecuteTaskTypeOrderClientOrderNotFound()
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceItemModel->type = \Model_InvoiceItem::TYPE_ORDER;
        $orderId                = 22;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')
            ->setMethods(array('getOrderId'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrderId')
            ->will($this->returnValue($orderId));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue(null));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf('Could not activate proforma item. Order %d not found', $orderId));
        $serviceMock->executeTask($invoiceItemModel);
    }

    public function testexecuteTaskTypeHookCall()
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceItemModel->type   = \Model_InvoiceItem::TYPE_HOOK_CALL;
        $invoiceItemModel->rel_id = '{}';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')
            ->setMethods(array('markAsExecuted'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('markAsExecuted');

        $eventManagerMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManagerMock->expects($this->atLeastOnce())
            ->method('fire');

        $di                   = new \Box_Di();
        $di['events_manager'] = $eventManagerMock;
        $serviceMock->setDi($di);

        $serviceMock->executeTask($invoiceItemModel);
    }

    public function testexecuteTaskTypeDeposit()
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceItemModel->type = \Model_InvoiceItem::TYPE_DEPOSIT;

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $di = new \Box_Di();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->withConsecutive(array('Invoice'), array('Client'))
            ->willReturnOnConsecutiveCalls($invoiceModel, $clientModel);
        $di['db'] = $dbMock;

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $clientServiceMock->expects($this->atLeastOnce())
            ->method('addFunds')
            ->with($clientModel);
        $di['mod_service'] = $di->protect(function ($serviceName) use ($clientServiceMock){
            if ($serviceName == 'Client'){
                return $clientServiceMock;
            }
        });

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')
            ->setMethods(array('markAsExecuted', 'getTotal'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('markAsExecuted');
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTotal')
            ->willReturn('1.00');
        $serviceMock->setDi($di);

        $serviceMock->executeTask($invoiceItemModel);
    }

    public function testexecuteTaskTypeCustom()
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceItemModel->type = \Model_InvoiceItem::TYPE_CUSTOM;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')
            ->setMethods(array('markAsExecuted'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('markAsExecuted');

        $serviceMock->executeTask($invoiceItemModel);
    }

    public function testaddNew()
    {
        $data             = array(
            'title' => 'Guacamole',

        );
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());
        $newId = 1;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($invoiceItemModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($newId));

        $periodMock = $this->getMockBuilder('\Box_Period')
            ->disableOriginalConstructor()
            ->getMock();

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $result = $this->service->addNew($invoiceModel, $data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testgetTotal()
    {
        $price            = 5;
        $quantity         = 3;
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceItemModel->price    = $price;
        $invoiceItemModel->quantity = $quantity;

        $expected = $price * $quantity;

        $result = $this->service->getTotal($invoiceItemModel);
        $this->assertIsFloat($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetTax()
    {
        $rate             = 0.21;
        $price            = 12;
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceItemModel->invoice_id = 2;
        $invoiceItemModel->taxed      = true;
        $invoiceItemModel->price      = $price;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue($rate));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result   = $this->service->getTax($invoiceItemModel);
        $expected = round(($price * $rate / 100), 2);
        $this->assertIsFloat($result);
        $this->assertEquals($expected, $result);
    }

    public function testupdate()
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());

        $data = array(
            'title' => 'New Engine',
            'price' => 12,
            'taxed' => true,
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $this->service->update($invoiceItemModel, $data);
    }

    public function testremove()
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());

        $data = array(
            'title' => 'New Engine',
            'price' => 12,
            'taxed' => true,
        );

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $result = $this->service->remove($invoiceItemModel);
        $this->assertTrue($result);
    }

    public function testgenerateForAddFunds()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $amount = 11;


        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($invoiceModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di       = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $this->service->generateForAddFunds($invoiceModel, $amount);
    }

    public function testcreditInvoiceItem()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')
            ->setMethods(array('getTotalWithTax'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTotalWithTax')
            ->will($this->returnValue(11.2));

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientBalanceModel = new \Model_Client();
        $clientBalanceModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->onConsecutiveCalls($invoiceModel, $clientModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($clientBalanceModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $invoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('addNote');

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($invoiceServiceMock) {
            return $invoiceServiceMock;
        });

        $serviceMock->setDi($di);
        $serviceMock->creditInvoiceItem($invoiceItemModel);
    }

    public function testgetTotalWithTax()
    {
        $total    = 5;
        $tax      = 0.5;
        $quantity = 3;
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceItemModel->quantity = $quantity;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')
            ->setMethods(array('getTotal', 'getTax'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTotal')
            ->will($this->returnValue($total));
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTax')
            ->will($this->returnValue($tax));

        $result = $serviceMock->getTotalWithTax($invoiceItemModel);
        $this->assertIsFloat($result);
        $expected = $total + $tax * $quantity;
        $this->assertEquals($expected, $result);
    }

    public function testgetOrderId()
    {
        $orderId          = 2;
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceItemModel->rel_id = $orderId;
        $invoiceItemModel->type   = \Model_InvoiceItem::TYPE_ORDER;

        $result = $this->service->getOrderId($invoiceItemModel);
        $this->assertIsInt($result);
        $this->assertEquals($orderId, $result);
    }

    public function testgetOrderIdInvoiceItemTypeIsNotOrder()
    {
        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());

        $result = $this->service->getOrderId($invoiceItemModel);
        $this->assertIsInt($result);
        $expected = 0;
        $this->assertEquals($expected, $result);
    }

    public function testgetAllNotExecutePaidItems()
    {
        $di = new \Box_Di();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn(array());

        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getAllNotExecutePaidItems();
        $this->assertIsArray($result);
    }
}
 