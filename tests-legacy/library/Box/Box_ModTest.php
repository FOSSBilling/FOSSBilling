<?php

declare(strict_types=1);

#[Group('Core')]
final class Box_ModTest extends \BBTestCase
{
    public function testEmptyConfig(): void
    {
        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $db;

        $mod = new Box_Mod('api');
        $mod->setDi($di);
        $array = $mod->getConfig();
        $this->assertSame([], $array);
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
        $di = $this->getDi();
        $di['url'] = new Box_Url();

        $mod = new Box_Mod('Cookieconsent');
        $mod->setDi($di);

        $bool = $mod->hasManifest();
        $this->assertTrue($bool);

        $array = $mod->getManifest();
        $this->assertIsArray($array);
    }

    public function testGetServiceSub(): void
    {
        $mod = new Box_Mod('Invoice');
        $subServiceName = 'transaction';

        $di = $this->getDi();
        $mod->setDi($di);

        $subService = $mod->getService($subServiceName);
        $this->assertInstanceOf(Box\Mod\Invoice\ServiceTransaction::class, $subService);
    }
}
