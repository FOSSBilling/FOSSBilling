<?php

namespace Box\Tests\Mod\Servicecustom\Api;

class Api_ClientTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicecustom\Api\Client
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new \Box\Mod\Servicecustom\Api\Client();
    }

    public function testCall(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicecustom\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServiceCustomByOrderId')
            ->willReturn(new \Model_ServiceCustom());
        $serviceMock->expects($this->atLeastOnce())
            ->method('customCall')
            ->willReturn(null);

        $arguments = [
            0 => [
                'order_id' => random_int(1, 100),
            ],
        ];

        $this->api->setService($serviceMock);

        $this->api->__call('delete', $arguments);
    }

    public function testCallArgumentsNotSetException(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicecustom\Service::class)->getMock();
        $serviceMock->expects($this->never())
            ->method('getServiceCustomByOrderId')
            ->willReturn(new \Model_ServiceCustom());
        $serviceMock->expects($this->never())
            ->method('customCall')
            ->willReturn(null);

        $arguments = [];

        $this->api->setService($serviceMock);
        $this->expectException(\Exception::class);
        $this->api->__call('delete', $arguments);
    }

    public function testCallOrderIdNotSetException(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicecustom\Service::class)->getMock();
        $serviceMock->expects($this->never())
            ->method('getServiceCustomByOrderId')
            ->willReturn(new \Model_ServiceCustom());
        $serviceMock->expects($this->never())
            ->method('customCall')
            ->willReturn(null);

        $arguments = [
            0 => [],
        ];

        $this->api->setService($serviceMock);
        $this->expectException(\Exception::class);
        $this->api->__call('delete', $arguments);
    }
}
