<?php

declare(strict_types=1);

namespace Box\Mod\Stats\Api;

#[PHPUnit\Framework\Attributes\Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Admin $api;

    public function setUp(): void
    {
        $this->api = new Admin();
    }

    public function testGetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetSummary(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSummary')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->get_summary();
        $this->assertIsArray($result);
    }

    public function testGetSummaryIncome(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Stats\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSummaryIncome')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->get_summary_income();
        $this->assertIsArray($result);
    }

    public function testGetOrdersStatuses(): void
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

    public function testGetProductSummary(): void
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

    public function testGetProductSales(): void
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

    public function testGetIncomeVsRefunds(): void
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

    public function testGetRefunds(): void
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

    public function testGetIncome(): void
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

    public function testGetOrders(): void
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

    public function testGetClients(): void
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

    public function testClientCountries(): void
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

    public function testSalesCountries(): void
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

    public function testGetInvoices(): void
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

    public function testGetTickets(): void
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
