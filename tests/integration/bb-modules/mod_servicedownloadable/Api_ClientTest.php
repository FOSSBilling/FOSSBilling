<?php
/**
 * @group Core
 */
class Api_Client_ServiceDownloadableTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'services.xml';

    public function testServiceDownload()
    {
        $this->expectException(\Box_Exception::class);

        $data = array(
            'order_id'    =>  1,
        );
        $bool = $this->_callOnService('send_file', $data);
        $this->assertTrue($bool);
    }

    protected function _callOnService($method, $data)
    {
        $m = "serviceDownloadable_".$method;
        return $this->api_client->{$m}($data);
    }
}