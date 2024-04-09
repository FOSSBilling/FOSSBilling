<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Admin_ThemeTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_theme.xml';

    public function testLists(): void
    {
        $code = 'boxbilling';
        $array = $this->api_admin->theme_get(['code' => $code]);
        $this->assertIsArray($array);
        $this->assertEquals($array['code'], $code);

        $array = $this->api_admin->theme_get_list();
        $this->assertIsArray($array);

        $bool = $this->api_admin->theme_select(['code' => 'boxbilling']);
        $this->assertTrue($bool);

        $array = $this->api_admin->theme_get_list(['type' => 'admin']);
        $this->assertIsArray($array);
    }

    /**
     * @expectedException \FOSSBilling\Exception
     */
    public function testThemeNotFound(): void
    {
        $this->api_admin->theme_get(['code' => 'non-existing-theme']);
    }

    public function testPresets(): void
    {
        $bool = $this->api_admin->theme_preset_select(['code' => 'boxbilling', 'preset' => 'Default']);
        $this->assertTrue($bool);

        $bool = $this->api_admin->theme_preset_delete(['code' => 'boxbilling', 'preset' => 'Default']);
        $this->assertTrue($bool);
    }
}
