<?php

namespace Box\Mod\Stats\Api;

class AdminTest extends \BBTestCase
{
    /**
     * @var Admin
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Admin();
    }

    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetSummary()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSummary')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->get_summary();
        $this->assertIsArray($result);
    }

    public function testgetSummaryIncome()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSummaryIncome')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->get_summary_income();
        $this->assertIsArray($result);
    }

    public function testgetOrdersStatuses()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrdersStatuses')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->get_orders_statuses($data);
        $this->assertIsArray($result);
    }

    public function testgetProductSummary()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductSummary')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->get_product_summary($data);
        $this->assertIsArray($result);
    }

    public function testgetProductSales()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductSales')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->get_product_sales($data);
        $this->assertIsArray($result);
    }

    public function testgetIncomeVsRefunds()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('incomeAndRefundStats')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->get_income_vs_refunds($data);
        $this->assertIsArray($result);
    }

    public function testgetRefunds()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getRefunds')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->get_refunds($data);
        $this->assertIsArray($result);
    }

    public function testgetIncome()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getIncome')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->get_income($data);
        $this->assertIsArray($result);
    }

    public function testgetOrders()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTableStats')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->get_orders($data);
        $this->assertIsArray($result);
    }

    public function testgetClients()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTableStats')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->get_clients($data);
        $this->assertIsArray($result);
    }

    public function testclientCountries()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getClientCountries')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->client_countries($data);
        $this->assertIsArray($result);
    }

    public function testsalesCountries()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSalesByCountry')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->sales_countries($data);
        $this->assertIsArray($result);
    }

    public function testgetInvoices()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTableStats')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->get_invoices($data);
        $this->assertIsArray($result);
    }

    public function testgetTickets()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTableStats')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $data = [];
        $result = $this->api->get_tickets($data);
        $this->assertIsArray($result);
    }
}
