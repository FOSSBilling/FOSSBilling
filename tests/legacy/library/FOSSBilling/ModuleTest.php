<?php

declare(strict_types=1);

namespace FOSSBilling;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ModuleTest extends \BBTestCase
{
    public function testEmptyConfig(): void
    {
        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $db;

        $mod = new Module('api');
        $mod->setDi($di);
        $array = $mod->getConfig();
        $this->assertSame([], $array);
    }

    public function testCoreMod(): void
    {
        $mod = new Module('api');
        $this->assertTrue($mod->isCore());

        $array = $mod->getCoreModules();
        $this->assertIsArray($array);

        $mod = new Module('Cookieconsent');
        $this->assertFalse($mod->isCore());
    }

    public function testManifest(): void
    {
        $di = $this->getDi();
        $di['url'] = new \Box_Url();

        $mod = new Module('Cookieconsent');
        $mod->setDi($di);

        $bool = $mod->hasManifest();
        $this->assertTrue($bool);

        $array = $mod->getManifest();
        $this->assertIsArray($array);
    }

    public function testGetServiceSub(): void
    {
        $mod = new Module('Invoice');
        $subServiceName = 'transaction';

        $di = $this->getDi();
        $mod->setDi($di);

        $subService = $mod->getService($subServiceName);
        $this->assertInstanceOf(\Box\Mod\Invoice\ServiceTransaction::class, $subService);
    }
}
