<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Api;

#[PHPUnit\Framework\Attributes\Group('Core')]
final class GuestTest extends \BBTestCase
{
    protected ?Guest $api;

    public function setUp(): void
    {
        $this->api = new Guest();
    }

    public function testGetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGet(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Invoice\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $dbMock = $this->createMock('\Box_Database');
        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5('1');
        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testGetInvoiceNotFound(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5('1');
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Invoice was not found');
        $this->api->get($data);
    }

    public function testUpdate(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Invoice\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateInvoice')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5('1');
        $result = $this->api->update($data);
        $this->assertIsBool($result);
        $this->assertTrue(true);
    }

    public function testUpdateInvoiceNotFound(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5('1');
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Invoice was not found');
        $this->api->update($data);
    }

    public function testUpdateInvoiceIsPaid(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $model = new \Model_Invoice();
        $model->loadBean(new \DummyBean());
        $model->status = 'paid';
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setIdentity(new \Model_Admin());

        $data['hash'] = md5('1');
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Paid Invoice cannot be modified');
        $this->api->update($data);
    }

    public function testGateways(): void
    {
        $gatewayServiceMock = $this->createMock(\Box\Mod\Invoice\ServicePayGateway::class);
        $gatewayServiceMock->expects($this->atLeastOnce())
            ->method('getActive')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn () => $gatewayServiceMock);

        $this->api->setDi($di);

        $result = $this->api->gateways([]);
        $this->assertIsArray($result);
    }

    public function testPayment(): void
    {
        $data = [
            'hash' => '',
            'gateway_id' => '',
        ];
        $serviceMock = $this->createMock(\Box\Mod\Invoice\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('processInvoice')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->payment($data);
        $this->assertIsArray($result);
    }

    public function testPaymentMissingHashParam(): void
    {
        $data = [
            'gateway_id' => '',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(810);
        $this->expectExceptionMessage('Invoice hash not passed. Missing param hash');
        $this->api->payment($data);
    }

    public function testPaymentMissingGatewayIdParam(): void
    {
        $data = [
            'hash' => '',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(811);
        $this->expectExceptionMessage('Payment method not found. Missing param gateway_id');
        $this->api->payment($data);
    }

    public function testPdf(): void
    {
        $data = [
            'hash' => '',
        ];
        $serviceMock = $this->createMock(\Box\Mod\Invoice\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('generatePDF');

        $di = new \Pimple\Container();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $this->api->pdf($data);
    }
}