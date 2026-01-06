<?php

declare(strict_types=1);

namespace Box\Mod\Invoice;
use PHPUnit\Framework\Attributes\DataProvider; 
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTaxTest extends \BBTestCase
{
    protected ?ServiceTax $service;

    public function setUp(): void
    {
        $this->service = new ServiceTax();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetTaxRateForClientByCountryAndState(): void
    {
        $taxRateExpected = 0.21;
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $clientServiceMock = $this->getMockBuilder(\Box\Mod\Client\Service::class)
            ->getMock();
        $clientServiceMock->expects($this->atLeastOnce())
            ->method('isClientTaxable')
            ->willReturn(true);

        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \DummyBean());
        $taxModel->taxrate = $taxRateExpected;

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($taxModel);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $clientServiceMock);
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getTaxRateForClient($clientModel);
        $this->assertIsFloat($result);
        $this->assertEquals($taxRateExpected, $result);
    }

    public function testGetTaxRateForClientByCountry(): void
    {
        $taxRateExpected = 0.21;
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $clientServiceMock = $this->getMockBuilder(\Box\Mod\Client\Service::class)
            ->getMock();
        $clientServiceMock->expects($this->atLeastOnce())
            ->method('isClientTaxable')
            ->willReturn(true);

        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \DummyBean());
        $taxModel->taxrate = $taxRateExpected;

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturnOnConsecutiveCalls(null, $taxModel);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $clientServiceMock);
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getTaxRateForClient($clientModel);
        $this->assertIsFloat($result);
        $this->assertEquals($taxRateExpected, $result);
    }

    public function testGetTaxRateForClient(): void
    {
        $taxRateExpected = 0.21;
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $clientServiceMock = $this->getMockBuilder(\Box\Mod\Client\Service::class)
            ->getMock();
        $clientServiceMock->expects($this->atLeastOnce())
            ->method('isClientTaxable')
            ->willReturn(true);

        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \DummyBean());
        $taxModel->taxrate = $taxRateExpected;

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturnOnConsecutiveCalls(null, null, $taxModel);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $clientServiceMock);
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getTaxRateForClient($clientModel);
        $this->assertIsFloat($result);
        $this->assertEquals($taxRateExpected, $result);
    }

    public function testGetTaxRateForClientTaxWasNotFound(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $clientServiceMock = $this->getMockBuilder(\Box\Mod\Client\Service::class)
            ->getMock();
        $clientServiceMock->expects($this->atLeastOnce())
            ->method('isClientTaxable')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturnOnConsecutiveCalls(null, null, null);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $clientServiceMock);
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $taxRateExpected = 0;
        $result = $this->service->getTaxRateForClient($clientModel);
        $this->assertIsInt($result);
        $this->assertEquals($taxRateExpected, $result);
    }

    public function testGetTaxRateForClientClientIsNotTaxable(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $clientServiceMock = $this->getMockBuilder(\Box\Mod\Client\Service::class)
            ->getMock();
        $clientServiceMock->expects($this->atLeastOnce())
            ->method('isClientTaxable')
            ->willReturn(false);

        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $clientServiceMock);
        $this->service->setDi($di);

        $taxRateExpected = 0;
        $result = $this->service->getTaxRateForClient($clientModel);
        $this->assertIsInt($result);
        $this->assertEquals($taxRateExpected, $result);
    }

    public function testGetTaxWhenTaxRateIsZero(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->taxrate = 0;

        $result = $this->service->getTax($invoiceModel);
        $this->assertIsInt($result);
        $this->assertEquals(0, $result);
    }

    public function testGetTax(): void
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $invoiceModel->taxrate = 15;

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \DummyBean());
        $invoiceItemModel->quantity = 1;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$invoiceItemModel]);

        $invoiceItemService = $this->createMock(ServiceInvoiceItem::class);
        $invoiceItemService->expects($this->atLeastOnce())
            ->method('getTax')
            ->willReturn(21);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $invoiceItemService);
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getTax($invoiceModel);
        $this->assertIsInt($result);
    }

    public function testDelete(): void
    {
        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $result = $this->service->delete($taxModel);
        $this->assertTrue($result);
    }

    public function testCreate(): void
    {
        $systemService = $this->createMock(\Box\Mod\System\Service::class);
        $systemService->expects($this->atLeastOnce())
            ->method('checkLimits');

        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($taxModel);
        $newId = 2;
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newId);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemService);
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $data = [
            'name' => 'tax',
            'taxrate' => '0.18',
        ];
        $result = $this->service->create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testUpdate(): void
    {
        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(2);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $data = [
            'name' => 'tax',
            'taxrate' => '0.18',
        ];
        $result = $this->service->update($taxModel, $data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGetSearchQuery(): void
    {
        $result = $this->service->getSearchQuery([]);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertSame([], $result[1]);
    }

    public function testToApiArray(): void
    {
        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->with($taxModel)
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->toApiArray($taxModel);
        $this->assertIsArray($result);
    }
}
