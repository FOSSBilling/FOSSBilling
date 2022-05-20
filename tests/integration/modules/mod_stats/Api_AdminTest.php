<?php
/**
 * @group Core
 */
class Api_Admin_StatsTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'orders.xml';
    
    public function testSummary()
    {
        $array = $this->api_admin->stats_get_summary();
        $this->assertIsArray($array);

        $array = $this->api_admin->stats_get_summary_income();
        $this->assertIsArray($array);

        $array = $this->api_admin->stats_get_orders_statuses();
        $this->assertIsArray($array);

        $array = $this->api_admin->stats_get_income_vs_refunds();
        $this->assertIsArray($array);
        
        $array = $this->api_admin->stats_get_product_summary();
        $this->assertIsArray($array);

        $array = $this->api_admin->stats_get_product_sales();
        $this->assertIsArray($array);

        $array = $this->api_admin->stats_client_countries();
        $this->assertIsArray($array);

        $array = $this->api_admin->stats_sales_countries();
        $this->assertIsArray($array);
    }

    public function testGraphs()
    {
        $array = $this->api_admin->stats_get_orders();
        $this->assertIsArray($array);
        
        $array = $this->api_admin->stats_get_clients();
        $this->assertIsArray($array);
        
        $array = $this->api_admin->stats_get_invoices();
        $this->assertIsArray($array);
        
        $array = $this->api_admin->stats_get_refunds();
        $this->assertIsArray($array);

        $array = $this->api_admin->stats_get_income();
        $this->assertIsArray($array);
        
        $array = $this->api_admin->stats_get_tickets();
        $this->assertIsArray($array);
    }
}