<?php
class Api_Guest_ServiceSolusvmTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'servicesolusvm.xml';

    public function testservicesolusvm()
    {
        $array = $this->api_guest->servicesolusvm_get_templates();
        $this->assertIsArray($array);
    }
}