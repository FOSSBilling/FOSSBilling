<?php

declare(strict_types=1);

namespace StatsTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    public function testStatsSummary(): void
    {
        $result = Request::makeRequest('admin/stats/get_summary');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testStatsSummaryIncome(): void
    {
        $result = Request::makeRequest('admin/stats/get_summary_income');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testStatsOrderStatus(): void
    {
        $result = Request::makeRequest('admin/stats/get_orders_statuses');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    public function testStatsProductSummary(): void
    {
        $result = Request::makeRequest('admin/stats/get_product_summary');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
    }

    /* TODO: SQLSTATE[42000]: Syntax error or access violation: 1055 Expression #1 of SELECT list is not in GROUP BY clause and contains nonaggregated column 'fossbilling.client_order.title' which is not functionally dependent on columns in GROUP BY clause; this is incompatible with sql_mode=only_full_group_by
    public function testStatsProductSales(): void
    {
        $result = Request::makeRequest('admin/stats/get_product_sales');
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
    }
    */
}
