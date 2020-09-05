<?php


namespace Box\Mod\Invoice\Api;


class AdminTest extends \BBTestCase {
    /**
    * @var \Box\Mod\Invoice\Api\Admin
    */
    protected $api = null;

    public function setup(): void
    {
        $this->api = new \Box\Mod\Invoice\Api\Admin();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testget_list()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->will($this->returnValue(array('SqlString', array())));

        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getAdvancedResultSet')
            ->will($this->returnValue(array('list' => array())));

        $di = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->get_list(array());
        $this->assertIsArray($result);
    }

    public function testget()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Admin());

        $data['id'] = 1;
        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testmark_as_paid()
    {
        $data = array(
            'id' => 1,
            'execute' => true,
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('markAsPaid')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->mark_as_paid($data);
        $this->assertTrue($result);
    }

    public function testprepare()
    {
        $data = array(
            'client_id' => 1,
        );
        $newInvoiceId = 1;

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceModel->id = $newInvoiceId;

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('prepareInvoice')
            ->will($this->returnValue($invoiceModel));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Client();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->prepare($data);
        $this->assertIsInt($result);
        $this->assertEquals($newInvoiceId, $result);
    }

    public function testapprove()
    {
        $data = array(
            'id' => 1,
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('approveInvoice')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->approve($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testrefund()
    {
        $data = array(
            'id' => 1,
        );
        $newNegativeInvoiceId = 2;
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('refundInvoice')
            ->will($this->returnValue($newNegativeInvoiceId));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->refund($data);
        $this->assertIsInt($result);
        $this->assertEquals($newNegativeInvoiceId, $result);
    }

    public function testupdate()
    {
        $data = array(
            'id' => 1,
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateInvoice')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testitem_delete()
    {
        $data = array(
            'id' => 1,
        );

        $invoiceItemService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')->getMock();
        $invoiceItemService->expects($this->atLeastOnce())
            ->method('remove')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_InvoiceItem();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($invoiceItemService) {return $invoiceItemService;});

        $this->api->setDi($di);

        $result = $this->api->item_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdelete()
    {
        $data = array(
            'id' => 1,
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteInvoiceByAdmin')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testrenewal_invoice()
    {
        $data = array(
            'id' => 1,
        );
        $newInvoiceId = 3;
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('renewInvoice')
            ->will($this->returnValue($newInvoiceId));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_ClientOrder();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->price = 10;
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->renewal_invoice($data);
        $this->assertIsInt($result);
        $this->assertEquals($newInvoiceId, $result);
    }
    public function testrenewal_invoiceOrderIsFree()
    {
        $data = array(
            'id' => 1,
        );

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_ClientOrder();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = 1;
        $model->price = 0;
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d is free. No need to generate invoice.', $model->id));
        $this->api->renewal_invoice($data);
    }

    public function testbatch_pay_with_credits()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('doBatchPayWithCredits')
            ->will($this->returnValue(true));

        $this->api->setService($serviceMock);

        $result = $this->api->batch_pay_with_credits(array());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testpay_with_credits()
    {
        $data = array(
            'id' => 1,
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('payInvoiceWithCredits')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->pay_with_credits($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testbatch_generate()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('generateInvoicesForExpiringOrders')
            ->will($this->returnValue(true));

        $this->api->setService($serviceMock);

        $result = $this->api->batch_generate(array());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testbatch_activate_paid()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('doBatchPaidInvoiceActivation')
            ->will($this->returnValue(true));

        $this->api->setService($serviceMock);

        $result = $this->api->batch_activate_paid(array());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testbatch_send_reminders()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('doBatchRemindersSend')
            ->will($this->returnValue(true));

        $this->api->setService($serviceMock);

        $result = $this->api->batch_send_reminders(array());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testbatch_invoke_due_event()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('doBatchInvokeDueEvent')
            ->will($this->returnValue(true));

        $this->api->setService($serviceMock);

        $result = $this->api->batch_invoke_due_event(array());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }


    public function testsend_reminder()
    {
        $data = array(
            'id' => 1,
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('sendInvoiceReminder')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->send_reminder($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testget_statuses()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('counter')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $result = $this->api->get_statuses(array());
        $this->assertIsArray($result);
    }

    public function testtransaction_process_all()
    {
        $transactionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTransaction')->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('proccessReceivedATransactions')
            ->will($this->returnValue(true));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($transactionService) {return $transactionService;});

        $this->api->setDi($di);
        $result = $this->api->transaction_process_all(array());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtransaction_process()
    {
        $data = array(
            'id' => 1,
        );

        $transactionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTransaction')->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('preProcessTransaction')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Transaction();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $eventsMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventsMock;
        $di['logger'] = new \Box_Log();
        $di['mod_service'] = $di->protect(function () use($transactionService) {return $transactionService;});

        $this->api->setDi($di);

        $result = $this->api->transaction_process($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtransaction_update()
    {
        $data = array(
            'id' => 1,
        );

        $transactionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTransaction')->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('update')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Transaction();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($transactionService) {return $transactionService;});

        $this->api->setDi($di);

        $result = $this->api->transaction_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtransaction_create()
    {
        $newTransactionId = 1;
        $transactionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTransaction')->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('create')
            ->will($this->returnValue($newTransactionId));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($transactionService) {return $transactionService;});
        $this->api->setDi($di);

        $result = $this->api->transaction_create(array());
        $this->assertIsInt($result);
        $this->assertEquals($newTransactionId, $result);
    }

    public function testtransaction_delete()
    {
        $data = array(
            'id' => 1,
        );

        $transactionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTransaction')->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('delete')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Transaction();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($transactionService) {return $transactionService;});

        $this->api->setDi($di);

        $result = $this->api->transaction_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtransaction_get()
    {
        $data = array(
            'id' => 1,
        );

        $transactionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTransaction')->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Transaction();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($transactionService) {return $transactionService;});

        $this->api->setDi($di);

        $result = $this->api->transaction_get($data);
        $this->assertIsArray($result);
    }

    public function testtransaction_get_list()
    {
        $transactionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTransaction')->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->will($this->returnValue(array('SqlString', array())));

        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue(array('list' => array())));

        $di = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(function () use($transactionService) {return $transactionService;});
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->api->setDi($di);
        $result = $this->api->transaction_get_list(array());
        $this->assertIsArray($result);
    }

    public function testtransaction_get_statuses()
    {
        $transactionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTransaction')->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('counter')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($transactionService) {return $transactionService;});

        $this->api->setDi($di);

        $result = $this->api->transaction_get_statuses(array());
        $this->assertIsArray($result);
    }

    public function testtransaction_get_statuses_pairs()
    {
        $transactionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTransaction')->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('getStatusPairs')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($transactionService) {return $transactionService;});

        $this->api->setDi($di);

        $result = $this->api->transaction_get_statuses_pairs(array());
        $this->assertIsArray($result);
    }

    public function testtransaction_statuses()
    {
        $transactionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTransaction')->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('getStatuses')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($transactionService) {return $transactionService;});

        $this->api->setDi($di);

        $result = $this->api->transaction_statuses(array());
        $this->assertIsArray($result);
    }

    public function testtransaction_gateway_statuses()
    {
        $transactionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTransaction')->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('getGatewayStatuses')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($transactionService) {return $transactionService;});

        $this->api->setDi($di);

        $result = $this->api->transaction_gateway_statuses(array());
        $this->assertIsArray($result);
    }

    public function testtransaction_types()
    {
        $transactionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTransaction')->getMock();
        $transactionService->expects($this->atLeastOnce())
            ->method('getTypes')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($transactionService) {return $transactionService;});

        $this->api->setDi($di);

        $result = $this->api->transaction_types(array());
        $this->assertIsArray($result);
    }

    public function testgateway_get_list()
    {
        $gatewayService = $this->getMockBuilder('\Box\Mod\Invoice\ServicePayGateway')->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->will($this->returnValue(array('SqlString', array())));

        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue(array('list' => array())));

        $di = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(function () use($gatewayService) {return $gatewayService;});
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->api->setDi($di);
        $result = $this->api->gateway_get_list(array());
        $this->assertIsArray($result);
    }

    public function testgateway_get_pairs()
    {
        $gatewayService = $this->getMockBuilder('\Box\Mod\Invoice\ServicePayGateway')->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('getPairs')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($gatewayService) {return $gatewayService;});
        $this->api->setDi($di);

        $result = $this->api->gateway_get_pairs(array());
        $this->assertIsArray($result);
    }

    public function testgateway_get_available()
    {
        $gatewayService = $this->getMockBuilder('\Box\Mod\Invoice\ServicePayGateway')->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('getAvailable')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($gatewayService) {return $gatewayService;});
        $this->api->setDi($di);

        $result = $this->api->gateway_get_available(array());
        $this->assertIsArray($result);
    }

    public function testgateway_install()
    {
        $data = array(
            'code' => 'PP',
        );

        $gatewayService = $this->getMockBuilder('\Box\Mod\Invoice\ServicePayGateway')->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('install')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['mod_service'] = $di->protect(function () use($gatewayService) {return $gatewayService;});
        $this->api->setDi($di);

        $result = $this->api->gateway_install($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgateway_get()
    {
        $data = array(
            'id' => 1,
        );

        $gatewayService = $this->getMockBuilder('\Box\Mod\Invoice\ServicePayGateway')->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_PayGateway();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($gatewayService) {return $gatewayService;});

        $this->api->setDi($di);

        $result = $this->api->gateway_get($data);
        $this->assertIsArray($result);
    }

    public function testgateway_copy()
    {
        $data = array(
            'id' => 1,
        );
        $newGatewayId = 1;
        $gatewayService = $this->getMockBuilder('\Box\Mod\Invoice\ServicePayGateway')->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('copy')
            ->will($this->returnValue($newGatewayId));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_PayGateway();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($gatewayService) {return $gatewayService;});

        $this->api->setDi($di);

        $result = $this->api->gateway_copy($data);
        $this->assertIsInt($result);
        $this->assertEquals($newGatewayId, $result);
    }

    public function testgateway_update()
    {
        $data = array(
            'id' => 1,
        );

        $gatewayService = $this->getMockBuilder('\Box\Mod\Invoice\ServicePayGateway')->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('update')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_PayGateway();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($gatewayService) {return $gatewayService;});

        $this->api->setDi($di);

        $result = $this->api->gateway_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgateway_delete()
    {
        $data = array(
            'id' => 1,
        );

        $gatewayService = $this->getMockBuilder('\Box\Mod\Invoice\ServicePayGateway')->getMock();
        $gatewayService->expects($this->atLeastOnce())
            ->method('delete')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_PayGateway();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($gatewayService) {return $gatewayService;});

        $this->api->setDi($di);

        $result = $this->api->gateway_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function subscription_get_list()
    {
        $subscriptionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceSubscription')->getMock();
        $subscriptionService->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->will($this->returnValue(array('SqlString', array())));

        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(function () use($subscriptionService) {return $subscriptionService;});

        $this->api->setDi($di);
        $result = $this->api->subscription_get_list(array());
        $this->assertIsArray($result);
    }

    public function testsubscription_create()
    {
        $data = array(
            'client_id' => 1,
            'gateway_id' => 1,
            'currency' => 'EU',

        );
        $newSubscriptionId = 1;
        $subscriptionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceSubscription')->getMock();
        $subscriptionService->expects($this->atLeastOnce())
            ->method('create')
            ->will($this->returnValue($newSubscriptionId));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_PayGateway();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->currency = 'EU';

        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->onConsecutiveCalls($client, $model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($subscriptionService) {return $subscriptionService;});

        $this->api->setDi($di);

        $result = $this->api->subscription_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newSubscriptionId, $result);
    }

    public function testsubscription_createCurrencyMismatch()
    {
        $data = array(
            'client_id' => 1,
            'gateway_id' => 1,
            'currency' => 'EU',

        );

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_PayGateway();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->onConsecutiveCalls($client, $model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Client currency must match subscription currency. Check if clients currency is defined.');
        $this->api->subscription_create($data);
    }

    public function testsubscription_update()
    {
        $data = array(
            'id' => 1,
        );

        $subscriptionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceSubscription')->getMock();
        $subscriptionService->expects($this->atLeastOnce())
            ->method('update')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Subscription();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($subscriptionService) {return $subscriptionService;});

        $this->api->setDi($di);

        $result = $this->api->subscription_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testsubscription_get()
    {
        $data = array(
            'id' => 1,
        );

        $subscriptionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceSubscription')->getMock();
        $subscriptionService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

       $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Subscription();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($subscriptionService) {return $subscriptionService;});

        $this->api->setDi($di);

        $result = $this->api->subscription_get($data);
        $this->assertIsArray($result);
    }

    public function testsubscription_delete()
    {
        $data = array(
            'id' => 1,
        );

        $subscriptionService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceSubscription')->getMock();
        $subscriptionService->expects($this->atLeastOnce())
            ->method('delete')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Subscription();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($subscriptionService) {return $subscriptionService;});

        $this->api->setDi($di);

        $result = $this->api->subscription_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtax_delete()
    {
        $data = array(
            'id' => 1,
        );

        $taxService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTax')->getMock();
        $taxService->expects($this->atLeastOnce())
            ->method('delete')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $model = new \Model_Tax();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function () use($taxService) {return $taxService;});

        $this->api->setDi($di);

        $result = $this->api->tax_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testtax_create()
    {
        $data = array(
            'id' => 1,
        );
        $newTaxId = 1;
        $taxService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTax')->getMock();
        $taxService->expects($this->atLeastOnce())
            ->method('create')
            ->will($this->returnValue($newTaxId));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');


        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['mod_service'] = $di->protect(function () use($taxService) {return $taxService;});

        $this->api->setDi($di);

        $result = $this->api->tax_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newTaxId, $result);
    }

    public function tax_get_list()
    {
        $taxService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTax')->getMock();
        $taxService->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->will($this->returnValue(array('SqlString', array())));

        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(function () use($taxService) {return $taxService;});

        $this->api->setDi($di);

        $result = $this->api->tax_get_list(array());
        $this->assertIsArray($result);
    }


    public function testtax_setup_eu()
    {
        $taxService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTax')->getMock();
        $taxService->expects($this->atLeastOnce())
            ->method('setupEUTaxes')
            ->will($this->returnValue(true));


        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($taxService) {return $taxService;});

        $this->api->setDi($di);

        $result = $this->api->tax_setup_eu(array());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testBatch_delete()
    {
        $activityMock = $this->getMockBuilder('\Box\Mod\Invoice\Api\Admin')->setMethods(array('delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->
        method('delete')->
        will($this->returnValue(true));
        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(array('ids' => array(1, 2, 3)));
        $this->assertEquals(true, $result);
    }

    public function testBatch_delete_subscription()
    {
        $activityMock = $this->getMockBuilder('\Box\Mod\Invoice\Api\Admin')->setMethods(array('subscription_delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->method('subscription_delete')->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_subscription(array('ids' => array(1, 2, 3)));
        $this->assertEquals(true, $result);
    }

    public function testBatch_delete_transaction()
    {
        $activityMock = $this->getMockBuilder('\Box\Mod\Invoice\Api\Admin')->setMethods(array('transaction_delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->method('transaction_delete')->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_transaction(array('ids' => array(1, 2, 3)));
        $this->assertEquals(true, $result);
    }


    public function testBatch_delete_tax()
    {
        $activityMock = $this->getMockBuilder('\Box\Mod\Invoice\Api\Admin')->setMethods(array('tax_delete'))->getMock();
        $activityMock->expects($this->atLeastOnce())->method('tax_delete')->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di              = new \Box_Di();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_tax(array('ids' => array(1, 2, 3)));
        $this->assertEquals(true, $result);
    }

    public function testgetTax()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $taxService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTax')->getMock();
        $taxService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model  = new \Model_Tax();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di                = new \Box_Di();
        $di['validator']   = $validatorMock;
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($taxService) { return $taxService; });

        $this->api->setDi($di);
        $this->api->setService($taxService);
        $this->api->setIdentity(new \Model_Admin());

        $data['id'] = 1;
        $result     = $this->api->tax_get($data);
        $this->assertIsArray($result);
    }


    public function testupdateTax()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $taxService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTax')->getMock();
        $taxService->expects($this->atLeastOnce())
            ->method('update')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model  = new \Model_Tax();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di                = new \Box_Di();
        $di['validator']   = $validatorMock;
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($taxService) { return $taxService; });

        $this->api->setDi($di);
        $this->api->setService($taxService);
        $this->api->setIdentity(new \Model_Admin());

        $data['id'] = 1;
        $result     = $this->api->tax_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

}
 