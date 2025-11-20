<?php

namespace Box\Mod\Stats;

class PdoMock extends \PDO
{
    public function __construct()
    {
    }
}
class PdoStatmentsMock extends \PDOStatement
{
    public function __construct()
    {
    }
}

class ServiceTest extends \BBTestCase
{
    /**
     * @var Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new Service();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetOrdersStatuses(): void
    {
        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('counter')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $result = $this->service->getOrdersStatuses([]);
        $this->assertIsArray($result);
    }

    public function testgetProductSummary(): void
    {
        $data = [];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getProductSummary($data);
        $this->assertIsArray($result);
    }

    public function testgetSummary(): void
    {
        $pdoStatmentMock = $this->getMockBuilder('\\' . PdoStatmentsMock::class)
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $expected = [
            'clients_total' => null,
            'clients_today' => null,
            'clients_yesterday' => null,
            'clients_this_month' => null,
            'clients_last_month' => null,

            'orders_total' => null,
            'orders_today' => null,
            'orders_yesterday' => null,
            'orders_this_month' => null,
            'orders_last_month' => null,

            'invoices_total' => null,
            'invoices_today' => null,
            'invoices_yesterday' => null,
            'invoices_this_month' => null,
            'invoices_last_month' => null,

            'tickets_total' => null,
            'tickets_today' => null,
            'tickets_yesterday' => null,
            'tickets_this_month' => null,
            'tickets_last_month' => null,
        ];
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchColumn');

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatmentMock);

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;
        $this->service->setDi($di);

        $result = $this->service->getSummary();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetSummaryIncome(): void
    {
        $pdoStatmentMock = $this->getMockBuilder('\\' . PdoStatmentsMock::class)
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchColumn');

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatmentMock);

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;
        $this->service->setDi($di);

        $expected = [
            'total' => null,
            'today' => null,
            'yesterday' => null,
            'this_month' => null,
            'last_month' => null,
        ];

        $result = $this->service->getSummaryIncome();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetProductSales(): void
    {
        $pdoStatmentMock = $this->getMockBuilder('\\' . PdoStatmentsMock::class)
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $res = [
            'testProduct' => 1,
        ];
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->willReturn($res);

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatmentMock);

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;
        $this->service->setDi($di);

        $data = [
            'date_from' => 'yesterday',
            'date_to' => 'now',
        ];
        $result = $this->service->getProductSales($data);
        $this->assertIsArray($result);
    }

    public function testincomeAndRefundStats(): void
    {
        $pdoStatmentMock = $this->getMockBuilder('\\' . PdoStatmentsMock::class)
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $res = [
            [
                'refund' => 0,
                'income' => 0,
            ],
        ];
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->willReturn($res);

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatmentMock);

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;
        $this->service->setDi($di);

        $result = $this->service->incomeAndRefundStats([]);
        $this->assertIsArray($result);
        $this->assertEquals($res[0], $result);
    }

    public function testgetRefunds(): void
    {
        $pdoStatmentMock = $this->getMockBuilder('\\' . PdoStatmentsMock::class)
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->willReturn([]);

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatmentMock);

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;

        $this->service->setDi($di);

        $data = [
            'date_from' => 'yesterday',
            'date_to' => 'now',
        ];
        $result = $this->service->getRefunds($data);
        $this->assertIsArray($result);
    }

    public function testgetIncome(): void
    {
        $pdoStatmentMock = $this->getMockBuilder('\\' . PdoStatmentsMock::class)
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->willReturn([]);

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatmentMock);

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;

        $this->service->setDi($di);

        $data = [
            'date_from' => 'yesterday',
            'date_to' => 'now',
        ];
        $result = $this->service->getIncome($data);
        $this->assertIsArray($result);
    }

    public function testgetClientCountries(): void
    {
        $pdoStatmentMock = $this->getMockBuilder('\\' . PdoStatmentsMock::class)
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->willReturn([]);

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatmentMock);

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;

        $this->service->setDi($di);

        $result = $this->service->getClientCountries([]);
        $this->assertIsArray($result);
    }

    public function testgetSalesByCountry(): void
    {
        $pdoStatmentMock = $this->getMockBuilder('\\' . PdoStatmentsMock::class)
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->willReturn([]);

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatmentMock);

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;

        $this->service->setDi($di);

        $result = $this->service->getSalesByCountry([]);
        $this->assertIsArray($result);
    }

    public function testgetTableStats(): void
    {
        $pdoStatmentMock = $this->getMockBuilder('\\' . PdoStatmentsMock::class)
            ->getMock();
        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('execute');

        $pdoStatmentMock->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->willReturn([]);

        $pdoMock = $this->getMockBuilder('\\' . PdoMock::class)->getMock();
        $pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->willReturn($pdoStatmentMock);

        $di = new \Pimple\Container();
        $di['pdo'] = $pdoMock;

        $this->service->setDi($di);

        $data = [
            'date_from' => 'yesterday',
            'date_to' => 'now',
        ];
        $result = $this->service->getTableStats('TableName', $data);
        $this->assertIsArray($result);
    }
}
