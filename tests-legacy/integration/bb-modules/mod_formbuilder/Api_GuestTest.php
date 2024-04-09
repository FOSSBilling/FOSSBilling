<?php

class Api_GUest_FormbuilderTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_formbuilder.xml';

    /**
     *  Box_Exception.
     */
    public function testFormGetNonExisting(): void
    {
        try {
            $this->api_guest->formbuilder_get(['id' => 'non-existing']);
            $this->fail('An expected exception has not been raised.');
        } catch (Box_Exception) {
        }

        try {
            $this->api_guest->formbuilder_get(['id' => 100000]);
            $this->fail('An expected exception has not been raised.');
        } catch (Box_Exception) {
        }
    }

    public function testGet(): void
    {
        $test = $this->api_guest->formbuilder_get(['id' => 2]);
        $this->assertIsArray($test);
        $this->assertNotEmpty($test);

        $test = $this->api_guest->formbuilder_get(['id' => '2']);
        $this->assertIsArray($test);
        $this->assertNotEmpty($test);
    }
}
