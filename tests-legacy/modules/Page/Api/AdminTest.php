<?php

declare(strict_types=1);

namespace Box\Mod\Page\Api;
use PHPUnit\Framework\Attributes\DataProvider; 
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Admin $api;

    public function setUp(): void
    {
        $this->api = new Admin();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetPairs(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Page\Service::class);

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->get_pairs();
        $this->assertIsArray($result);
    }
}
