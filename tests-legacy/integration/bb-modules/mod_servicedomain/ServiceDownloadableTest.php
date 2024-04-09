<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Admin_ServiceDownloadableTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'orders.xml';

    public function testAdminServiceDownloadable(): void
    {
        $this->assertTrue(true);

        /*
        $endpoint = SYSTEM_URL . 'bb-api/rest.php/admin/servicedownloadable/upload';
        $file_name = PATH_TESTS.'/fixtures/services.xml';

        $params = array(
            'id'            =>  7, // product id
            'file_data'     =>  '@'.realpath($file_name),
        );

        // @TODO Post the file

        $this->assertEquals('{"result":true,"error":null}', $rsp);
        */
    }
}
