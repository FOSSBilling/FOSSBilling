<?php

declare(strict_types=1);

namespace Box\Mod\Api;

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

    public function testGetRequestCountFiltersByRequestPrefix(): void
    {
        $since = 674_690_401;
        $ip = '1.2.3.4';
        $requestPrefix = 'api:/api/guest/client/login';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('getCell')
            ->with(
                $this->stringContains('request LIKE :request_prefix'),
                $this->callback(fn (array $values): bool => $values['ip'] === $ip
                    && $values['request_prefix'] === $requestPrefix . '%'
                    && isset($values['since']))
            )
            ->willReturn(3);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getRequestCount($since, $ip, $requestPrefix);

        $this->assertSame(3, $result);
    }
    }
}
