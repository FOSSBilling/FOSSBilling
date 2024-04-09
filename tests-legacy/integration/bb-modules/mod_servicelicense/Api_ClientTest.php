<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Client_ServiceLicenseTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'services.xml';

    public function testServiceLicense(): void
    {
        $id = 2;

        $service = $this->api_client->order_service(['id' => $id]);

        $data = [
            'order_id' => $id,
        ];
        $bool = $this->_callOnService('reset', $data);
        $this->assertTrue($bool);
    }

    protected function _callOnService($method, $data)
    {
        $m = 'serviceLicense_' . $method;

        return $this->api_client->{$m}($data);
    }
}
