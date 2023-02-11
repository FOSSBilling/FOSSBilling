<?php
/**
 * @group Core
 */
class Api_Guest_ServiceDomainTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'services.xml';
    
    public function testGuestServiceDomain()
    {
        $data = array(
            'sld'   =>  'phpunit',
            'tld'   =>  '.com',
        );
        $bool = $this->api_guest->servicedomain_check($data);
        $this->assertTrue($bool);

        $bool = $this->api_guest->servicedomain_can_be_transferred($data);
        $this->assertTrue($bool);

        $array = $this->api_guest->servicedomain_pricing($data);
        $this->assertIsArray($array);
    }

    public function testTlds()
    {
        $array = $this->api_guest->servicedomain_tlds();
        $this->assertIsArray($array);
    }
}