<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Client_ServiceDownloadableTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'services.xml';

    public function testServiceDownload(): void
    {
        $this->expectException(FOSSBilling\Exception::class);

        $data = [
            'order_id' => 1,
        ];
        $bool = $this->_callOnService('send_file', $data);
        $this->assertTrue($bool);
    }

    protected function _callOnService($method, $data)
    {
        $m = 'serviceDownloadable_' . $method;

        return $this->api_client->{$m}($data);
    }
}
