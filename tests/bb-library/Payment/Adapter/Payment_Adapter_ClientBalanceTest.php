<?php


class Payment_Adapter_ClientBalanceTest extends PHPUnit\Framework\TestCase {

    public function testDi()
    {
        $di = new \Box_Di();

        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')->disableOriginalConstructor()
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

    public function testgetConfig()
    {
        $adapter = new \Payment_Adapter_ClientBalance();
        $result = $adapter->getConfig();
        $this->assertEquals(array(), $result);
    }

    public function testgetHtml_NotEnoughInBalance()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $di = new \Box_Di();
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Invoice')
            ->willReturn($invoiceModel);
        $di['db'] = $dbMock;

        $adapterMock = $this->getMockBuilder('Payment_Adapter_ClientBalance')
            ->disableOriginalConstructor()
            ->setMethods(array('enoughInBalanceToCoverInvoice'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('enoughInBalanceToCoverInvoice')
            ->with($invoiceModel)
            ->willReturn(false);

        $adapterMock->setDi($di);
        $invoiceId = 1;
        $result = $adapterMock->getHtml(null, $invoiceId, null);
        $this->assertIsString($result);
        $this->assertEquals('Not enough in balance', $result);
    }

    public function testgetHtml_InvoiceTypeDeposit()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $di = new \Box_Di();
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Invoice')
            ->willReturn($invoiceModel);
        $di['db'] = $dbMock;

        $adapterMock = $this->getMockBuilder('Payment_Adapter_ClientBalance')
            ->disableOriginalConstructor()
            ->setMethods(array('enoughInBalanceToCoverInvoice'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('enoughInBalanceToCoverInvoice')
            ->with($invoiceModel)
            ->willReturn(true);

        $invoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('isInvoiceTypeDeposit')
            ->with($invoiceModel)
            ->willReturn(true);
        $di['mod_service'] = $di->protect(function($serviceName) use($invoiceServiceMock){
            if ($serviceName == 'Invoice'){
                return $invoiceServiceMock;
            }
        });

        $adapterMock->setDi($di);
        $invoiceId = 1;
        $result = $adapterMock->getHtml(null, $invoiceId, null);
        $this->assertIsString($result);
        $this->assertEquals('Forbidden to pay deposit invoice with this gateway', $result);
    }

    public function testgetHtml()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $di = new \Box_Di();
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Invoice')
            ->willReturn($invoiceModel);
        $di['db'] = $dbMock;

        $adapterMock = $this->getMockBuilder('Payment_Adapter_ClientBalance')
            ->disableOriginalConstructor()
            ->setMethods(array('getServiceUrl', 'enoughInBalanceToCoverInvoice'))
            ->getMock();

        $invoiceId = 1;
        $url = 'http://www.boxbilling.com/bb-ipn.php?bb-gateway_id=0&bb_invoice_id='.$invoiceId;
        $adapterMock->expects($this->atLeastOnce())
            ->method('getServiceUrl')
            ->with($invoiceId)
            ->willReturn($url);
        $adapterMock->expects($this->atLeastOnce())
            ->method('enoughInBalanceToCoverInvoice')
            ->with($invoiceModel)
            ->willReturn(true);
        $invoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('isInvoiceTypeDeposit')
            ->with($invoiceModel)
            ->willReturn(false);
        $di['mod_service'] = $di->protect(function($serviceName) use($invoiceServiceMock){
            if ($serviceName == 'Invoice'){
                return $invoiceServiceMock;
            }
        });

        $toolsMock = $this->getMockBuilder('\Box_Tools')
            ->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('url');
        $di['tools'] = $toolsMock;

        $adapterMock->setDi($di);
        $result = $adapterMock->getHtml(null, $invoiceId, null);

        $this->assertIsString($result);
        $this->assertTrue(strpos($result, $url) !== false);
        $this->assertTrue(strpos($result, '<script') !== false);
    }

    public function testprocessTransaction_IpnIsInValid()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $adapterMock = $this->getMockBuilder('Payment_Adapter_ClientBalance')
            ->disableOriginalConstructor()
            ->setMethods(array('isIpnValid'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('isIpnValid')
            ->willReturn(false);

        $transactionId = 1;
        $data = array();
        $gatewayId = 1;

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('IPN is not valid');

        $adapterMock->processTransaction(null, $transactionId, $data, $gatewayId);
    }

    public function testprocessTransaction_InvoiceTypeDeposit()
    {
        $adapterMock = $this->getMockBuilder('Payment_Adapter_ClientBalance')
            ->disableOriginalConstructor()
            ->setMethods(array('isIpnValid'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('isIpnValid')
            ->willReturn(true);

        $di = new \Box_Di();

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->withConsecutive(array('Transaction'), array('Invoice'))
            ->willReturnOnConsecutiveCalls($transactionModel, $invoiceModel);

        $di['db'] = $dbMock;

        $invoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('isInvoiceTypeDeposit')
            ->with($invoiceModel)
            ->willReturn(true);

        $di['mod_service'] = $di->protect(function ($serviceName) use($invoiceServiceMock){
            if ('Invoice' == $serviceName)
                return $invoiceServiceMock;
        });

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionCode(303);
        $this->expectExceptionMessage('Forbidden to pay deposit invoice with this gateway');

        $transactionId = 1;
        $data = array();
        $gatewayId = 1;
        $adapterMock->setDi($di);
        $adapterMock->processTransaction(null, $transactionId, $data, $gatewayId);
    }

    public function testprocessTransaction_invoiceIdIsSet()
    {
        $adapterMock = $this->getMockBuilder('Payment_Adapter_ClientBalance')
            ->disableOriginalConstructor()
            ->setMethods(array('isIpnValid'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('isIpnValid')
            ->willReturn(true);

        $di = new \Box_Di();

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->withConsecutive(array('Transaction'), array('Invoice'))
            ->willReturnOnConsecutiveCalls($transactionModel, $invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($transactionModel);
        $di['db'] = $dbMock;

        $invoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('isInvoiceTypeDeposit')
            ->with($invoiceModel)
            ->willReturn(false);
        $invoiceServiceMock->expects($this->once())
            ->method('payInvoiceWithCredits')
            ->with($invoiceModel);
        $invoiceServiceMock->expects($this->once())
            ->method('doBatchPayWithCredits');

        $di['mod_service'] = $di->protect(function ($serviceName) use($invoiceServiceMock){
            if ('Invoice' == $serviceName)
                return $invoiceServiceMock;
        });

        $transactionId = 1;
        $data = array(
            'get'=> array(
                'bb_invoice_id' => 1
            )
        );
        $gatewayId = 1;
        $adapterMock->setDi($di);
        $result = $adapterMock->processTransaction(null, $transactionId, $data, $gatewayId);
        $this->assertTrue($result);
    }

    public function testprocessTransaction_invoiceIdIsNotSet()
    {
        $adapterMock = $this->getMockBuilder('Payment_Adapter_ClientBalance')
            ->disableOriginalConstructor()
            ->setMethods(array('isIpnValid'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('isIpnValid')
            ->willReturn(true);

        $di = new \Box_Di();

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->withConsecutive(array('Transaction'), array('Invoice'))
            ->willReturnOnConsecutiveCalls($transactionModel, $invoiceModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($transactionModel);
        $di['db'] = $dbMock;

        $invoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('isInvoiceTypeDeposit')
            ->with($invoiceModel)
            ->willReturn(false);
        $invoiceServiceMock->expects($this->never())
            ->method('payInvoiceWithCredits')
            ->with($invoiceModel);
        $invoiceServiceMock->expects($this->once())
            ->method('doBatchPayWithCredits');

        $di['mod_service'] = $di->protect(function ($serviceName) use($invoiceServiceMock){
            if ('Invoice' == $serviceName)
                return $invoiceServiceMock;
        });

        $transactionId = 1;
        $data = array(
            'get'=> array(
            )
        );
        $gatewayId = 1;
        $adapterMock->setDi($di);
        $result = $adapterMock->processTransaction(null, $transactionId, $data, $gatewayId);
        $this->assertTrue($result);
    }

    public function testisIpnValid()
    {
        $adapter = new \Payment_Adapter_ClientBalance();
        $result = $adapter->isIpnValid(array());
        $this->assertTrue($result);
    }

    public function testgetServiceUrl_ClientBalanceGatewayNotEnabled()
    {
        $di = new \Box_Di();

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();

        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('PayGateway')
            ->willReturn(null);

        $di['db'] = $dbMock;

        $adapter = new \Payment_Adapter_ClientBalance();
        $adapter->setDi($di);

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionCode(301);
        $this->expectExceptionMessage('ClientBalance gateway is not enabled');

        $adapter->getServiceUrl();
    }

    public function testgetServiceUrl_InvoiceTypeDeposit()
    {
        $di = new \Box_Di();

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();

        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('PayGateway')
            ->willReturn($payGatewayModel);
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Invoice')
            ->willReturn($invoiceModel);
        $di['db'] = $dbMock;

        $invoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('isInvoiceTypeDeposit')
            ->with($invoiceModel)
            ->willReturn(true);
        $di['mod_service'] = $di->protect(function ($serviceName) use($invoiceServiceMock){
            if ('Invoice' == $serviceName)
                return $invoiceServiceMock;
        });

        $adapter = new \Payment_Adapter_ClientBalance();
        $adapter->setDi($di);

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionCode(302);
        $this->expectExceptionMessage('Forbidden to pay deposit invoice with this gateway');

        $adapter->getServiceUrl();
    }

    public function amountsProvider()
    {
        return array(
            array(
                4.25, 2.00, true,
            ),
            array(
                1.00, 20.00, false,
            ),
            array(
                2.00, 2.00, true,
            ),
        );
    }

    /**
     * @dataProvider amountsProvider
     */
    public function testenoughInBalanceToCoverInvoice($inBalance, $invoiceSum, $expectedResult)
    {
        $di = new \Box_Di();

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $clientModel = new Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Client')
            ->willReturn($clientModel);
        $di['db'] = $dbMock;

        $clientBalanceServiceMock = $this->getMockBuilder('\Box\Mod\Client\ServiceBalance')
            ->getMock();

        $clientBalanceServiceMock->expects($this->atLeastOnce())
            ->method('getClientBalance')
            ->with($clientModel)
            ->willReturn($inBalance);

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('getTotalWithTax')
            ->with($invoiceModel)
            ->willReturn($invoiceSum);
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use($invoiceServiceMock, $clientBalanceServiceMock){
            if ('Invoice' == $serviceName){
                return $invoiceServiceMock;
            }
            if ('Client' == $serviceName && $sub == 'Balance'){
                return $clientBalanceServiceMock;
            }
        });

        $adapter = new Payment_Adapter_ClientBalance();
        $adapter->setDi($di);
        $result = $adapter->enoughInBalanceToCoverInvoice($invoiceModel);
        $this->assertEquals($expectedResult, $result);
    }
}