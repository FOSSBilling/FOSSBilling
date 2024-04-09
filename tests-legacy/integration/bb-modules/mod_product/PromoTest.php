<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Admin_PromoTest extends ApiTestCase
{
    public function testPromo(): void
    {
        $array = $this->api_admin->product_promo_get_list();
        $this->assertIsArray($array);
    }
}
