<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_ModTest extends PHPUnit\Framework\TestCase
{
    public function testEmptyConfig(): void
    {
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = new Pimple\Container();
        $di['db'] = $db;

        $mod = new Box_Mod('api');
        $mod->setDi($di);
        $array = $mod->getConfig();
        $this->assertEquals([], $array);
    }

    public function testCoreMod(): void
    {
        $mod = new Box_Mod('api');
        $this->assertTrue($mod->isCore());

        $array = $mod->getCoreModules();
        $this->assertIsArray($array);

        $mod = new Box_Mod('Cookieconsent');
        $this->assertFalse($mod->isCore());
    }

    public function testManifest(): void
    {
        $di = new Pimple\Container();
        $di['url'] = new Box_Url();

        $mod = new Box_Mod('Cookieconsent');
        $mod->setDi($di);

        $bool = $mod->hasManifest();
        $this->assertTrue($bool);

        $array = $mod->getManifest();
        $this->assertIsArray($array);
    }

    public function testgetServiceSub(): void
    {
        $mod = new Box_Mod('Invoice');
        $subServiceName = 'transaction';

        $di = new Pimple\Container();
        $mod->setDi($di);

        $subService = $mod->getService($subServiceName);
        $this->assertInstanceOf(Box\Mod\Invoice\ServiceTransaction::class, $subService);
    }
}
