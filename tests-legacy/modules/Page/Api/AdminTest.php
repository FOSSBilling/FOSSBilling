<?php

declare(strict_types=1);

namespace Box\Mod\Page\Api;

#[PHPUnit\Framework\Attributes\Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Admin $api;

    public function setUp(): void
    {
        $this->api = new Admin();
    }

    public function testGetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Page\Service::class)->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->get_pairs();
        $this->assertIsArray($result);
    }
}
