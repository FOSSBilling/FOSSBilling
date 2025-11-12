<?php

namespace Box\Mod\Invoice\Api;

class GuestTest extends \BBTestCase
{
    /**
     * @var Guest
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Guest();
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

    public function testgateways(): void
    {
        $gatewayServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\ServicePayGateway::class)->getMock();
        $gatewayServiceMock->expects($this->atLeastOnce())
            ->method('getActive')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $gatewayServiceMock);

        $this->api->setDi($di);

        $result = $this->api->gateways([]);
        $this->assertIsArray($result);
    }

    public function testpayment(): void
    {
        $data = [
            'hash' => '',
            'gateway_id' => '',
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('processInvoice')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->payment($data);
        $this->assertIsArray($result);
    }

    public function testpaymentMissingHashParam(): void
    {
        $data = [
            'gateway_id' => '',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(810);
        $this->expectExceptionMessage('Invoice hash not passed. Missing param hash');
        $this->api->payment($data);
    }

    public function testpaymentMissingGatewayIdParam(): void
    {
        $data = [
            'hash' => '',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(811);
        $this->expectExceptionMessage('Payment method not found. Missing param gateway_id');
        $this->api->payment($data);
    }

    public function testpdf(): void
    {
        $data = [
            'hash' => '',
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Invoice\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('generatePDF');

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $this->api->pdf($data);
    }
}
