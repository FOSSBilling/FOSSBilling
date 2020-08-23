<?php
/**
 *
 */
class Api_GUest_FormbuilderTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_formbuilder.xml';

    /**
     *  Box_Exception
     */
    public function testFormGetNonExisting()
    {
        try {
            $this->api_guest->formbuilder_get(array("id" => "non-existing"));
            $this->fail('An expected exception has not been raised.');
        } catch (Box_Exception $e) {
        }
         try {
            $this->api_guest->formbuilder_get(array("id" => 100000));
            $this->fail('An expected exception has not been raised.');
        } catch (Box_Exception $e) {
        }

    }

    public function testGet()
    {
        $test = $this->api_guest->formbuilder_get(array("id" => 2));
        $this->assertIsArray($test);
        $this->assertNotEmpty($test);

        $test = $this->api_guest->formbuilder_get(array("id" => "2"));
        $this->assertIsArray($test);
        $this->assertNotEmpty($test);



    }

}