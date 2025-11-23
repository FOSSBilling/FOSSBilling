<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Branding;

#[PHPUnit\Framework\Attributes\Group('Core')]
final class ServiceTest extends \BBTestCase
{
    public function testDi(): void
    {
        $service = new \Box\Mod\Branding\Service();

        $di = new \Pimple\Container();
        $db = $this->createMock('Box_Database');

        $di['db'] = $db;
        $service->setDi($di);
        $result = $service->getDi();
        $this->assertEquals($di, $result);
    }
}
