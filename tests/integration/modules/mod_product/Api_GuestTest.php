<?php
/**
 * @group Core
 */
class Api_Guest_ProductTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'orders.xml';
    
    public function testGetAll()
    {
        $list = $this->api_guest->product_get_pairs();
        $this->assertIsArray($list);

        $list = $this->api_guest->product_get_list();
        $this->assertIsArray($list);
        
        $list = $this->api_guest->product_category_get_pairs();
        $this->assertIsArray($list);

        $data = array(
            'id'    =>  10,
        );
        $list = $this->api_guest->product_get($data);
        $this->assertIsArray($list);
        
        $list = $this->api_guest->product_get_slider($data);
        $this->assertIsArray($list);
    }

    public function testCategoryGetList()
    {
        $pager = $this->api_guest->product_category_get_list();
        $this->assertIsArray($pager);
        $this->assertArrayHasKey('list', $pager);

        $list = $pager['list'];
        $this->assertIsArray($list);

        $item = $list[0];
        $this->assertArrayHasKey('price_starting_from', $item);
        $this->assertArrayHasKey('icon_url', $item);
        $this->assertArrayHasKey('type', $item);
        $this->assertArrayHasKey('products', $item);
        $this->assertIsArray($item['products']);
    }

    /*
    public function testBenchmark()
    {
        $timer = new Benchmark_Timer();
        $timer->start();
        $list = $this->api_guest->product_get_categories();
        $timer->stop();
        $timer->display();
    }
    */

    public function testProductGetList()
    {
        $array = $this->api_guest->product_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        $item = $list[0];

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('product_category_id', $item);
        $this->assertArrayHasKey('type', $item);
        $this->assertArrayHasKey('title', $item);
        $this->assertArrayHasKey('form_id', $item);
        $this->assertArrayHasKey('slug', $item);
        $this->assertArrayHasKey('description', $item);
        $this->assertArrayHasKey('unit', $item);
        $this->assertArrayHasKey('priority', $item);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('updated_at', $item);
        $this->assertArrayHasKey('pricing', $item);

        $pricing = $item['pricing'];
        $this->assertIsArray($item['pricing']);
        $this->assertArrayHasKey('type', $pricing);
        $this->assertArrayHasKey('free', $pricing);
        $this->assertArrayHasKey('once', $pricing);
        $this->assertArrayHasKey('recurrent', $pricing);

        $this->assertArrayHasKey('config', $item);
        $this->assertIsArray($item['config']);

        $this->assertArrayHasKey('addons', $item);
        $this->assertIsArray($item['addons']);

        $this->assertArrayHasKey('price_starting_from', $item);
        $this->assertArrayHasKey('icon_url', $item);
        $this->assertArrayHasKey('allow_quantity_select', $item);
        $this->assertArrayHasKey('quantity_in_stock', $item);
        $this->assertArrayHasKey('stock_control', $item);

        $this->assertArrayNotHasKey('upgrades', $item);
        $this->assertArrayNotHasKey('status', $item);
        $this->assertArrayNotHasKey('hidden', $item);
        $this->assertArrayNotHasKey('setup', $item);
        $this->assertArrayNotHasKey('category', $item);
    }

    public function testProduct_StartingFromPrice_DomainType()
    {
        $array = $this->api_guest->product_get(array('id' => 10));
        $this->assertTrue($array['price_starting_from'] > 0);
    }

}