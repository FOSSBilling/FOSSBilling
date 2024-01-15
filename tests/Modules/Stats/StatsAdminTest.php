<?php

declare(strict_types=1);

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class StatsAdminTest extends TestCase
{
    public function testStatsSummary(): void
    {
        $response = Request::makeRequest('admin/stats/get_summary');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertIsArray($response->getResult());
    }

    public function testStatsSummaryIncome(): void
    {
        $response = Request::makeRequest('admin/stats/get_summary_income');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertIsArray($response->getResult());
    }

    public function testStatsOrderStatus(): void
    {
        $response = Request::makeRequest('admin/stats/get_orders_statuses');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertIsArray($response->getResult());
    }

    public function testStatsProductSummary(): void
    {
        $response = Request::makeRequest('admin/stats/get_product_summary');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertIsArray($response->getResult());
    }

    public function testStatsProductSales(): void
    {
        $response = Request::makeRequest('admin/stats/get_product_sales');
        $this->assertTrue($response->wasSuccessful(), $response->generatePHPUnitMessage());
        $this->assertIsArray($response->getResult());
    }
}
