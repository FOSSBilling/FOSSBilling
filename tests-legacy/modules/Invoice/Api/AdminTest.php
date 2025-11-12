<?php

namespace Box\Mod\Invoice\Api;

class AdminTest extends \BBTestCase
{
    /**
     * @var Admin
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Admin();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetList(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['SqlString', []]);

        $paginatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = new \Pimple\Container();
        $di['pager'] = $paginatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->get_list([]);
        $this->assertIsArray($result);
    }

    public function testget(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Admin());

        $data['id'] = 1;
        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testmarkAsPaid(): void
    {
        $data = [
            'id' => 1,
            'execute' => true,
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('markAsPaid')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $model->gateway_id = '1';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->mark_as_paid($data);
        $this->assertTrue($result);
    }

    public function testprepare(): void
    {
        $data = [
            'client_id' => 1,
        ];
        $newInvoiceId = 1;

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->id = $newInvoiceId;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('prepareInvoice')
            ->willReturn($invoiceModel);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->prepare($data);
        $this->assertIsInt($result);
        $this->assertEquals($newInvoiceId, $result);
    }

    public function testapprove(): void
    {
        $data = [
            'id' => 1,
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('approveInvoice')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->approve($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testrefund(): void
    {
        $data = [
            'id' => 1,
        ];
        $newNegativeInvoiceId = 2;
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('refundInvoice')
            ->willReturn($newNegativeInvoiceId);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->refund($data);
        $this->assertIsInt($result);
        $this->assertEquals($newNegativeInvoiceId, $result);
    }

    public function testupdate(): void
    {
        $data = [
            'id' => 1,
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateInvoice')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testitemDelete(): void
    {
        $data = [
            'id' => 1,
        ];

        $invoiceItemService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceInvoiceItem::class)->getMock();
        $invoiceItemService->expects($this->atLeastOnce())
            ->method('remove')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_InvoiceItem();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $invoiceItemService);

        $this->api->setDi($di);

        $result = $this->api->item_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdelete(): void
    {
        $data = [
            'id' => 1,
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteInvoiceByAdmin')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testrenewalInvoice(): void
    {
        $data = [
            'id' => 1,
        ];
        $newInvoiceId = 3;
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('renewInvoice')
            ->willReturn($newInvoiceId);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_ClientOrder();
        $model->loadBean(new \DummyBean());
        $model->price = 10;
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->renewal_invoice($data);
        $this->assertIsInt($result);
        $this->assertEquals($newInvoiceId, $result);
    }

    public function testrenewalInvoiceOrderIsFree(): void
    {
        $data = [
            'id' => 1,
        ];

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_ClientOrder();
        $model->loadBean(new \DummyBean());
        $model->id = 1;
        $model->price = 0;
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d is free. No need to generate invoice.', $model->id));
        $this->api->renewal_invoice($data);
    }

    public function testbatchPayWithCredits(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('doBatchPayWithCredits')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->batch_pay_with_credits([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testpayWithCredits(): void
    {
        $data = [
            'id' => 1,
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('payInvoiceWithCredits')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->pay_with_credits($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testbatchGenerate(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('generateInvoicesForExpiringOrders')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->batch_generate();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testbatchActivatePaid(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('doBatchPaidInvoiceActivation')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->batch_activate_paid();
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testbatchSendReminders(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('doBatchRemindersSend')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->batch_send_reminders([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testbatchInvokeDueEvent(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('doBatchInvokeDueEvent')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->batch_invoke_due_event([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testsendReminder(): void
    {
        $data = [
            'id' => 1,
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('sendInvoiceReminder')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->send_reminder($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgetStatuses(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('counter')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->get_statuses([]);
        $this->assertIsArray($result);
    }

    public function testtransactionProcessAll(): void
    {
        $transactionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTransaction::class)->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('processReceivedATransactions')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $transactionService);

        $this->api->setDi($di);
        $result = $this->api->transaction_process_all([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtransactionProcess(): void
    {
        $data = [
            'id' => 1,
        ];

        $transactionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTransaction::class)->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('preProcessTransaction')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Transaction();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventsMock;
        $di['logger'] = new \Box_Log();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $transactionService);

        $this->api->setDi($di);

        $result = $this->api->transaction_process($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtransactionUpdate(): void
    {
        $data = [
            'id' => 1,
        ];

        $transactionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTransaction::class)->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Transaction();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $transactionService);

        $this->api->setDi($di);

        $result = $this->api->transaction_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtransactionCreate(): void
    {
        $newTransactionId = 1;
        $transactionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTransaction::class)->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($newTransactionId);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $transactionService);
        $this->api->setDi($di);

        $result = $this->api->transaction_create([]);
        $this->assertIsInt($result);
        $this->assertEquals($newTransactionId, $result);
    }

    public function testtransactionDelete(): void
    {
        $data = [
            'id' => 1,
        ];

        $transactionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTransaction::class)->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('delete')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Transaction();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $transactionService);

        $this->api->setDi($di);

        $result = $this->api->transaction_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtransactionGet(): void
    {
        $data = [
            'id' => 1,
        ];

        $transactionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTransaction::class)->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Transaction();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $transactionService);

        $this->api->setDi($di);

        $result = $this->api->transaction_get($data);
        $this->assertIsArray($result);
    }

    public function testtransactionGetList(): void
    {
        $transactionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTransaction::class)->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['SqlString', []]);

        $paginatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = new \Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $transactionService);

        $this->api->setDi($di);
        $result = $this->api->transaction_get_list([]);
        $this->assertIsArray($result);
    }

    public function testtransactionGetStatuses(): void
    {
        $transactionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTransaction::class)->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('counter')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $transactionService);

        $this->api->setDi($di);

        $result = $this->api->transaction_get_statuses([]);
        $this->assertIsArray($result);
    }

    public function testtransactionGetStatusesPairs(): void
    {
        $transactionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTransaction::class)->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('getStatusPairs')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $transactionService);

        $this->api->setDi($di);

        $result = $this->api->transaction_get_statuses_pairs([]);
        $this->assertIsArray($result);
    }

    public function testtransactionStatuses(): void
    {
        $transactionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTransaction::class)->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('getStatuses')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $transactionService);

        $this->api->setDi($di);

        $result = $this->api->transaction_statuses([]);
        $this->assertIsArray($result);
    }

    public function testtransactionGatewayStatuses(): void
    {
        $transactionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTransaction::class)->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('getGatewayStatuses')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $transactionService);

        $this->api->setDi($di);

        $result = $this->api->transaction_gateway_statuses([]);
        $this->assertIsArray($result);
    }

    public function testtransactionTypes(): void
    {
        $transactionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTransaction::class)->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('getTypes')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $transactionService);

        $this->api->setDi($di);

        $result = $this->api->transaction_types([]);
        $this->assertIsArray($result);
    }

    public function testgatewayGetList(): void
    {
        $gatewayService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServicePayGateway::class)->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['SqlString', []]);

        $paginatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = new \Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $gatewayService);

        $this->api->setDi($di);
        $result = $this->api->gateway_get_list([]);
        $this->assertIsArray($result);
    }

    public function testgatewayGetPairs(): void
    {
        $gatewayService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServicePayGateway::class)->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('getPairs')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $gatewayService);
        $this->api->setDi($di);

        $result = $this->api->gateway_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testgatewayGetAvailable(): void
    {
        $gatewayService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServicePayGateway::class)->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('getAvailable')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $gatewayService);
        $this->api->setDi($di);

        $result = $this->api->gateway_get_available([]);
        $this->assertIsArray($result);
    }

    public function testgatewayInstall(): void
    {
        $data = [
            'code' => 'PP',
        ];

        $gatewayService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServicePayGateway::class)->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('install')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $gatewayService);
        $this->api->setDi($di);

        $result = $this->api->gateway_install($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgatewayGet(): void
    {
        $data = [
            'id' => 1,
        ];

        $gatewayService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServicePayGateway::class)->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_PayGateway();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $gatewayService);

        $this->api->setDi($di);

        $result = $this->api->gateway_get($data);
        $this->assertIsArray($result);
    }

    public function testgatewayCopy(): void
    {
        $data = [
            'id' => 1,
        ];
        $newGatewayId = 1;
        $gatewayService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServicePayGateway::class)->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('copy')
            ->willReturn($newGatewayId);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_PayGateway();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $gatewayService);

        $this->api->setDi($di);

        $result = $this->api->gateway_copy($data);
        $this->assertIsInt($result);
        $this->assertEquals($newGatewayId, $result);
    }

    public function testgatewayUpdate(): void
    {
        $data = [
            'id' => 1,
        ];

        $gatewayService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServicePayGateway::class)->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_PayGateway();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $gatewayService);

        $this->api->setDi($di);

        $result = $this->api->gateway_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgatewayDelete(): void
    {
        $data = [
            'id' => 1,
        ];

        $gatewayService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServicePayGateway::class)->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('delete')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_PayGateway();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $gatewayService);

        $this->api->setDi($di);

        $result = $this->api->gateway_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function subscription_get_list(): void
    {
        $subscriptionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceSubscription::class)->getMock();
        $subscriptionService->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['SqlString', []]);

        $paginatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $subscriptionService);

        $this->api->setDi($di);
        $result = $this->api->subscription_get_list([]);
        $this->assertIsArray($result);
    }

    public function testsubscriptionCreate(): void
    {
        $data = [
            'client_id' => 1,
            'gateway_id' => 1,
            'currency' => 'EU',
        ];
        $newSubscriptionId = 1;
        $subscriptionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceSubscription::class)->getMock();
        $subscriptionService->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($newSubscriptionId);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_PayGateway();
        $model->loadBean(new \DummyBean());
        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->currency = 'EU';

        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturnOnConsecutiveCalls($client, $model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $subscriptionService);

        $this->api->setDi($di);

        $result = $this->api->subscription_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newSubscriptionId, $result);
    }

    public function testsubscriptionCreateCurrencyMismatch(): void
    {
        $data = [
            'client_id' => 1,
            'gateway_id' => 1,
            'currency' => 'EU',
        ];

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_PayGateway();
        $model->loadBean(new \DummyBean());
        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturnOnConsecutiveCalls($client, $model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Client currency must match subscription currency. Check if clients currency is defined.');
        $this->api->subscription_create($data);
    }

    public function testsubscriptionUpdate(): void
    {
        $data = [
            'id' => 1,
        ];

        $subscriptionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceSubscription::class)->getMock();
        $subscriptionService->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Subscription();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $subscriptionService);

        $this->api->setDi($di);

        $result = $this->api->subscription_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testsubscriptionGet(): void
    {
        $data = [
            'id' => 1,
        ];

        $subscriptionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceSubscription::class)->getMock();
        $subscriptionService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Subscription();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $subscriptionService);

        $this->api->setDi($di);

        $result = $this->api->subscription_get($data);
        $this->assertIsArray($result);
    }

    public function testsubscriptionDelete(): void
    {
        $data = [
            'id' => 1,
        ];

        $subscriptionService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceSubscription::class)->getMock();
        $subscriptionService->expects($this->atLeastOnce())
            ->method('delete')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Subscription();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $subscriptionService);

        $this->api->setDi($di);

        $result = $this->api->subscription_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtaxDelete(): void
    {
        $data = [
            'id' => 1,
        ];

        $taxService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTax::class)->getMock();
        $taxService->expects($this->atLeastOnce())
            ->method('delete')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Tax();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $taxService);

        $this->api->setDi($di);

        $result = $this->api->tax_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtaxCreate(): void
    {
        $data = [
            'id' => 1,
        ];
        $newTaxId = 1;
        $taxService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTax::class)->getMock();
        $taxService->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($newTaxId);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $taxService);

        $this->api->setDi($di);

        $result = $this->api->tax_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newTaxId, $result);
    }

    public function tax_get_list(): void
    {
        $taxService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTax::class)->getMock();
        $taxService->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['SqlString', []]);

        $paginatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $taxService);

        $this->api->setDi($di);

        $result = $this->api->tax_get_list([]);
        $this->assertIsArray($result);
    }

    public function testBatchDelete(): void
    {
        $activityMock = $this->getMockBuilder('\\' . Admin::class)->onlyMethods(['delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->
        method('delete')->
        willReturn(true);
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
        $this->assertTrue($result);
    }

    public function testBatchDeleteSubscription(): void
    {
        $activityMock = $this->getMockBuilder('\\' . Admin::class)->onlyMethods(['subscription_delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->method('subscription_delete')->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_subscription(['ids' => [1, 2, 3]]);
        $this->assertTrue($result);
    }

    public function testBatchDeleteTransaction(): void
    {
        $activityMock = $this->getMockBuilder('\\' . Admin::class)->onlyMethods(['transaction_delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->method('transaction_delete')->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_transaction(['ids' => [1, 2, 3]]);
        $this->assertTrue($result);
    }

    public function testBatchDeleteTax(): void
    {
        $activityMock = $this->getMockBuilder('\\' . Admin::class)->onlyMethods(['tax_delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->method('tax_delete')->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_tax(['ids' => [1, 2, 3]]);
        $this->assertTrue($result);
    }

    public function testgetTax(): void
    {
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $taxService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTax::class)->getMock();
        $taxService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Tax();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $taxService);

        $this->api->setDi($di);
        $this->api->setService($taxService);
        $this->api->setIdentity(new \Model_Admin());

        $data['id'] = 1;
        $result = $this->api->tax_get($data);
        $this->assertIsArray($result);
    }

    public function testupdateTax(): void
    {
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $taxService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTax::class)->getMock();
        $taxService->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Tax();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $taxService);

        $this->api->setDi($di);
        $this->api->setService($taxService);
        $this->api->setIdentity(new \Model_Admin());

        $data['id'] = 1;
        $result = $this->api->tax_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}
