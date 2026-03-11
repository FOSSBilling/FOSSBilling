<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Servicecustom\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Api_ClientTest extends \BBTestCase
{
    protected ?\Box\Mod\Servicecustom\Api\Client $api;

    public function setUp(): void
    {
        $this->api = new \Box\Mod\Servicecustom\Api\Client();
    }

    public function testCall(): void
    {
        $identity = (object) ['id' => 42];

        $serviceMock = $this->createMock(\Box\Mod\Servicecustom\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServiceCustomByOrderId')
            ->with(1, 42)
            ->willReturn(new \Model_ServiceCustom());
        $serviceMock->expects($this->atLeastOnce())
            ->method('customCall')
            ->with($this->isInstanceOf(\Model_ServiceCustom::class), 'delete', ['order_id' => 1, 'method' => 'delete'])
            ->willReturn(null);

        $data = [
            'order_id' => 1,
            'method' => 'delete',
        ];

        $this->api->setService($serviceMock);
        $this->api->setIdentity($identity);

        $this->api->call($data);
    }

    public function testCallMethodNotSetException(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Servicecustom\Service::class);
        $serviceMock->expects($this->never())
            ->method('getServiceCustomByOrderId')
            ->willReturn(new \Model_ServiceCustom());
        $serviceMock->expects($this->never())
            ->method('customCall')
            ->willReturn(null);

        $data = [
            'order_id' => 1,
        ];

        $this->api->setService($serviceMock);
        $this->expectException(\Exception::class);
        $this->validateRequiredParams($this->api, 'call', $data);
    }

    public function testCallOrderIdNotSetException(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Servicecustom\Service::class);
        $serviceMock->expects($this->never())
            ->method('getServiceCustomByOrderId')
            ->willReturn(new \Model_ServiceCustom());
        $serviceMock->expects($this->never())
            ->method('customCall')
            ->willReturn(null);

        $data = [
            'method' => 'delete',
        ];

        $this->api->setService($serviceMock);
        $this->expectException(\Exception::class);
        $this->validateRequiredParams($this->api, 'call', $data);
    }
}
