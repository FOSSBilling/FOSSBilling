<?php
/**
 * @group Core
 */
class Api_Admin_ExtensionTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'extensions.xml';
    
    public function testSetup()
    {
        $data = array(
            'code' => 'extension',
        );
        
        try {
            //do not allow install core extension
            $bool = $this->api_admin->extension_mod_install($data);
            $this->fail('Core extension can not be installed');
        } catch (Box_Exception $e) {
            $this->assertTrue(TRUE);
        }

        try {
            //do not allow uninstall core extension
            $bool = $this->api_admin->extension_mod_uninstall($data);
            $this->fail('Core extension can not be uninstalled');
        } catch (Box_Exception $e) {
            $this->assertTrue(TRUE);
        }
    }

    public function testUninstall()
    {
        $data = array(
            'type'  =>  'mod',
            'id'    =>  'news',
        );
        $bool = $this->api_admin->extension_uninstall($data);

        $this->assertTrue($bool);
    }
    
    public function testUpdate()
    {
        $versions = array(
            'version_old' => '1.1.1',
            'version_new' => '2.2.2',
        );
        $extension =  new Model_Extension();
        $extension->loadBean(new RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->setMethods(array('update', 'findExtension'))->getMock();
        $serviceMock->expects($this->any())
            ->method('update')
            ->will($this->returnValue($versions));
        $serviceMock->expects($this->any())
            ->method('findExtension')
            ->will($this->returnValue($extension));

        $serviceMock->setDi($this->di);

        $data = array(
            'type'  =>  'mod',
            'id'    =>  'branding',
        );
        $api = new \Box\Mod\Extension\Api\Admin();
        $api->setDi($this->di);
        $api->setService($serviceMock);
        $arr = $api->update($data);
        $this->assertEquals($arr, $versions);
    }
    
    public function testConfigs()
    {
        $data = array(
            'ext'           =>  'mod_email',
            'mailer'        =>  'mail',
            'smtp_host'     =>  'hostname',
            'smtp_port'     =>  55,
            'smtp_username' =>  'username',
            'smtp_password' =>  'sasd132423%3@#//',
            'smtp_security' =>  'yes',
        );
        
        $bool = $this->api_admin->extension_config_save($data);
        $this->assertTrue($bool);
        
        $array = $this->api_admin->extension_config_get($data);
        $array['ext'] = 'mod_email';
        $this->assertIsArray($array);
        $this->assertEquals($data, $array);
    }
    
    public function testActivations()
    {
        $data = array(
            'id'    =>  'news',
            'type'  =>  'mod',
        );
        $array = $this->api_admin->extension_activate($data);
        $this->assertIsArray($array);
        $bool = $this->api_guest->extension_is_on($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->extension_deactivate($data);
        $this->assertTrue($bool);
        $bool = $this->api_guest->extension_is_on($data);
        $this->assertFalse($bool);
    }
    
    public function testLists()
    {
        $data = array(
            'type' => 'server-manager',
        );
        $array = $this->api_admin->extension_get_list($data);
        $this->assertIsArray($array);
        
        $array = $this->api_admin->extension_get_list(array('active'=>true, 'type'=>'mod', 'search'=>'f'));
        $this->assertIsArray($array);
        
        $array = $this->api_admin->extension_get_list(array('installed'=>true));
        $this->assertIsArray($array);
        
        $array = $this->api_admin->extension_get_list(array('has_settings'=>true));
        $this->assertIsArray($array);
        
        $array = $this->api_admin->extension_get_latest();
        $this->assertIsArray($array);
        
        $array = $this->api_admin->extension_get_navigation();
        $this->assertIsArray($array);
        
        $array = $this->api_admin->extension_languages();
        $this->assertIsArray($array);
        
        $array = $this->api_admin->extension_languages();
        $this->assertIsArray($array);
    }

    public function testUpdate_Core()
    {
        $bool = $this->api_admin->extension_update_core();
        $this->assertTrue($bool);
    }

    public function testInstall(){

        $extension =  new Model_Extension();
        $extension->loadBean(new RedBeanPHP\OODBBean());

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->setMethods(array('downloadAndExtract'))->getMock();
        $serviceMock->expects($this->any())
            ->method('downloadAndExtract')
            ->will($this->returnValue(true));

        $serviceMock->setDi($this->di);

        $data = array(
            'id'    =>  'branding',
            'type'  =>  'mod',
        );
        $api = new \Box\Mod\Extension\Api\Admin();
        $api->setService($serviceMock);
        $api->setDi($this->di);
        $arr = $api->install($data);

        $expected = $data;
        $expected['success'] = true;
        $this->assertEquals($arr, $expected);
    }


}