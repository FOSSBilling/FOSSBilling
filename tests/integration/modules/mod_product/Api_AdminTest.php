<?php
/**
 * @group Core
 */
class Api_Admin_ProductTest extends ApiTestCase
{
    public function testLists()
    {
        $list = $this->api_admin->product_get_list();
        $this->assertIsArray($list);

        $list = $this->api_admin->product_get_pairs();
        $this->assertIsArray($list);
        
        $list = $this->api_admin->product_get_types();
        $this->assertIsArray($list);

        $data = array(
            'id'    =>  10,
        );
        $array = $this->api_admin->product_get($data);
        $this->assertIsArray($array);
    }

    public function testProduct()
    {
        $data = array(
            'title'                 => 'title',
            'type'                  => Model_ProductTable::CUSTOM,
            'product_category_id'   => 1,
        );
        $id = $this->api_admin->product_prepare($data);
        $this->assertTrue(is_numeric($id));

        $data = array(
            'id'  =>  $id,
            'title'  =>  'new title',
        );
        $bool = $this->api_admin->product_update($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->product_update_config($data);
        $this->assertTrue($bool);
    }

    public function testAddons()
    {
        $array = $this->api_admin->product_addon_get_pairs();
        $this->assertIsArray($array);


        $data = array(
            'title'                 => 'title',
        );
        $id = $this->api_admin->product_addon_create($data);
        $this->assertTrue(is_numeric($id));

        $data = array(
            'id'  =>  $id,
            'title'  =>  'new title',
        );
        $array = $this->api_admin->product_addon_get($data);
        $this->assertIsArray($array);

        $bool = $this->api_admin->product_addon_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->product_addon_delete($data);
        $this->assertTrue($bool);
    }

    public function testCategory()
    {
        $array = $this->api_admin->product_category_get_pairs();
        $this->assertIsArray($array);

        $data = array(
            'title'                 => 'title',
        );
        $id = $this->api_admin->product_category_create($data);
        $this->assertTrue(is_numeric($id));

        $data = array(
            'id'                 => $id,
            'title'                 => 'title',
        );
        $array = $this->api_admin->product_category_get($data);
        $this->assertIsArray($array);

        $bool = $this->api_admin->product_category_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->product_category_delete($data);
        $this->assertTrue($bool);

    }

    public function testPromos()
    {
        $array = $this->api_admin->product_promo_get_list();
        $this->assertIsArray($array);

        $data = array(
            'code'                 => 'title',
            'type'                 => 'percent',
            'value'                 => '50',
        );
        $id = $this->api_admin->product_promo_create($data);
        $this->assertTrue(is_numeric($id));

        $data = array(
            'id'                 => $id,
            'value'                 => '25',
        );
        $array = $this->api_admin->product_promo_get($data);
        $this->assertIsArray($array);

        $bool = $this->api_admin->product_promo_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->product_promo_delete($data);
        $this->assertTrue($bool);

    }

    public function testProductGetList()
    {
        $array = $this->api_admin->product_get_list();
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
        $this->assertArrayHasKey('upgrades', $item);
        $this->assertIsArray($item['upgrades']);
        $this->assertArrayHasKey('status', $item);
        $this->assertArrayHasKey('hidden', $item);
        $this->assertArrayHasKey('setup', $item);
        $this->assertArrayHasKey('category', $item);
        $this->assertIsArray($item['category']);
    }

    public function testProductPromoGetList()
    {
        $array = $this->api_admin->product_promo_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        $item = $list[0];

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('code', $item);
        $this->assertArrayHasKey('description', $item);
        $this->assertArrayHasKey('type', $item);
        $this->assertArrayHasKey('value', $item);
        $this->assertArrayHasKey('maxuses', $item);
        $this->assertArrayHasKey('used', $item);
        $this->assertArrayHasKey('freesetup', $item);
        $this->assertArrayHasKey('once_per_client', $item);
        $this->assertArrayHasKey('recurring', $item);
        $this->assertArrayHasKey('active', $item);
        $this->assertArrayHasKey('products', $item);
        $this->assertArrayHasKey('periods', $item);
        $this->assertArrayHasKey('start_at', $item);
        $this->assertArrayHasKey('end_at', $item);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('updated_at', $item);
        $this->assertArrayHasKey('applies_to', $item);
        $this->assertIsArray($item['applies_to']);
    }


    public function testCreateTwoDomainProducts()
    {
        $data = array(
            'title'                 => 'Two domain product check_',
            'type'                  => Model_ProductTable::DOMAIN,
        );

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(413);
        $this->expectExceptionMessage('You have already created domain product.');


        for($i = 0; $i< 2; $i++){
            $id = $this->api_admin->product_prepare($data);
        }
    }
}