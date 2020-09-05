<?php


namespace Box\Mod\Stats\Api;


class AdminTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Stats\Api\Admin
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api= new \Box\Mod\Stats\Api\Admin();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testget_summary()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSummary')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $result = $this->api->get_summary();
        $this->assertIsArray($result);
    }

    public function testget_summary_income()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSummaryIncome')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $result = $this->api->get_summary_income();
        $this->assertIsArray($result);
    }

    public function testget_orders_statuses()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getOrdersStatuses')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $data = array();
        $result = $this->api->get_orders_statuses($data);
        $this->assertIsArray($result);
    }

    public function testget_product_summary()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductSummary')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $data = array();
        $result = $this->api->get_product_summary($data);
        $this->assertIsArray($result);
    }

    public function testget_product_sales()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getProductSales')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $data = array();
        $result = $this->api->get_product_sales($data);
        $this->assertIsArray($result);
    }

    public function testget_income_vs_refunds()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('incomeAndRefundStats')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $data = array();
        $result = $this->api->get_income_vs_refunds($data);
        $this->assertIsArray($result);
    }

    public function testget_refunds()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getRefunds')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $data = array();
        $result = $this->api->get_refunds($data);
        $this->assertIsArray($result);
    }

    public function testget_income()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getIncome')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $data = array();
        $result = $this->api->get_income($data);
        $this->assertIsArray($result);
    }

    public function testget_orders()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTableStats')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $data = array();
        $result = $this->api->get_orders($data);
        $this->assertIsArray($result);
    }

    public function testget_clients()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTableStats')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $data = array();
        $result = $this->api->get_clients($data);
        $this->assertIsArray($result);
    }

    public function testclient_countries()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getClientCountries')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $data = array();
        $result = $this->api->client_countries($data);
        $this->assertIsArray($result);
    }

    public function testsales_countries()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSalesByCountry')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $data = array();
        $result = $this->api->sales_countries($data);
        $this->assertIsArray($result);
    }

    public function testget_invoices()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTableStats')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $data = array();
        $result = $this->api->get_invoices($data);
        $this->assertIsArray($result);
    }

    public function testget_tickets()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Stats\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTableStats')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $data = array();
        $result = $this->api->get_tickets($data);
        $this->assertIsArray($result);
    }
}
 