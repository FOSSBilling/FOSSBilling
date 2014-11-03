<?php
/**
 * @group Core
 */
class Api_Guest_ExtensionTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'extensions.xml';
    
    public function testLists()
    {
        $array = $this->api_guest->extension_languages();
        $this->assertInternalType('array', $array);

        $array = $this->api_guest->extension_theme();
        $this->assertInternalType('array', $array);

        $bool = $this->api_guest->extension_is_on(array('mod'=>'system'));
        $this->assertTrue($bool);

        $bool = $this->api_guest->extension_is_on(array('mod'=>'forum'));
        $this->assertTrue($bool);

        $arr = $this->api_guest->extension_settings(array('ext'=>'mod_email'));
        $this->assertInternalType('array', $arr);
    }
}