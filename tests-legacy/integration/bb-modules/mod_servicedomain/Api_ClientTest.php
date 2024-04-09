<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Client_ServiceDomainTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'services.xml';

    public static function orders()
    {
        return [
            [3],
            [4],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('orders')]
    public function testClientServiceDomain($id): void
    {
        $this->api_admin->order_renew(['id' => $id]);

        $data = [
            'order_id' => $id,
            'ns1' => 'ns1.1freehosting.com',
            'ns2' => 'ns2.1freehosting.com',
            'ns3' => 'ns3.1freehosting.com',
            'ns4' => 'ns4.1freehosting.com',
        ];
        $bool = $this->api_client->serviceDomain_update_nameservers($data);
        $this->assertTrue($bool);

        $data = [
            'order_id' => $id,
            'contact' => [
                'first_name' => 'John',
                'last_name' => 'Does',
                'email' => 'email@example.com  ',
                'company' => 'Company',
                'address1' => 'Adress 1',
                'address2' => 'Adress 2',
                'country' => 'US',
                'city' => 'San Jose',
                'state' => 'n/a',
                'postcode' => '20506',
                'phone_cc' => '1408',
                'phone' => '123456',
            ],
        ];
        $bool = $this->_callOnService('update_contacts', $data);
        $this->assertTrue($bool, 'Failed updating contacts');

        $bool = $this->_callOnService('get_transfer_code', $data);
        $this->assertTrue($bool, 'Getting epp code');

        $bool = $this->_callOnService('enable_privacy_protection', $data);
        $this->assertTrue($bool, 'Failed Enabling privacy protection');

        $bool = $this->_callOnService('disable_privacy_protection', $data);
        $this->assertTrue($bool, 'Failed Disabling privacy protection');

        $bool = $this->_callOnService('lock', $data);
        $this->assertTrue($bool);

        $bool = $this->_callOnService('unlock', $data);
        $this->assertTrue($bool);
    }

    protected function _callOnService($method, $data)
    {
        $m = 'serviceDomain_' . $method;

        return $this->api_client->{$m}($data);
    }
}
