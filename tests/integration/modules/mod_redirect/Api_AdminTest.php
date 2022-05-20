<?php
/**
 * @group Core
 */
class Box_Mod_Redirect_Api_AdminTest extends BBModTestCase
{
    protected $_mod = 'redirect';
    protected $_initialSeedFile = 'mod_redirect.xml';
    

    public function testRedirect()
    {
        $array = $this->api_admin->redirect_get_list();
        $this->assertIsArray($array);
        
        $int = $this->api_admin->redirect_create(array('path'=>'/forum', 'target'=>'new-forum'));
        $this->assertIsInt($int);
        
        $r = $this->api_admin->redirect_get(array('id'=>$int));
        $this->assertTrue(isset($r['id']));
        $this->assertTrue(isset($r['path']));
        $this->assertTrue(isset($r['target']));
        
        $bool = $this->api_admin->redirect_update(array('id'=>$int, 'target'=>'new-target'));
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->redirect_update(array('id'=>$int, 'path'=>'/new-path'));
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->redirect_delete(array('id'=>$int));
        $this->assertTrue($bool);
        
    }
}