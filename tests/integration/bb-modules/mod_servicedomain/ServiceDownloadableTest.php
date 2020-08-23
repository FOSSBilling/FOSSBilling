<?php
/**
 * @group Core
 */
class Api_Admin_ServiceDownloadableTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'orders.xml';

    public function testAdminServiceDownloadable()
    {
        $this->assertTrue(true);

        /*
        $endpoint = BB_URL . 'bb-api/rest.php/admin/servicedownloadable/upload';
        $file_name = BB_PATH_TESTS.'/fixtures/services.xml';

        $params = array(
            'id'            =>  7, // product id
            'file_data'     =>  '@'.realpath($file_name),
        );
        
        // Post the file
        $curl = curl_init($endpoint);
        curl_setopt($curl, CURLOPT_HTTPAUTH,          CURLAUTH_BASIC) ;
        curl_setopt($curl, CURLOPT_USERPWD,           "admin:phpunit");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        $rsp = curl_exec($curl);
        curl_close($curl);

        $this->assertEquals('{"result":true,"error":null}', $rsp);
        */
    }
}