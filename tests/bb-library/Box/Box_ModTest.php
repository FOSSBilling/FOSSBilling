<?php
/**
 * @group Core
 */
class Box_ModTest extends PHPUnit\Framework\TestCase
{
    public function testEmptyConfig()
    {
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $di = new Box_Di();
        $di['config'] = array('salt'=>'salt');
        $di['db'] = $db;

        $mod = new Box_Mod('api');
        $mod->setDi($di);
        $array = $mod->getConfig();
        $this->assertEquals(array(), $array);
    }

    public function testCoreMod()
    {
        $mod = new Box_Mod('api');
        $this->assertTrue($mod->isCore());
        
        $array = $mod->getCoreModules();
        $this->assertIsArray($array);
        
        $mod = new Box_Mod('example');
        $this->assertFalse($mod->isCore());
    }
    
    public function testExampleMod()
    {
        $mod = new Box_Mod('exaMple');

        $this->assertEquals('example', $mod->getName());

        $bool = $mod->hasService();
        $this->assertTrue($bool);

        $obj = $mod->getService();
        $this->assertInstanceOf(Box\Mod\Example\Service::class, $obj);

        $bool = $mod->hasAdminController();
        $this->assertTrue($bool);

        $bool = $mod->hasSettingsPage();
        $this->assertTrue($bool);

        $obj = $mod->getAdminController();
        $this->assertInstanceOf(Box\Mod\Example\Controller\Admin::class, $obj);

        $bool = $mod->hasClientController();
        $this->assertTrue($bool);

        $obj = $mod->getClientController();
        $this->assertInstanceOf(Box\Mod\Example\Controller\Client::class, $obj);
    }

    public function testManifest()
    {
        $di = new Box_Di();
        $di['url'] = new Box_Url();

        $mod = new Box_Mod('exaMplE');
        $mod->setDi($di);

        $bool = $mod->hasManifest();
        $this->assertTrue($bool);

        $array = $mod->getManifest();
        $this->assertIsArray($array);
    }

    public function testInstall()
    {
        $db_mock = $this->getMockBuilder('Box_Database')->getMock();
        $db_mock->expects($this->atLeastOnce())
            ->method('exec')
            ->will($this->returnValue(true));

        $di = new Box_Di();
        $di['db'] = $db_mock;
        $di['url'] = new Box_Url();

        $mod = new Box_Mod('example');
        $mod->setDi($di);

        $bool = $mod->install();
        $this->assertTrue($bool);

        $bool = $mod->uninstall();
        $this->assertTrue($bool);

        $bool = $mod->update();
        $this->assertTrue($bool);
    }

    public function testgetServiceSub()
    {
        $mod = new Box_Mod('Invoice');
        $subServiceName = 'transaction';

        $subService = $mod->getService($subServiceName);
        $this->assertInstanceOf(\Box\Mod\Invoice\ServiceTransaction::class, $subService);

    }

}