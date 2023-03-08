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
        
        $mod = new Box_Mod('Cookieconsent');
        $this->assertFalse($mod->isCore());
    }
    
    public function testManifest()
    {
        $di = new Box_Di();
        $di['url'] = new Box_Url();

        $mod = new Box_Mod('Cookieconsent');
        $mod->setDi($di);

        $bool = $mod->hasManifest();
        $this->assertTrue($bool);

        $array = $mod->getManifest();
        $this->assertIsArray($array);
    }

    public function testgetServiceSub()
    {
        $mod = new Box_Mod('Invoice');
        $subServiceName = 'transaction';

        $subService = $mod->getService($subServiceName);
        $this->assertInstanceOf(\Box\Mod\Invoice\ServiceTransaction::class, $subService);

    }

}