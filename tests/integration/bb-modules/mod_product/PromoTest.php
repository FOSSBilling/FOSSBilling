<?php
/**
 * @group Core
 */
class Api_Admin_PromoTest extends ApiTestCase
{
    public function testPromo()
    {
        $array = $this->api_admin->product_promo_get_list();
        $this->assertIsArray($array);
    }
}