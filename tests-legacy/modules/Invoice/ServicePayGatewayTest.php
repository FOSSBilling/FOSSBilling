<?php

declare(strict_types=1);

namespace Box\Mod\Invoice;
use PHPUnit\Framework\Attributes\DataProvider; 
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServicePayGatewayTest extends \BBTestCase
{
    protected ?ServicePayGateway $service;

    public function setUp(): void
    {
        $this->service = new ServicePayGateway();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetSearchQuery(): void
    {
        $di = $this->getDi();

        $this->service->setDi($di);
        $data = [];
        $result = $this->service->getSearchQuery($data);
        $this->assertIsArray($result);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertSame([], $result[1]);
    }

    public function testGetSearchQueryWithAdditionalParams(): void
    {
        $di = $this->getDi();

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

    public function testGetPairs(): void
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

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($queryResult);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getPairs();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetAvailable(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getAvailable();
        $this->assertIsArray($result);
    }

    public function testInstallPayGateway(): void
    {
        $code = 'PP';

        $serviceMock = $this->getMockBuilder(ServicePayGateway::class)
            ->onlyMethods(['getAvailable'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAvailable')
            ->willReturn([$code]);

        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($payGatewayModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->install($code);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testInstallGatewayNotAvailable(): void
    {
        $code = 'PP';

        $serviceMock = $this->getMockBuilder(ServicePayGateway::class)
            ->onlyMethods(['getAvailable'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAvailable')
            ->willReturn([]);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Payment gateway is not available for installation.');
        $serviceMock->install($code);
    }

    public function testToApiArray(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(ServicePayGateway::class)
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
            'accepted_currencies' => [],
            'supports_one_time_payments' => false,
            'supports_subscriptions' => false,
            'config' => [],
            'form' => [],
            'description' => null,
            'enabled' => null,
            'test_mode' => null,
            'callback' => 'https://localhost/ipn.php?',
        ];

        $di = $this->getDi();

        $serviceMock->setDi($di);

        $result = $serviceMock->toApiArray($payGatewayModel, false, new \Model_Admin());
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testCopy(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($payGatewayModel);

        $expected = 2;
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($expected);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->copy($payGatewayModel);
        $this->assertIsInt($result);
        $this->assertEquals($expected, $result);
    }

    public function testUpdate(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
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

    public function testDelete(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->delete($payGatewayModel);
        $this->assertTrue($result);
    }

    public function testGetActive(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$payGatewayModel]);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $data = ['format' => 'pairs'];
        $result = $this->service->getActive($data);
        $this->assertIsArray($result);
    }

    public function testCanPerformRecurrentPayment(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $expected = true;
        $payGatewayModel->allow_recurrent = $expected;

        $result = $this->service->canPerformRecurrentPayment($payGatewayModel);
        $this->assertIsBool($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetPaymentAdapter(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());
        $expected = 'Payment_Adapter_Custom';

        $serviceMock = $this->getMockBuilder(ServicePayGateway::class)
            ->onlyMethods(['getAdapterClassName'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterClassName')
            ->willReturn($expected);

        $urlMock = $this->createMock('\Box_Url');
        $urlMock->expects($this->atLeastOnce())
            ->method('link');

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);

        $di = $this->getDi();
        $di['url'] = $urlMock;
        $di['tools'] = $toolsMock;
        $serviceMock->setDi($di);

        $optional = [
            'auto_redirect' => '',
        ];
        $result = $serviceMock->getPaymentAdapter($payGatewayModel, $invoiceModel, $optional);
        $this->assertInstanceOf($expected, $result);
    }

    public function testGetPaymentAdapterPaymentGatewayNotFound(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(ServicePayGateway::class)
            ->onlyMethods(['getAdapterClassName'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterClassName')
            ->willReturn('');

        $urlMock = $this->createMock('\Box_Url');
        $urlMock->expects($this->atLeastOnce())
            ->method('link');

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);

        $di = $this->getDi();
        $di['url'] = $urlMock;
        $di['tools'] = $toolsMock;
        $serviceMock->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Payment gateway  was not found');
        $serviceMock->getPaymentAdapter($payGatewayModel, $invoiceModel);
    }

    public function testGetAdapterConfig(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $payGatewayModel->gateway = 'Custom';

        $expected = '\Payment_Adapter_Custom';
        $serviceMock = $this->getMockBuilder(ServicePayGateway::class)
            ->onlyMethods(['getAdapterClassName'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterClassName')
            ->willReturn($expected);

        $result = $serviceMock->getAdapterConfig($payGatewayModel);
        $this->assertIsArray($result);
    }

    public function testGetAdapterConfigClassDoesNotExists(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $payGatewayModel->gateway = 'Custom';

        $expected = 'Payment_Adapter_ClassDoesNotExists';
        $serviceMock = $this->getMockBuilder(ServicePayGateway::class)
            ->onlyMethods(['getAdapterClassName'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterClassName')
            ->willReturn($expected);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Payment gateway class %s was not found', $expected));
        $serviceMock->getAdapterConfig($payGatewayModel);
    }

    public function testGetAdapterConfigAdapterDoesNotExists(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $payGatewayModel->gateway = 'Unknown';

        $serviceMock = $this->getMockBuilder(ServicePayGateway::class)
            ->onlyMethods(['getAdapterClassName'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterClassName');

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Payment gateway %s was not found', $payGatewayModel->gateway));
        $serviceMock->getAdapterConfig($payGatewayModel);
    }

    public function testGetAdapterClassName(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $payGatewayModel->gateway = 'Custom';

        $expected = 'Payment_Adapter_Custom';

        $result = $this->service->getAdapterClassName($payGatewayModel);
        $this->assertIsString($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetAcceptedCurrencies(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());
        $payGatewayModel->accepted_currencies = '{}';

        $result = $this->service->getAcceptedCurrencies($payGatewayModel);
        $this->assertIsArray($result);
    }

    public function testGetFormElements(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(ServicePayGateway::class)
            ->onlyMethods(['getAdapterConfig'])
            ->getMock();
        $config = ['form' => []];
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterConfig')
            ->willReturn($config);

        $result = $serviceMock->getFormElements($payGatewayModel);
        $this->assertIsArray($result);
    }

    public function testGetFormElementsEmptyFormConfig(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(ServicePayGateway::class)
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

    public function testGetDescription(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(ServicePayGateway::class)
            ->onlyMethods(['getAdapterConfig'])
            ->getMock();
        $config = ['description' => ''];
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdapterConfig')
            ->willReturn($config);

        $result = $serviceMock->getDescription($payGatewayModel);
        $this->assertIsString($result);
    }

    public function testGetDescriptionEmptyDescription(): void
    {
        $payGatewayModel = new \Model_PayGateway();
        $payGatewayModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(ServicePayGateway::class)
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
