<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Admin_ServiceLicenseTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'services.xml';

    public function testAdminServiceLicense(): void
    {
        $array = $this->api_admin->servicelicense_plugin_get_pairs();
        $this->assertIsArray($array);

        $data = [
            'order_id' => 2,
            'validate_ip' => 0,
            'validate_host' => 0,
            'validate_version' => 0,
            'validate_path' => 0,
            'versions' => '1' . PHP_EOL . '2',
        ];
        $bool = $this->api_admin->servicelicense_update($data);
        $this->assertTrue($bool);

        $service = $this->api_admin->order_service(['id' => 2]);
        $this->assertEquals(2, count($service['versions']));
    }
}
