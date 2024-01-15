<?php

namespace Box\Mod\Invoice;

class ServicePayGatewayTest extends \BBTestCase
{
    /**
     * @var ServicePayGateway
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new ServicePayGateway();
    }

    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetSearchQuery()
    {
        $di = new \Pimple\Container();

        $this->service->setDi($di);
        $data = [];
        $result = $this->service->getSearchQuery($data);
        $this->assertIsArray($result);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertEquals([], $result[1]);
    }

    public function testgetSearchQueryWithAdditionalParams()
    {
        $di = new \Pimple\Container();

        $this->service->setDi($di);
        $data = ['search' => 'keyword'];
        $expectedParams = [':search' => "%$data[search]%"];

        $result = $this->service->getSearchQuery($data);
        $this->assertIsArray($result);
        $this->assertIsString($result[0]);
        $this->assertTrue(strpos($result[0], 'AND name LIKE :search') > 0);
        $this->assertIsArray($result[1]);
        $this->assertEquals($expectedParams, $result[1]);
    }

    public function testgetPairs()
    {
        $expected = [
            1 => 'Custom',
        ];

        $queryResult = [
            [
                'id' => 1,
                'name' => 'Custom',
            ],
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($queryResult);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getPairs();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetAvailable()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getAvailable();
        $this->assertIsArray($result);
    }

    public function testinstallPayGateway()
    {
        $code = 'PP';

        $serviceMock = $this->getMockBuilder('\\' . ServicePayGateway::class)
            ->onlyMethods(['getAvailable'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAvailable')
            ->willReturn([$code]);

        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($payGatewayModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->install($code);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testinstallGatewayNotAvailable()
    {
        $code = 'PP';

        $serviceMock = $this->getMockBuilder('\\' . ServicePayGateway::class)
            ->onlyMethods(['getAvailable'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAvailable')
            ->willReturn([]);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Payment gateway is not available for installation.');
        $serviceMock->install($code);
    }

    public function testtoApiArray()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . ServicePayGateway::class)
            ->onlyMethods([
                'getAdapterConfig', 'getAcceptedCurrencies', 'getFormElements',
                'getDescription'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterConfig')
            ->willReturn([]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAcceptedCurrencies');
        $serviceMock->expects($this->atLeastOnce())
            ->method('getFormElements');
        $serviceMock->expects($this->atLeastOnce())
            ->method('getDescription');

        $expected = [
            'id' => null,
            'code' => null,
            'title' => null,
            'allow_single' => null,
            'allow_recurrent' => null,
            'accepted_currencies' => null,
            'supports_one_time_payments' => false,
            'supports_subscriptions' => false,
            'config' => [],
            'form' => null,
            'description' => null,
            'enabled' => null,
            'test_mode' => null,
            'callback' => 'http://localhost/ipn.php?',
        ];

        $di = new \Pimple\Container();

        $serviceMock->setDi($di);

        $result = $serviceMock->toApiArray($payGatewayModel, false, new \Model_Admin());
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testcopy()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($payGatewayModel);

        $expected = 2;
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($expected);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->copy($payGatewayModel);
        $this->assertIsInt($result);
        $this->assertEquals($expected, $result);
    }

    public function testupdate()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $data = [
            'title' => '',
            'config' => '',
            'accepted_currencies' => [],
            'enabled' => '',
            'allow_single' => '',
            'allow_recurrent' => '',
            'test_mode' => '',
        ];
        $result = $this->service->update($payGatewayModel, $data);
        $this->assertTrue($result);
    }

    public function testdelete()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->delete($payGatewayModel);
        $this->assertTrue($result);
    }

    public function testgetActive()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$payGatewayModel]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $data = ['format' => 'pairs'];
        $result = $this->service->getActive($data);
        $this->assertIsArray($result);
    }

    public function testcanPerformRecurrentPayment()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $expected = true;
        $payGatewayModel->allow_recurrent = $expected;

        $result = $this->service->canPerformRecurrentPayment($payGatewayModel);
        $this->assertIsBool($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetPaymentAdapter()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $expected = 'Payment_Adapter_Custom';

        $serviceMock = $this->getMockBuilder('\\' . ServicePayGateway::class)
            ->addMethods(['getConfig'])
            ->onlyMethods(['getAdapterClassName'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterClassName')
            ->willReturn($expected);

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->willReturn([]);

        $urlMock = $this->getMockBuilder('\Box_Url')->getMock();
        $urlMock->expects($this->atLeastOnce())
            ->method('link');

        $di = new \Pimple\Container();
        $di['tools'] = $toolsMock;
        $di['url'] = $urlMock;
        $serviceMock->setDi($di);

        $optional = [
            'auto_redirect' => '',
        ];
        $result = $serviceMock->getPaymentAdapter($payGatewayModel, $invoiceModel, $optional);
        $this->assertInstanceOf($expected, $result);
    }

    public function testgetPaymentAdapterPaymentGatewayNotFound()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . ServicePayGateway::class)
            ->addMethods(['getConfig'])
            ->onlyMethods(['getAdapterClassName'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterClassName')
            ->willReturn(null);

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('decodeJ')
            ->willReturn([]);

        $urlMock = $this->getMockBuilder('\Box_Url')->getMock();
        $urlMock->expects($this->atLeastOnce())
            ->method('link');

        $di = new \Pimple\Container();
        $di['tools'] = $toolsMock;
        $di['url'] = $urlMock;
        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Payment gateway  was not found');
        $serviceMock->getPaymentAdapter($payGatewayModel, $invoiceModel);
    }

    public function testgetAdapterConfig()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $payGatewayModel->gateway = 'Custom';

        $expected = '\Payment_Adapter_Custom';
        $serviceMock = $this->getMockBuilder('\\' . ServicePayGateway::class)
            ->addMethods(['getConfig'])
            ->onlyMethods(['getAdapterClassName'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterClassName')
            ->willReturn($expected);

        $result = $serviceMock->getAdapterConfig($payGatewayModel);
        $this->assertIsArray($result);
    }

    public function testgetAdapterConfigClassDoesNotExists()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $payGatewayModel->gateway = 'Custom';

        $expected = 'Payment_Adapter_ClassDoesNotExists';
        $serviceMock = $this->getMockBuilder('\\' . ServicePayGateway::class)
            ->addMethods(['getConfig'])
            ->onlyMethods(['getAdapterClassName'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterClassName')
            ->willReturn($expected);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Payment gateway class %s was not found', $expected));
        $serviceMock->getAdapterConfig($payGatewayModel);
    }

    public function testgetAdapterConfigAdapterDoesNotExists()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $payGatewayModel->gateway = 'Unknown';

        $serviceMock = $this->getMockBuilder('\\' . ServicePayGateway::class)
            ->addMethods(['getConfig'])
            ->onlyMethods(['getAdapterClassName'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterClassName');

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Payment gateway %s was not found', $payGatewayModel->gateway));
        $serviceMock->getAdapterConfig($payGatewayModel);
    }

    public function testgetAdapterClassName()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $payGatewayModel->gateway = 'Custom';

        $expected = 'Payment_Adapter_Custom';

        $result = $this->service->getAdapterClassName($payGatewayModel);
        $this->assertIsString($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetAcceptedCurrencies()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $payGatewayModel->accepted_currencies = '{}';

        $result = $this->service->getAcceptedCurrencies($payGatewayModel);
        $this->assertIsArray($result);
    }

    public function testgetFormElements()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . ServicePayGateway::class)
            ->onlyMethods(['getAdapterConfig'])
            ->getMock();
        $config = ['form' => []];
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterConfig')
            ->willReturn($config);

        $result = $serviceMock->getFormElements($payGatewayModel);
        $this->assertIsArray($result);
    }

    public function testgetFormElementsEmptyFormConfig()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . ServicePayGateway::class)
            ->onlyMethods(['getAdapterConfig'])
            ->getMock();
        $config = [];
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterConfig')
            ->willReturn($config);

        $result = $serviceMock->getFormElements($payGatewayModel);
        $this->assertIsArray($result);
        $emptyArray = [];
        $this->assertEquals($emptyArray, $result);
    }

    public function testgetDescription()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . ServicePayGateway::class)
            ->onlyMethods(['getAdapterConfig'])
            ->getMock();
        $config = ['description' => ''];
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterConfig')
            ->willReturn($config);

        $result = $serviceMock->getDescription($payGatewayModel);
        $this->assertIsString($result);
    }

    public function testgetDescriptionEmptyDescription()
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . ServicePayGateway::class)
            ->onlyMethods(['getAdapterConfig'])
            ->getMock();
        $config = [];
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterConfig')
            ->willReturn($config);

        $result = $serviceMock->getDescription($payGatewayModel);
        $this->assertNull($result);
    }
}
