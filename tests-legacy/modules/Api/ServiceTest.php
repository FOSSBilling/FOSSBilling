<?php

declare(strict_types=1);

namespace Box\Mod\Api;
use PHPUnit\Framework\Attributes\DataProvider; 
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;

    public function setUp(): void
    {
        $this->service = new Service();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetRequestCount(): void
    {
        $since = 674_690_401; // timestamp == '1991-05-20 00:00:01';
        $ip = '1.2.3.4';

        $requestNumber = 11;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn($requestNumber);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getRequestCount($since, $ip);

        $this->assertIsInt($result);
        $this->assertEquals($requestNumber, $result);
    }
}
