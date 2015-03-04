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

        $this->setExpectedException('Payment_Exception', 'IPN is duplicate');
        $newId = $service->createAndProcess($ipn);

        $transactionModel = $this->di['db']->load('Transaction', $newId);
        $this->assertInstanceOf('Model_Transaction', $transactionModel);

    }

}