<?php
class Box_Mod_Page_Api_AdminTest extends BBModTestCase
{
    protected $_mod = 'page';
    protected $_initialSeedFile = 'mod_page.xml';

    public function testMod()
    {
        $array = $this->api_admin->page_get_pairs();
        $this->assertIsArray($array);
    }
}