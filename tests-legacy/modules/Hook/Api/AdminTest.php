<?php

declare(strict_types=1);

namespace Box\Mod\Hook\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Admin $api;

    public function setUp(): void
    {
        $this->api = new Admin();
    }

    public function testGetList(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Hook\Service::class);
        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('hook', 'manage_hooks');

        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['SqlString', []]);

        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn([]);

        $di = $this->getDi();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffServiceMock,
            default => throw new \RuntimeException('Unexpected module service request: ' . $name),
        });

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->get_list([]);
        $this->assertIsArray($result);
    }

    public function testCall(): void
    {
        $data['event'] = 'testEvent';

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('hook', 'trigger_hooks');

        $eventManager = $this->createMock('\Box_EventManager');
        $eventManager->expects($this->atLeastOnce())
            ->method('fire')
            ->willReturn(1);

        $di = $this->getDi();
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $eventManager;
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffServiceMock,
            default => throw new \RuntimeException('Unexpected module service request: ' . $name),
        });

        $this->api->setDi($di);
        $result = $this->api->call($data);
        $this->assertNotEmpty($result);
    }

    public function testCallMissingEventParam(): void
    {
        $data['event'] = null;

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('hook', 'trigger_hooks');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffServiceMock,
            default => throw new \RuntimeException('Unexpected module service request: ' . $name),
        });

        $this->api->setDi($di);

        $result = $this->api->call($data);
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testBatchConnect(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Hook\Service::class);
        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('hook', 'manage_hooks');

        $serviceMock->expects($this->atLeastOnce())
            ->method('batchConnect')
            ->willReturn(true);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffServiceMock,
            default => throw new \RuntimeException('Unexpected module service request: ' . $name),
        });

        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->batch_connect([]);
        $this->assertNotEmpty($result);
    }
}
