<?php

declare(strict_types=1);

namespace Box\Mod\Cron;
use PHPUnit\Framework\Attributes\DataProvider; 
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    public function testGetDi(): void
    {
        $di = $this->getDi();
        $service = new Service();
        $service->setDi($di);
        $getDi = $service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetCronInfo(): void
    {
        $systemServiceMock = $this->createMock(\Box\Mod\System\Service::class);
        $systemServiceMock->expects($this->atLeastOnce())->method('getParamValue');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $service = new Service();
        $service->setDi($di);

        $result = $service->getCronInfo();
        $this->assertIsArray($result);
    }

    public function testGetLastExecutionTime(): void
    {
        $systemServiceMock = $this->createMock(\Box\Mod\System\Service::class);
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturn('2012-12-12 12:12:12');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $service = new Service();
        $service->setDi($di);

        $result = $service->getLastExecutionTime();
        $this->assertIsString($result);
    }

    public function testIsLate(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getLastExecutionTime'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getLastExecutionTime')
            ->willReturn(date('Y-m-d H:i:s'));

        $result = $serviceMock->isLate();
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }
}
