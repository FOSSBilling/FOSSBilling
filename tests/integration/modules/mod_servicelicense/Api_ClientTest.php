<?php
/**
 * @group Core
 */
class Api_Client_ServiceLicenseTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'services.xml';

    public function testServiceLicense()
    {
        $id = 2;

        $service = $this->api_client->order_service(array('id'=>$id));

        $data = array(
            'order_id'    =>  $id,
        );
        $bool = $this->_callOnService('reset', $data);
        $this->assertTrue($bool);
    }

    protected function _callOnService($method, $data)
    {
        $m = "serviceLicense_".$method;
        return $this->api_client->{$m}($data);
    }
}