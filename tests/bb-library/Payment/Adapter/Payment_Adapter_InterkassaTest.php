<?php


class Payment_Adapter_InterkassaTest extends PHPUnit\Framework\TestCase {

    private $defaultConfig = array();

    public function setup(): void
    {
        $this->defaultConfig = array(
            'url' => 'boxbilling.test',
            'return_url' => 'boxbilling.test',
            'notify_url' => 'boxbilling.test',
            'cancel_url' => 'boxbilling.test',
            'redirect_url' => 'boxbilling.test',

            'ik_co_id' => '64C18529-4B94-0B5D-7405-F2752F2B716C',
            'ik_secret_key' => 'RhAAaJ2AwydMbKzN',
        );
    }

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

    public function testinit()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')->disableOriginalConstructor()
            ->setMethods(array('getParam'))
            ->getMock();

        $adapterMock->expects($this->exactly(2))
            ->method('getParam')
            ->withConsecutive(array('ik_co_id'), array('ik_secret_key'))
            ->willReturnOnConsecutiveCalls('shop_id', 'secret_key_value');

        $adapterMock->init();
    }

    public function testinitShopIdMissing()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')->disableOriginalConstructor()
            ->setMethods(array('getParam'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('getParam')
            ->with('ik_co_id');

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('Shop ID is missing in gateway configuration');

        $adapterMock->init();
    }

    public function testinitSecrectKeyMissing()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')->disableOriginalConstructor()
            ->setMethods(array('getParam'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('getParam')
            ->withConsecutive(array('ik_co_id'), array('ik_secret_key'))
            ->willReturnOnConsecutiveCalls('shop_id_value', null);

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('Secret key is missing in gateway configuration');

        $adapterMock->init();
    }

    public function testgetType()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
        $this->assertEquals(Payment_AdapterAbstract::TYPE_FORM, $adapterMock->getType());
    }

    public function testgetConfig()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
        $config = $adapterMock->getConfig();

        $this->assertArrayHasKey('supports_one_time_payments', $config);
        $this->assertArrayHasKey('supports_subscriptions', $config);
        $this->assertArrayHasKey('description', $config);
        $this->assertArrayHasKey('form', $config);

        $this->assertArrayHasKey('ik_co_id', $config['form']);
        $this->assertArrayHasKey('ik_secret_key', $config['form']);
        $this->assertArrayHasKey('ik_secret_key_test', $config['form']);
    }

    public function testgetServiceUrl()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(array('getParam'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('getParam')
            ->with('test_mode')
            ->willReturn(false);

        $result = $adapterMock->getServiceUrl();
        $this->assertEquals('https://sci.interkassa.com/', $result);
    }

    public function testgetServiceUrl_TestEnabled()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(array('getParam'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('getParam')
            ->with('test_mode')
            ->willReturn(true);

        $result = $adapterMock->getServiceUrl();
        $this->assertEquals('https://sci.interkassa.com/demo/', $result);
    }

    public function testsinglePayment()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(array('getParam'))
            ->getMock();
        $adapterMock->expects($this->atLeastOnce())
            ->method('getParam')
            ->withConsecutive(
                array('ik_co_id'),
                array('notify_url'),
                array('return_url'),
                array('return_url'),
                array('cancel_url'));

        $paymentInvoiceMock = $this->getMockBuilder('Payment_Invoice')
            ->getMock();
        $paymentInvoiceMock->expects($this->atLeastOnce())
            ->method('getId');
        $paymentInvoiceMock->expects($this->atLeastOnce())
            ->method('getTotal');
        $paymentInvoiceMock->expects($this->atLeastOnce())
            ->method('getTitle');
        $paymentInvoiceMock->expects($this->atLeastOnce())
            ->method('getCurrency');
        $paymentInvoiceMock->expects($this->atLeastOnce())
            ->method('getTotal');


        $result = $adapterMock->singlePayment($paymentInvoiceMock);

        $this->assertArrayHasKey('ik_co_id', $result);
        $this->assertArrayHasKey('ik_pm_no', $result);
        $this->assertArrayHasKey('ik_am', $result);
        $this->assertArrayHasKey('ik_desc', $result);
        $this->assertArrayHasKey('ik_cur', $result);

        $this->assertArrayHasKey('ik_ia_u', $result);
        $this->assertArrayHasKey('ik_ia_m', $result);
        $this->assertArrayHasKey('ik_suc_u', $result);
        $this->assertArrayHasKey('ik_suc_m', $result);
        $this->assertArrayHasKey('ik_pnd_u', $result);
        $this->assertArrayHasKey('ik_pnd_m', $result);
        $this->assertArrayHasKey('ik_fal_u', $result);
        $this->assertArrayHasKey('ik_fal_m', $result);

        $this->assertArrayHasKey('ik_x_iid', $result);
    }

    public function testrecurrentPayment()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $paymentInvoiceMock = $this->getMockBuilder('Payment_Invoice')
            ->getMock();

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('Interkassa payment gateway do not support recurrent payments');

        $adapterMock->recurrentPayment($paymentInvoiceMock);
    }

    public function testgetTransaction()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $data = array(
            'post' => array(
                'ik_trn_id' => 1,
                'ik_am' => 0.10,
                'ik_cur' => 'USD',
                'ik_inv_st' => 'success',
            ),
        );
        $paymentInvoiceMock = $this->getMockBuilder('Payment_Invoice')
            ->getMock();
        $result = $adapterMock->getTransaction($data, $paymentInvoiceMock);
        $this->assertInstanceOf('Payment_Transaction', $result);
    }

    public function testisIpnValid()
    {
        $shop_id = '123';
        $secret_key = '123';
        $signValue = '1NKiNTfKnBLzl8KYPhbNUA==';

        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(array('getParam'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('getParam')
            ->withConsecutive(
                array('ik_co_id'),
                array('ik_secret_key'))
            ->willReturnOnConsecutiveCalls($shop_id, $secret_key);

        $data = array(
            'post' => array(
                'ik_co_id' => $shop_id,
                'ik_trn_id' => 1,
                'ik_am' => 0.10,
                'ik_cur' => 'USD',
                'ik_inv_st' => 'success',
                'ik_sign' => $signValue,
            ),
        );

        $result = $adapterMock->isIpnValid($data);
        $this->assertTrue($result);
    }

    public function testisIpnValid_MismatchShopId()
    {
        $shop_id = '12345';
        $secret_key = '123';
        $signValue = '1NKiNTfKnBLzl8KYPhbNUA==';

        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(array('getParam'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('getParam')
            ->withConsecutive(
                array('ik_co_id'),
                array('ik_secret_key'))
            ->willReturnOnConsecutiveCalls($shop_id, $secret_key);

        $data = array(
            'post' => array(
                'ik_co_id' => 123,
            ),
        );

        $result = $adapterMock->isIpnValid($data);
        $this->assertFalse($result);
    }

    public function testisIpnValid_TestMode()
    {
        $shop_id = '123';
        $secret_key = '123';
        $signValue = '1NKiNTfKnBLzl8KYPhbNUA==';

        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(array('getParam'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('getParam')
            ->withConsecutive(
                array('ik_co_id'),
                array('ik_secret_key'),
                array('test_mode'),
                array('ik_secret_key_test'))
            ->willReturnOnConsecutiveCalls($shop_id, $secret_key, true, $secret_key);

        $data = array(
            'post' => array(
                'ik_co_id' => $shop_id,
                'ik_trn_id' => 1,
                'ik_am' => 0.10,
                'ik_cur' => 'USD',
                'ik_inv_st' => 'success',
                'ik_sign' => $signValue,
            ),
        );

        $result = $adapterMock->isIpnValid($data);
        $this->assertTrue($result);
    }

    public function testprocessTransaction()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(array('isIpnValid'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('isIpnValid')
            ->willReturn(false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('IPN is not valid');

        $adminModel = new \Model_Admin();
        $api_admin = new \Api_Handler($adminModel);

        $transaction_id = 1;
        $data = array();
        $gateway_id = 1;
        $adapterMock->processTransaction($api_admin, $transaction_id, $data, $gateway_id);
    }

    public function testprocessTransaction_Success()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(array('isIpnValid', 'isIpnDuplicate'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('isIpnValid')
            ->willReturn(true);
        $adapterMock->expects($this->atLeastOnce())
            ->method('isIpnDuplicate')
            ->willReturn(false);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());

        $invoiceModel= new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientModel = new \Model_Client();
        $clientModel ->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->withConsecutive(
                array('Transaction'),
                array('Invoice'),
                array('Client'))
            ->willReturnOnConsecutiveCalls($transactionModel, $invoiceModel, $clientModel);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($transactionModel);

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $clientServiceMock->expects($this->atLeastOnce())
            ->method('addFunds');

        $invoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('payInvoiceWithCredits')
            ->with($invoiceModel);
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('doBatchPayWithCredits');

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($serviceName) use($clientServiceMock, $invoiceServiceMock){
            if ($serviceName == 'Client'){
                return $clientServiceMock;
            }
            if ($serviceName == 'Invoice'){
                return $invoiceServiceMock;
            }
        });
        $di['db'] = $dbMock;
        $adapterMock->setDi($di);

        $adminModel = new \Model_Admin();
        $api_admin = new \Api_Handler($adminModel);

        $transaction_id = 1;
        $invoice_id = 22;
        $data = array(
            'post' => array(
                'ik_x_iid' => $invoice_id,
                'ik_trn_id' => 2,
                'ik_inv_st' => 'success',
                'ik_am' => 1.00,
                'ik_cur' => 'USD',
            ),
            'get'=> array(
                'bb_invoice_id' => $invoice_id
            )
        );
        $gateway_id = 1;
        $adapterMock->processTransaction($api_admin, $transaction_id, $data, $gateway_id);
    }

    public function testprocessTransaction_IpnDuplicate()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(array('isIpnValid', 'isIpnDuplicate'))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('isIpnValid')
            ->willReturn(true);
        $adapterMock->expects($this->atLeastOnce())
            ->method('isIpnDuplicate')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $transactionModel = new \Model_Transaction();
        $transactionModel->loadBean(new \RedBeanPHP\OODBBean());

        $invoiceModel= new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientModel = new \Model_Client();
        $clientModel ->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->withConsecutive(
                array('Transaction'),
                array('Invoice'),
                array('Client'))
            ->willReturnOnConsecutiveCalls($transactionModel, $invoiceModel, $clientModel);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($transactionModel);

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $clientServiceMock->expects($this->never())
            ->method('addFunds');

        $invoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $invoiceServiceMock->expects($this->never())
            ->method('payInvoiceWithCredits')
            ->with($invoiceModel);
        $invoiceServiceMock->expects($this->never())
            ->method('doBatchPayWithCredits');

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($serviceName) use($clientServiceMock, $invoiceServiceMock){
            if ($serviceName == 'Client'){
                return $clientServiceMock;
            }
            if ($serviceName == 'Invoice'){
                return $invoiceServiceMock;
            }
        });
        $di['db'] = $dbMock;
        $adapterMock->setDi($di);

        $adminModel = new \Model_Admin();
        $api_admin = new \Api_Handler($adminModel);

        $transaction_id = 1;
        $invoice_id = 22;
        $data = array(
            'post' => array(
                'ik_x_iid' => $invoice_id,
                'ik_trn_id' => 2,
                'ik_inv_st' => 'success',
                'ik_am' => 1.00,
                'ik_cur' => 'USD',
            ),
            'get'=> array(
                'bb_invoice_id' => $invoice_id
            )
        );
        $gateway_id = 1;

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('IPN is duplicate');

        $adapterMock->processTransaction($api_admin, $transaction_id, $data, $gateway_id);
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

        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $adapterMock->setDi($di);

        $ipn = array(
            'ik_trn_id' => '1A2D3F4G5G6B7X8Z9C',
            'ik_inv_st' => 'Completed',
            'ik_am' => '5.45'
        );

        $result = $adapterMock->isIpnDuplicate($ipn);
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
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Interkassa')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
        $adapterMock->setDi($di);

        $ipn = array(
            'ik_trn_id' => '1A2D3F4G5G6B7X8Z9C',
            'ik_inv_st' => 'Completed',
            'ik_am' => '5.45'
        );

        $result = $adapterMock->isIpnDuplicate($ipn);
        $this->assertFalse($result);
    }



}
