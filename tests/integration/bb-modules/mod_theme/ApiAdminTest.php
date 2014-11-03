<?php
/**
 * @group Core
 */
class Api_Admin_ThemeTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_theme.xml';
    
    public function testLists()
    {
        $code = 'boxbilling';
        $array = $this->api_admin->theme_get(array('code'=> $code));
        $this->assertInternalType('array', $array);
        $this->assertEquals($array['code'], $code);

        $array = $this->api_admin->theme_get_list();
        $this->assertInternalType('array', $array);

        $bool = $this->api_admin->theme_select(array('code'=>'boxbilling'));
        $this->assertTrue($bool);
        
        $array = $this->api_admin->theme_get_list(array('type'=>'admin'));
        $this->assertInternalType('array', $array);

    }

    /**
     * @expectedException \Box_Exception
     */
    public function testThemeNotFound()
    {
        $this->api_admin->theme_get(array('code'=> 'non-existing-theme'));
    }

    public function testPresets()
    {

        $bool = $this->api_admin->theme_preset_select(array('code'=>'boxbilling', 'preset'=>'Default'));
        $this->assertTrue($bool);

        $bool = $this->api_admin->theme_preset_delete(array('code'=>'boxbilling', 'preset'=>'Default'));
        $this->assertTrue($bool);

    }
}