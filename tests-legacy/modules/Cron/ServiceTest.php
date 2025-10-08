<?php

namespace Box\Mod\Cron;

class ServiceTest extends \BBTestCase
{
    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $service = new Service();
        $service->setDi($di);
        $getDi = $service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetCronInfo(): void
    {
        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemServiceMock->expects($this->atLeastOnce())->method('getParamValue');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $service = new Service();
        $service->setDi($di);

        $result = $service->getCronInfo();
        $this->assertIsArray($result);
    }

    public function testgetLastExecutionTime(): void
    {
        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturn('2012-12-12 12:12:12');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $service = new Service();
        $service->setDi($di);

        $result = $service->getLastExecutionTime();
        $this->assertIsString($result);
    }

    public function testisLate(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
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
