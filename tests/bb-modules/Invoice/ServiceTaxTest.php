<?php


namespace Box\Mod\Invoice;


class ServiceTaxTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Invoice\ServiceTax
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Invoice\ServiceTax();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetTaxRateForClientByCountryAndState()
    {
        $taxRateExpected = 0.21;
        $clientModel     = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')
            ->getMock();
        $clientServiceMock->expects($this->atLeastOnce())
            ->method('isClientTaxable')
            ->will($this->returnValue(true));

        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \RedBeanPHP\OODBBean());
        $taxModel->taxrate = $taxRateExpected;

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($taxModel));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($clientServiceMock) {
            return $clientServiceMock;
        });
        $di['db']          = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getTaxRateForClient($clientModel);
        $this->assertIsFloat($result);
        $this->assertEquals($taxRateExpected, $result);
    }

    public function testgetTaxRateForClientByCountry()
    {
        $taxRateExpected = 0.21;
        $clientModel     = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')
            ->getMock();
        $clientServiceMock->expects($this->atLeastOnce())
            ->method('isClientTaxable')
            ->will($this->returnValue(true));

        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \RedBeanPHP\OODBBean());
        $taxModel->taxrate = $taxRateExpected;

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->onConsecutiveCalls(null, $taxModel));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($clientServiceMock) {
            return $clientServiceMock;
        });
        $di['db']          = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getTaxRateForClient($clientModel);
        $this->assertIsFloat($result);
        $this->assertEquals($taxRateExpected, $result);
    }

    public function testgetTaxRateForClient()
    {
        $taxRateExpected = 0.21;
        $clientModel     = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')
            ->getMock();
        $clientServiceMock->expects($this->atLeastOnce())
            ->method('isClientTaxable')
            ->will($this->returnValue(true));

        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \RedBeanPHP\OODBBean());
        $taxModel->taxrate = $taxRateExpected;

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->onConsecutiveCalls(null, null, $taxModel));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($clientServiceMock) {
            return $clientServiceMock;
        });
        $di['db']          = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getTaxRateForClient($clientModel);
        $this->assertIsFloat($result);
        $this->assertEquals($taxRateExpected, $result);
    }

    public function testgetTaxRateForClient_TaxWasNotFound()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')
            ->getMock();
        $clientServiceMock->expects($this->atLeastOnce())
            ->method('isClientTaxable')
            ->will($this->returnValue(true));

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->onConsecutiveCalls(null, null, null));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($clientServiceMock) {
            return $clientServiceMock;
        });
        $di['db']          = $dbMock;
        $this->service->setDi($di);

        $taxRateExpected = 0;
        $result          = $this->service->getTaxRateForClient($clientModel);
        $this->assertIsInt($result);
        $this->assertEquals($taxRateExpected, $result);
    }

    public function testgetTaxRateForClient_ClientIsNotTaxable()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());

        $clientServiceMock = $this->getMockBuilder('\Box\Mod\Client\Service')
            ->getMock();
        $clientServiceMock->expects($this->atLeastOnce())
            ->method('isClientTaxable')
            ->will($this->returnValue(false));

        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \RedBeanPHP\OODBBean());

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($clientServiceMock) {
            return $clientServiceMock;
        });
        $this->service->setDi($di);

        $taxRateExpected = 0;
        $result          = $this->service->getTaxRateForClient($clientModel);
        $this->assertIsInt($result);
        $this->assertEquals($taxRateExpected, $result);
    }

    public function testgetTaxWhenTaxRateIsZero()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceModel->taxrate = 0;

        $result = $this->service->getTax($invoiceModel);
        $this->assertIsInt($result);
        $this->assertEquals(0, $result);
    }

    public function testgetTax()
    {
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceModel->taxrate = 15;

        $invoiceItemModel = new \Model_InvoiceItem();
        $invoiceItemModel->loadBean(new \RedBeanPHP\OODBBean());
        $invoiceItemModel->quantity = 1;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn(array($invoiceItemModel));

        $invoiceItemService = $this->getMockBuilder('\Box\Mod\Invoice\ServiceInvoiceItem')->getMock();
        $invoiceItemService->expects($this->atLeastOnce())
            ->method('getTax')
            ->willReturn(21);

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($invoiceItemService) { return $invoiceItemService; });
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getTax($invoiceModel);
        $this->assertIsInt($result);
    }

    public function testdelete()
    {
        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di           = new \Box_Di();
        $di['db']     = $dbMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);


        $result = $this->service->delete($taxModel);
        $this->assertTrue($result);
    }

    public function testcreate()
    {
        $systemService = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('checkLimits');

        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($taxModel));
        $newId = 2;
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($newId));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($systemService) {
            return $systemService;
        });
        $di['db']          = $dbMock;
        $di['logger']      = new \Box_Log();
        $this->service->setDi($di);

        $data   = array(
            'name'    => 'tax',
            'taxrate' => '0.18',
        );
        $result = $this->service->create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testUpdate()
    {
        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \RedBeanPHP\OODBBean());
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();

        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(2));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['logger']      = new \Box_Log();
        $this->service->setDi($di);

        $data   = array(
            'name'    => 'tax',
            'taxrate' => '0.18',
        );
        $result = $this->service->update($taxModel, $data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }


    public function testgetSearchQuery()
    {
        $result = $this->service->getSearchQuery(array());
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertEquals(array(), $result[1]);
    }

    public function testsetupEUTaxes()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');

        $systemService   = $this->getMockBuilder('\Box\Mod\System\Service')
            ->getMock();
        $euCountriesData = array(
            'AT' => 'Austria',
        );

        $euVatData = array(
            'AT' => 20,
        );

        $systemService->expects($this->atLeastOnce())
            ->method('getEuCountries')
            ->will($this->returnValue($euCountriesData));

        $systemService->expects($this->atLeastOnce())
            ->method('getEuVat')
            ->will($this->returnValue($euVatData));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Invoice\ServiceTax')
            ->setMethods(array('create'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('create');

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($systemService) {
            return $systemService;
        });
        $serviceMock->setDi($di);

        $result = $serviceMock->setupEUTaxes(array());
        $this->assertTrue($result);
    }

    public function testtoApiArray()
    {
        $taxModel = new \Model_Tax();
        $taxModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')
            ->with($taxModel)
            ->willReturn(array());

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->toApiArray($taxModel);
        $this->assertIsArray($result);
    }

}
 