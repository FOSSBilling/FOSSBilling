<?php


class Payment_Adapter_PayPalTest extends PHPUnit\Framework\TestCase {

    private $defaultConfig = array();

    public function setup(): void
    {
        $this->defaultConfig = array(
            'email' => 'info@boxbilling.com',
        );
    }

    public function testDi()
    {
        $di = new \Box_Di();

        $adapterMock = $this->getMockBuilder('\Payment_Adapter_PayPalEmail')->disableOriginalConstructor()
            ->setMethods(array('setDi', 'getDi'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('setDi')
            ->with($di);
        $adapterMock->setDi($di);

        $adapterMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $getDi = $adapterMock->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testConsturct()
    {
        $adapter = new Payment_Adapter_PayPalEmail($this->defaultConfig);
        $this->assertInstanceOf('Payment_Adapter_PayPalEmail', $adapter);
    }


    public function testConsturctMissingEmail()
    {
        $config = array();

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('Payment gateway "PayPal" is not configured properly. Please update configuration parameter "PayPal Email address" at "Configuration -> Payments".');

        new Payment_Adapter_PayPalEmail($config);
    }

    public function testgetConfig()
    {
        $adapter = new Payment_Adapter_PayPalEmail($this->defaultConfig);
        $result = $adapter->getConfig();
        $this->assertArrayHasKey('supports_one_time_payments', $result);
        $this->assertArrayHasKey('supports_subscriptions', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('form', $result);
        $form = $result['form'];
        $this->assertArrayHasKey('email', $form);
    }

    public function testisIpnDuplicate()
    {
        $di = new \Box_Di();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn(array(
                array('id' => 1),
                array('id' => 2),
            ));

        $di['db'] = $dbMock;
        $adapter = new Payment_Adapter_PayPalEmail($this->defaultConfig);
        $adapter->setDi($di);

        $ipn = array(
            'txn_id' => '1A2D3F4G5G6B7X8Z9C',
            'payment_status' => 'Completed',
            'txn_type' => 'web_accept',
            'mc_gross' => '5.45'
        );

        $result = $adapter->isIpnDuplicate($ipn);
        $this->assertTrue($result);
    }

    public function testisIpnDuplicate_IsNot()
    {
        $di = new \Box_Di();

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn(array(
                array('id' => 1),
            ));

        $di['db'] = $dbMock;
        $adapter = new Payment_Adapter_PayPalEmail($this->defaultConfig);
        $adapter->setDi($di);

        $ipn = array(
            'txn_id' => '1A2D3F4G5G6B7X8Z9C',
            'payment_status' => 'Completed',
            'txn_type' => 'web_accept',
            'mc_gross' => '5.45'
        );

        $result = $adapter->isIpnDuplicate($ipn);
        $this->assertFalse($result);
    }

    public function testgetOneTimePaymentFields()
    {
        $config = array(
            'return_url' => '',
            'cancel_url' => '',
            'notify_url' => '',
            'email' => '',
        );
        $adapterMock = $this->getMockBuilder('Payment_Adapter_PayPalEmail')
            ->setMethods(array('__construct', 'getInvoiceTitle', 'moneyFormat'))
            ->setConstructorArgs(array($config))
            ->getMock();

        $title = 'Payment for invoice BOX000001';
        $adapterMock->expects($this->atLeastOnce())
            ->method('getInvoiceTitle')
            ->willReturn($title);

        $invoice = array(
            'nr' => '00001',
            'currency' => 'USD',
            'subtotal' => '10.00',
            'tax' => '4',
        );
        $result = $adapterMock->getOneTimePaymentFields($invoice);
        $this->assertArrayHasKey('item_name', $result);
        $this->assertArrayHasKey('item_number', $result);
        $this->assertArrayHasKey('no_shipping', $result);
        $this->assertArrayHasKey('no_note', $result);
        $this->assertArrayHasKey('currency_code', $result);
        $this->assertArrayHasKey('rm', $result);
        $this->assertArrayHasKey('return', $result);
        $this->assertArrayHasKey('cancel_return', $result);
        $this->assertArrayHasKey('notify_url', $result);
        $this->assertArrayHasKey('business', $result);
        $this->assertArrayHasKey('cmd', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('tax', $result);
        $this->assertArrayHasKey('bn', $result);
        $this->assertArrayHasKey('charset', $result);
    }

    public function testgetSubscriptionFields()
    {
        $config = array(
            'return_url' => '',
            'cancel_url' => '',
            'notify_url' => '',
            'email' => '',
        );
        $adapterMock = $this->getMockBuilder('Payment_Adapter_PayPalEmail')
            ->setMethods(array('__construct', 'getInvoiceTitle', 'moneyFormat'))
            ->setConstructorArgs(array($config))
            ->getMock();

        $title = 'Payment for invoice BOX000001';
        $adapterMock->expects($this->atLeastOnce())
            ->method('getInvoiceTitle')
            ->willReturn($title);

        $invoice = array(
            'nr' => '00001',
            'currency' => 'USD',
            'total' => '10.00',
            'tax' => '4',
            'subscription' => array(
                'cycle' => '',
                'unit' => '',
            ),
            'buyer' => array(
                'address' => '',
                'city' => '',
                'email' => '',
                'first_name' => '',
                'last_name' => '',
                'zip' => '',
                'state' => '',
            ),
            'id' => '2',
        );
        $result = $adapterMock->getSubscriptionFields($invoice);
        $this->assertArrayHasKey('item_name', $result);
        $this->assertArrayHasKey('item_number', $result);
        $this->assertArrayHasKey('no_shipping', $result);
        $this->assertArrayHasKey('no_note', $result);
        $this->assertArrayHasKey('currency_code', $result);
        $this->assertArrayHasKey('rm', $result);
        $this->assertArrayHasKey('return', $result);
        $this->assertArrayHasKey('cancel_return', $result);
        $this->assertArrayHasKey('notify_url', $result);
        $this->assertArrayHasKey('business', $result);
        $this->assertArrayHasKey('cmd', $result);
        $this->assertArrayHasKey('a3', $result);
        $this->assertArrayHasKey('p3', $result);
        $this->assertArrayHasKey('t3', $result);
        $this->assertArrayHasKey('src', $result);
        $this->assertArrayHasKey('sra', $result);
        $this->assertArrayHasKey('charset', $result);
        $this->assertArrayHasKey('address1', $result);
        $this->assertArrayHasKey('city', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('first_name', $result);
        $this->assertArrayHasKey('last_name', $result);
        $this->assertArrayHasKey('zip', $result);
        $this->assertArrayHasKey('state', $result);
    }

    public function testgetInvoiceTitle()
    {
        $adapterMock = $this->getMockBuilder('Payment_Adapter_PayPalEmail')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $nr = 1;
        $serie = 'BOX';
        $title = 'Product';
        $invoice = array(
            'nr' => $nr,
            'serie' => $serie,
            'lines' => array(
                array(
                    'title' => $title,
                ),
            ),
        );

        $nr = sprintf('%05s', $nr);
        $expectedTitle = sprintf('Payment for invoice %s%s [%s]', $serie, $nr, $title);
        $result = $adapterMock->getInvoiceTitle($invoice);
        $this->assertEquals($expectedTitle, $result);
    }
}
