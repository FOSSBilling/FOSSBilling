<?php
/**
 * @group Core
 */
class Box_Mod_Filemanager_Api_AdminTest extends BBDbApiTestCase
{
    public function testActions()
    {
        $array = $this->api_admin->filemanager_get_list();
        $this->assertIsArray($array);
        $this->assertTrue(isset($array['filecount']));
        $this->assertTrue(isset($array['files']));
        
        unlink(BB_PATH_DATA.'/tmp.txt');
        $bool = $this->api_admin->filemanager_new_item(array('path'=>'bb-data/tmp.txt', 'type'=>'file'));
        $this->assertTrue($bool);
        
        rmdir(BB_PATH_DATA.'/new');
        $bool = $this->api_admin->filemanager_new_item(array('path'=>'bb-data/new', 'type'=>'dir'));
        $this->assertTrue($bool);
        
        rmdir(BB_PATH_DATA.'/new2');
        $bool = $this->api_admin->filemanager_new_item(array('path'=>BB_PATH_DATA.'/new2', 'type'=>'dir'));
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->filemanager_save_file(array('path'=>'bb-data/cache/tmp.txt', 'data'=>'content'));
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->filemanager_move_file(array('path'=>'bb-data/cache/tmp.txt', 'to'=>'bb-data/log'));
        $this->assertTrue($bool);
    }
}