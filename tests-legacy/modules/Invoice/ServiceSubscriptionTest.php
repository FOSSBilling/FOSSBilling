<?php

namespace Box\Mod\Invoice;

class ServiceSubscriptionTest extends \BBTestCase
{
    /**
     * @var ServiceSubscription
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new ServiceSubscription();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testcreate(): void
    {
        $subscriptionModel = new \Model_Subscription();
        $subscriptionModel->loadBean(new \DummyBean());
        $newId = 10;

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($subscriptionModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newId);

        $eventsMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $eventsMock;

        $this->service->setDi($di);

        $data = [
            'client_id' => 1,
            'gateway_id' => 2,
        ];

        $result = $this->service->create(new \Model_Client(), new \Model_PayGateway(), $data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testupdate(): void
    {
        $subscriptionModel = new \Model_Subscription();
        $subscriptionModel->loadBean(new \DummyBean());
        $data = [
            'status' => '',
            'sid' => '',
            'period' => '',
            'amount' => '',
            'currency' => '',
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->update($subscriptionModel, $data);
        $this->assertTrue($result);
    }

    public function testtoApiArray(): void
    {
        $subscriptionModel = new \Model_Subscription();
        $subscriptionModel->loadBean(new \DummyBean());

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());

        $gatewayModel = new \Model_PayGateway();
        $gatewayModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturnOnConsecutiveCalls($clientModel, $gatewayModel);

        $clientServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)
            ->getMock();
        $clientServiceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $payGatewayService = $this->getMockBuilder('\\' . ServicePayGateway::class)
            ->getMock();
        $payGatewayService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($clientServiceMock, $payGatewayService) {
            if ($serviceName == 'Client') {
                return $clientServiceMock;
            }
            if ($sub == 'PayGateway') {
                return $payGatewayService;
            }
        });
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $expected = [
            'id' => '',
            'sid' => '',
            'period' => '',
            'amount' => '',
            'currency' => '',
            'status' => '',
            'created_at' => '',
            'updated_at' => '',
            'client' => [],
            'gateway' => [],
        ];

        $result = $this->service->toApiArray($subscriptionModel);
        $this->assertIsArray($result);
        $this->assertIsArray($result['client']);
        $this->assertIsArray($result['gateway']);
        $this->assertEquals($expected, $result);
    }

    public function testdelete(): void
    {
        $subscriptionModel = new \Model_Subscription();
        $subscriptionModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $eventsMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $eventsMock->expects($this->atLeastOnce())
            ->method('fire');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $eventsMock;
        $this->service->setDi($di);

        $result = $this->service->delete($subscriptionModel);
        $this->assertTrue($result);
    }

    public static function searchQueryData(): array
    {
        return [
            [
                [], 'FROM subscription', [],
            ],
            [
                ['status' => 'active'], 'AND status = :status', [':status' => 'active'],
            ],
            [
                ['invoice_id' => '1'], 'AND invoice_id = :invoice_id', ['invoice_id' => '1'],
            ],
            [
                ['gateway_id' => '2'], 'AND gateway_id = :gateway_id', [':gateway_id' => '2'],
            ],
            [
                ['client_id' => '3'], 'AND client_id  = :client_id', [':client_id' => '3'],
            ],
            [
                ['currency' => 'EUR'], 'AND currency =  :currency', [':currency' => 'EUR'],
            ],
            [
                ['date_from' => '1234567'], 'AND UNIX_TIMESTAMP(created_at) >= :date_from', [':date_from' => '1234567'],
            ],
            [
                ['date_to' => '1234567'], 'AND UNIX_TIMESTAMP(created_at) <= :date_to', [':date_to' => '1234567'],
            ],
            [
                ['id' => '10'], 'AND id = :id', [':id' => '10'],
            ],
            [
                ['sid' => '10'], 'AND sid = :sid', [':sid' => '10'],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('searchQueryData')]
    public function testgetSearchQuery(array $data, string $expectedSqlPart, array $expectedParams): void
    {
        $di = new \Pimple\Container();

        $this->service->setDi($di);
        $result = $this->service->getSearchQuery($data);

        $this->assertIsArray($result);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertEquals($expectedParams, $result[1]);
        $this->assertTrue(str_contains($result[0], $expectedSqlPart));
    }

    public function testisSubscribableisNotSusbcribable(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(['']);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $invoice_id = 2;
        $result = $this->service->isSubscribable($invoice_id);
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testisSubscribable(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(null);

        $getAllResults = [
            0 => ['period' => '1W'],
        ];
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($getAllResults);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $invoice_id = 2;
        $result = $this->service->isSubscribable($invoice_id);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgetSubscriptionPeriod(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . ServiceSubscription::class)
            ->onlyMethods(['isSubscribable'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('isSubscribable')
            ->willReturn(true);

        $period = '1W';
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn($period);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $invoiceModel = new \Model_Invoice();
        $invoiceModel->loadBean(new \DummyBean());

        $result = $serviceMock->getSubscriptionPeriod($invoiceModel);
        $this->assertIsString($result);
        $this->assertEquals($period, $result);
    }

    public function testunsubscribe(): void
    {
        $subscribtionModel = new \Model_Subscription();
        $subscribtionModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $this->service->unsubscribe($subscribtionModel);
    }
}
