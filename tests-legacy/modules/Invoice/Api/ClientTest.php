<?php

namespace Box\Mod\Invoice\Api;

class ClientTest extends \BBTestCase
{
    /**
     * @var Client
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Client();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
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
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5(1);
        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testgetInvoiceNotFound(): void
    {
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5(1);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Invoice was not found');
        $this->api->get($data);
    }

    public function testupdate(): void
    {
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
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5(1);
        $result = $this->api->update($data);
        $this->assertIsBool($result);
        $this->assertTrue(true);
    }

    public function testupdateInvoiceNotFound(): void
    {
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5(1);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Invoice was not found');
        $this->api->update($data);
    }

    public function testupdateInvoiceIsPaid(): void
    {
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $model->status = 'paid';
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5(1);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Paid Invoice cannot be modified');
        $this->api->update($data);
    }

    public function testrenewalInvoice(): void
    {
        $generatedHash = 'generatedHashString';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();

        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $model->hash = $generatedHash;
        $serviceMock->expects($this->atLeastOnce())
            ->method('generateForOrder')
            ->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('approveInvoice');

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $clientOrder->price = 10;

        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($clientOrder);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $identity = new \Model_Admin();
        $identity->loadBean(new \DummyBean());
        $this->api->setIdentity($identity);

        $data['order_id'] = 1;
        $result = $this->api->renewal_invoice($data);
        $this->assertIsString($result);
        $this->assertEquals($generatedHash, $result);
    }

    public function testrenewalInvoiceOrderIsFree(): void
    {
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $clientOrder->id = 1;
        $clientOrder->price = 0;

        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($clientOrder);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->api->setDi($di);
        $identity = new \Model_Admin();
        $identity->loadBean(new \DummyBean());
        $this->api->setIdentity($identity);

        $data['order_id'] = 1;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d is free. No need to generate invoice.', $clientOrder->id));
        $this->api->renewal_invoice($data);
    }

    public function testrenewalInvoiceOrderNotFound(): void
    {
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $clientOrder->price = 10;

        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $identity = new \Model_Admin();
        $identity->loadBean(new \DummyBean());
        $this->api->setIdentity($identity);

        $data['order_id'] = 1;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order not found');
        $this->api->renewal_invoice($data);
    }

    public function testfundsInvoice(): void
    {
        $generatedHash = 'generatedHashString';

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();

        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $model->hash = $generatedHash;
        $serviceMock->expects($this->atLeastOnce())
            ->method('generateFundsInvoice')
            ->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('approveInvoice');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['logger'] = new \Box_Log();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $identity = new \Model_Client();
        $identity->loadBean(new \DummyBean());
        $this->api->setIdentity($identity);

        $data['amount'] = 10;
        $result = $this->api->funds_invoice($data);
        $this->assertIsString($result);
        $this->assertEquals($generatedHash, $result);
    }

    public function testdelete(): void
    {
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteInvoiceByClient')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());

        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $identity = new \Model_Client();
        $identity->loadBean(new \DummyBean());
        $this->api->setIdentity($identity);

        $data['hash'] = md5(1);
        $result = $this->api->delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
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

        $identity = new \Model_Client();
        $identity->loadBean(new \DummyBean());
        $this->api->setIdentity($identity);
        $result = $this->api->transaction_get_list([]);
        $this->assertIsArray($result);
    }

    public function testgetTaxRate(): void
    {
        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $taxRate = 20;

        $invoiceTaxService = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServiceTax::class)
            ->getMock();
        $invoiceTaxService->expects($this->atLeastOnce())
            ->method('getTaxRateForClient')
            ->willReturn($taxRate);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($service, $sub) use ($invoiceTaxService) {
            if ($service == 'Invoice' && $sub == 'Tax') {
                return $invoiceTaxService;
            }
        });
        $this->api->setDi($di);
        $this->api->setIdentity($client);

        $result = $this->api->get_tax_rate();
        $this->assertEquals($taxRate, $result);
    }
}
