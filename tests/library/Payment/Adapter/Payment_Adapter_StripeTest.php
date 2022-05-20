<?php


class Payment_Adapter_StripeTest extends PHPUnit\Framework\TestCase {

    private $defaultConfig = array();

    public function setup(): void
    {
        $this->defaultConfig = array(
            'api_key' => '123APIKEY456',
            'pub_key' => '654PUBKEY321',
            'test_mode' => false,
        );
    }

    public function testDi()
    {
        $di = new \Box_Di();

        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Stripe')->disableOriginalConstructor()
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
        $adapter = new Payment_Adapter_Stripe($this->defaultConfig);
        $result = $adapter->getConfig();
        $this->assertArrayHasKey('supports_one_time_payments', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('form', $result);

        $form = $result['form'];
        $this->assertArrayHasKey('test_api_key', $form);
        $this->assertArrayHasKey('test_pub_key', $form);
        $this->assertArrayHasKey('api_key', $form);
        $this->assertArrayHasKey('pub_key', $form);
    }

    public function testgetHtml()
    {
        $adapterMock = $this->getMockBuilder('\Payment_Adapter_Stripe')->disableOriginalConstructor()
            ->setMethods(array('_generateForm', ))
            ->getMock();

        $adapterMock->expects($this->atLeastOnce())
            ->method('_generateForm')
            ->willReturn('<html></html>');

        $di = new \Box_Di();

        $model = new \Model_Invoice();
        $model->loadBean(new RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->with('Invoice')
            ->willReturn($model);
        $di['db'] = $dbMock;

        $adapterMock->setDi($di);

        $api_admin = new Api_Handler(new Model_Admin());
        $result = $adapterMock->getHtml($api_admin, 2, false);
        $this->assertIsString($result);
    }

    public function testgetAmountInCents()
    {
        $model = new \Model_Invoice();
        $model->loadBean(new RedBeanPHP\OODBBean());

        $totalAmountWithTax = 12.23;
        $invoiceServiceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $invoiceServiceMock->expects($this->atLeastOnce())
            ->method('getTotalWithTax')
            ->with($model)
            ->willReturn($totalAmountWithTax);

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function($serviceName) use ($invoiceServiceMock){
            if ($serviceName == 'Invoice'){
                return $invoiceServiceMock;
            }
        });

        $adapter = new Payment_Adapter_Stripe($this->defaultConfig);
        $adapter->setDi($di);
        $result = $adapter->getAmountInCents($model);
        $this->assertEquals($totalAmountWithTax * 100, $result);
    }

    public function testgetInvoiceTitle_OneItem()
    {
        $queryResult = array(
            array(
                'title' => 'Hosting premium',
            )
        );
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($queryResult);
        $di['db'] = $dbMock;

        $adapter = new Payment_Adapter_Stripe($this->defaultConfig);
        $adapter->setDi($di);

        $model = new \Model_Invoice();
        $model->loadBean(new RedBeanPHP\OODBBean());
        $model->nr = 1;
        $model->serie = 'BOX';

        $result = $adapter->getInvoiceTitle($model);

        $expectedTitle = sprintf('Payment for invoice %s%05s [%s]', $model->serie, $model->nr, $queryResult[0]['title']);
        $this->assertEquals($expectedTitle, $result);
    }

    public function testgetInvoiceTitle_MultipleItems()
    {
        $queryResult = array(
            array(
                'title' => 'Hosting premium',
            ),
            array(
                'title' => 'Domain name',
            )
        );
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($queryResult);
        $di['db'] = $dbMock;

        $adapter = new Payment_Adapter_Stripe($this->defaultConfig);
        $adapter->setDi($di);

        $model = new \Model_Invoice();
        $model->loadBean(new RedBeanPHP\OODBBean());
        $model->nr = 1;
        $model->serie = 'BOX';

        $result = $adapter->getInvoiceTitle($model);

        $expectedTitle = sprintf('Payment for invoice %s%05s', $model->serie, $model->nr);
        $this->assertEquals($expectedTitle, $result);
    }

    public function testget_test_pub_key()
    {
        $config = $this->defaultConfig;
        $config['test_pub_key'] = 'test';
        $adapter = new Payment_Adapter_Stripe($config);
        $result = $adapter->get_test_pub_key();
        $this->assertEquals($config['test_pub_key'], $result);
    }

    public function testget_test_pub_key_IsNotSet()
    {
        $config = $this->defaultConfig;
        $adapter = new Payment_Adapter_Stripe($config);

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('Payment gateway "Stripe" is not configured properly. Please update configuration parameter "test_pub_key" at "Configuration -> Payments".');

        $adapter->get_test_pub_key();

    }

    public function testget_test_api_key()
    {
        $config = $this->defaultConfig;
        $config['test_api_key'] = 'test';
        $adapter = new Payment_Adapter_Stripe($config);
        $result = $adapter->get_test_api_key();
        $this->assertEquals($config['test_api_key'], $result);
    }

    public function testget_test_api_key_IsNotSet()
    {
        $config = $this->defaultConfig;
        $adapter = new Payment_Adapter_Stripe($config);

        $this->expectException(Payment_Exception::class);
        $this->expectExceptionMessage('Payment gateway "Stripe" is not configured properly. Please update configuration parameter "test_api_key" at "Configuration -> Payments".');

        $adapter->get_test_api_key();

    }
}