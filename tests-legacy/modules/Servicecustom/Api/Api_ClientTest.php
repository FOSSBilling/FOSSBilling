<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Servicecustom\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Api_ClientTest extends \BBTestCase
{
    protected ?\FOSSBilling\ProductType\Custom\Api\Client $api;

    public function setUp(): void
    {
        $this->api = new \FOSSBilling\ProductType\Custom\Api\Client();
    }

    public function testCall(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Custom\CustomHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServiceCustomByOrderId')
            ->willReturn(new \Model_ServiceCustom());
        $serviceMock->expects($this->atLeastOnce())
            ->method('customCall')
            ->willReturn(null);

        $arguments = [
            0 => [
                'order_id' => 1,
            ],
        ];

        $this->api->setService($serviceMock);

        $this->api->__call('delete', $arguments);
    }

    public function testCallArgumentsNotSetException(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Custom\CustomHandler::class);
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
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Custom\CustomHandler::class);
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
