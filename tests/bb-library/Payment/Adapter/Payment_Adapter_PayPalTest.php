<?php


class Payment_Adapter_PaypalTest extends PHPUnit_Framework_TestCase {

    private $defaultConfig = array();

    public function setup()
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
        $this->setExpectedException('Payment_Exception', 'Payment gateway "PayPal" is not configured properly. Please update configuration parameter "PayPal Email address" at "Configuration -> Payments".');
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
}
