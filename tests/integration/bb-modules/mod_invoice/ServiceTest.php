<?php
/**
 * @group Core
 */
class Box_Mod_Invoice_ServiceTest extends BBDbApiTestCase
{
    protected $_mod = 'invoice';
    protected $_initialSeedFile = 'mod_invoice.xml';
    
    public function testEvents()
    {
        $service = new \Box\Mod\Invoice\Service();
        $service->setDi($this->di);
        $params = array(
            'id' => 1,
        );
        $event = new Box_Event(null, 'name', $params, $this->api_admin, $this->api_guest);
        $event->setDi($this->di);
        $bool = $service->onAfterAdminInvoicePaymentReceived($event);
        $this->assertTrue($bool);
        
        $params = array(
            'id' => 1,
        );
        $event = new Box_Event(null, 'name', $params, $this->api_admin, $this->api_guest);
        $event->setDi($this->di);
        $bool = $service->onAfterAdminInvoiceApprove($event);
        $this->assertTrue($bool);
    }

    /**
     * Process Paypal transaction
     */
    public function testprocessTransaction()
    {
        $service = new \Box\Mod\Invoice\ServiceTransaction();
        $service->setDi($this->di);

        $transactionModel = $this->di['db']->load('Transaction', 10);
        $this->assertInstanceOf('Model_Transaction', $transactionModel);

        $gatewayModel = $this->di['db']->load('PayGateway', $transactionModel->gateway_id);
        $this->assertEquals('PayPalEmail', $gatewayModel->gateway);

        $service->processTransaction($transactionModel->id);

    }

    /**
     * Process Paypal duplicate transaction
     */
    public function testcreateAndProcessTransaction_Duplicate()
    {
        $service = new \Box\Mod\Invoice\ServiceTransaction();
        $service->setDi($this->di);

        $transactionModel = $this->di['db']->load('Transaction', 10);
        $this->assertInstanceOf('Model_Transaction', $transactionModel);

        $gatewayModel = $this->di['db']->load('PayGateway', $transactionModel->gateway_id);
        $this->assertEquals('PayPalEmail', $gatewayModel->gateway);

        $service->processTransaction($transactionModel->id);

        $transactionIpn = json_decode($transactionModel->ipn, 1);

        $ipn = array(
            'skip_validation'       =>  true,
            'bb_invoice_id'         =>  $transactionModel->invoice_id,
            'bb_gateway_id'         =>  $transactionModel->gateway_id,
            'get'                   =>  $transactionIpn['get'],
            'post'                  =>  $transactionIpn['post'],
        );

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('IPN is duplicate');

        $newId = $service->createAndProcess($ipn);

        $transactionModel = $this->di['db']->load('Transaction', $newId);
        $this->assertInstanceOf('Model_Transaction', $transactionModel);

    }

    public function testonAfterAdminCronRun()
    {
        $systemService = $this->di['mod_service']('System');

        $remove_after_days = 1;

        $systemService->setParamValue('remove_after_days', $remove_after_days);
        $expected = $systemService->getParamValue('remove_after_days');
        $this->assertEquals($expected, $remove_after_days);

        $sql = "SELECT 1 FROM invoice WHERE status = 'unpaid' AND DATEDIFF(NOW(), due_at) > :days";
        $binigns = array(':days' => $remove_after_days);
        $result = $this->di['db']->getAll($sql, $binigns);
        $invoiceNeedsToBeDeleted = count($result);

        $this->assertGreaterThan(0, $invoiceNeedsToBeDeleted);

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->disableOriginalConstructor()
            ->setMethods(array('getDi'))
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($this->di);
        $service = new \Box\Mod\Invoice\Service();
        $service->onAfterAdminCronRun($eventMock);

        $result = $this->di['db']->getAll($sql, $binigns);
        $this->assertEquals(0, count($result));
    }


    public function testbatch_activate_paid()
    {
        $invoiceItems = $this->di['mod_service']('Invoice', 'InvoiceItem')->getAllNotExecutePaidItems();
        $this->assertNotEmpty($invoiceItems);

        $bool = $this->api_admin->invoice_batch_activate_paid();
        $this->assertTrue($bool);

        $invoiceItems = $this->di['mod_service']('Invoice', 'InvoiceItem')->getAllNotExecutePaidItems();
        $this->assertEmpty($invoiceItems);
    }
}