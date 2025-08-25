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

        $mod = new FOSSBilling\Module($di, 'api');
        $array = $mod->getConfig();
        $this->assertEquals([], $array);
    }

    public function testCoreMod(): void
    {
        $di = new Pimple\Container();

        $mod = new FOSSBilling\Module($di, 'activity');
        $this->assertTrue($mod->isCore());

        $mod = new FOSSBilling\Module($di, 'Cookieconsent');
        $this->assertFalse($mod->isCore());
    }

    public function testManifest(): void
    {
        $di = new Pimple\Container();
        $di['url'] = new Box_Url();

        $mod = new FOSSBilling\Module($di, 'Cookieconsent');

        $array = $mod->getManifest();
        $this->assertIsArray($array);
    }

    public function testgetServiceSub(): void
    {
        $di = new Pimple\Container();
        $mod = new FOSSBilling\Module($di, 'Invoice');
        $subServiceName = 'transaction';

        $subService = $mod->getService($subServiceName);
        $this->assertInstanceOf(FOSSBilling\Module\Invoice\ServiceTransaction::class, $subService);
    }
}
