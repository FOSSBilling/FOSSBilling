<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class FOSSBilling_ModuleTest extends PHPUnit\Framework\TestCase
{
    public function testEmptyConfig(): void
    {
        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = new Pimple\Container();
        $di['db'] = $db;

        $mod = new FOSSBilling\Module('api');
        $mod->setDi($di);
        $array = $mod->getConfig();
        $this->assertEquals([], $array);
    }

    public function testCoreMod(): void
    {
        $mod = new FOSSBilling\Module('api');
        $this->assertTrue($mod->isCore());

        $array = $mod->getCoreModules();
        $this->assertIsArray($array);

        $mod = new FOSSBilling\Module('Cookieconsent');
        $this->assertFalse($mod->isCore());
    }

    public function testManifest(): void
    {
        $di = new Pimple\Container();
        $di['url'] = new Box_Url();

        $mod = new FOSSBilling\Module('Cookieconsent');
        $mod->setDi($di);

        $bool = $mod->hasManifest();
        $this->assertTrue($bool);

        $array = $mod->getManifest();
        $this->assertIsArray($array);
    }

    public function testgetServiceSub(): void
    {
        $mod = new FOSSBilling\Module('Invoice');
        $subServiceName = 'transaction';

        $di = new Pimple\Container();
        $mod->setDi($di);

        $subService = $mod->getService($subServiceName);
        $this->assertInstanceOf(Box\Mod\Invoice\ServiceTransaction::class, $subService);
    }
}
