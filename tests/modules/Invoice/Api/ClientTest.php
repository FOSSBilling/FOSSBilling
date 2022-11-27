<?php


namespace Box\Mod\Invoice\Api;


class ClientTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Invoice\Api\Client
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api = new \Box\Mod\Invoice\Api\Client();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
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
            ->method('findOne')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5(1);
        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testgetInvoiceNotFound()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5(1);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Invoice was not found');
        $this->api->get($data);
    }

    public function testupdate()
    {
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
            ->method('findOne')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5(1);
        $result = $this->api->update($data);
        $this->assertIsBool($result);
        $this->assertTrue(true);
    }

    public function testupdateInvoiceNotFound()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5(1);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Invoice was not found');
        $this->api->update($data);
    }

    public function testupdateInvoiceIsPaid()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->status = 'paid';
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5(1);
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Paid Invoice can not be modified');
        $this->api->update($data);
    }

    public function testrenewal_invoice()
    {
        $generatedHash = 'generatedHashString';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();

        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->hash = $generatedHash;
        $serviceMock->expects($this->atLeastOnce())
            ->method('generateForOrder')
            ->will($this->returnValue($model));
        $serviceMock->expects($this->atLeastOnce())
            ->method('approveInvoice');

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrder->price = 10;

        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($clientOrder));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $identity = new \Model_Admin();
        $identity->loadBean(new \RedBeanPHP\OODBBean());
        $this->api->setIdentity($identity);

        $data['order_id'] = 1;
        $result = $this->api->renewal_invoice($data);
        $this->assertIsString($result);
        $this->assertEquals($generatedHash, $result);
    }

    public function testrenewal_invoiceOrderIsFree()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrder->id = 1;
        $clientOrder->price = 0;

        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($clientOrder));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->api->setDi($di);
        $identity = new \Model_Admin();
        $identity->loadBean(new \RedBeanPHP\OODBBean());
        $this->api->setIdentity($identity);

        $data['order_id'] = 1;

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d is free. No need to generate invoice.', $clientOrder->id));
        $this->api->renewal_invoice($data);

    }

    public function testrenewal_invoiceOrderNotFound()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrder->price = 10;

        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $identity = new \Model_Admin();
        $identity->loadBean(new \RedBeanPHP\OODBBean());
        $this->api->setIdentity($identity);

        $data['order_id'] = 1;

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Order not found');
        $this->api->renewal_invoice($data);
    }

    public function testfunds_invoice()
    {
        $generatedHash = 'generatedHashString';

        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();

        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->hash = $generatedHash;
        $serviceMock->expects($this->atLeastOnce())
            ->method('generateFundsInvoice')
            ->will($this->returnValue($model));
        $serviceMock->expects($this->atLeastOnce())
            ->method('approveInvoice');

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['logger'] = new \Box_Log();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $identity = new \Model_Client();
        $identity->loadBean(new \RedBeanPHP\OODBBean());
        $this->api->setIdentity($identity);

        $data['amount'] = 10;
        $result = $this->api->funds_invoice($data);
        $this->assertIsString($result);
        $this->assertEquals($generatedHash, $result);
    }

    public function testdelete()
    {
        $validatorMock = $this->getMockBuilder('\Box_Validate')->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteInvoiceByClient')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();


        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $identity = new \Model_Client();
        $identity->loadBean(new \RedBeanPHP\OODBBean());
        $this->api->setIdentity($identity);

        $data['hash'] = md5(1);
        $result = $this->api->delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
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

        $identity = new \Model_Client();
        $identity->loadBean(new \RedBeanPHP\OODBBean());
        $this->api->setIdentity($identity);
        $result = $this->api->transaction_get_list(array());
        $this->assertIsArray($result);
    }

    public function testget_tax_rate()
    {
        $client = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());

        $taxRate = 20;

        $invoiceTaxService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTax')
            ->getMock();
        $invoiceTaxService->expects($this->atLeastOnce())
            ->method('getTaxRateForClient')
            ->willReturn($taxRate);


        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($service, $sub) use($invoiceTaxService){
            if ($service == 'Invoice' && $sub == 'Tax'){
                return  $invoiceTaxService;
            }
        });
        $this->api->setDi($di);
        $this->api->setIdentity($client);

        $result = $this->api->get_tax_rate();
        $this->assertEquals($taxRate, $result);
    }



}
 