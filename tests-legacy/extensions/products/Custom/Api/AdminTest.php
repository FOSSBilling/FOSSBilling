<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\Custom\Api\Tests;

use FOSSBilling\ProductType\Custom\Api;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Api $api;

    public function setUp(): void
    {
        $this->api = new Api();
        $this->api->setIdentity(new \Model_Admin());
    }

    public function testUpdate(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Custom\CustomHandler::class)->onlyMethods(['updateConfig'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateConfig');

        $data = [
            'order_id' => 1,
            'config' => [
                'param1' => 'value1',
            ],
        ];

        $this->api->setService($serviceMock);

        $this->api->admin_update($data);
    }

    public function testUpdateOrderIdNotSetException(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Custom\CustomHandler::class)->onlyMethods(['updateConfig'])->getMock();
        $serviceMock->expects($this->never())
            ->method('updateConfig');

        $data = [
            'config' => [
                'param1' => 'value1',
            ],
        ];

        $this->api->setService($serviceMock);
        $this->expectException(\Exception::class);
        $result = $this->api->admin_update($data);

        $this->assertTrue($result);
    }

    public function testUpdateConfigNotSet(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Custom\CustomHandler::class)->onlyMethods(['updateConfig'])->getMock();
        $serviceMock->expects($this->never())
            ->method('updateConfig');

        $data = [
            'order_id' => 1,
        ];

        $this->api->setService($serviceMock);

        $result = $this->api->admin_update($data);
        $this->assertTrue($result);
    }

    public function testUpdateConfigIsNotArray(): void
    {
        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Custom\CustomHandler::class)->onlyMethods(['updateConfig'])->getMock();
        $serviceMock->expects($this->never())
            ->method('updateConfig');

        $data = [
            'order_id' => 1,
            'config' => 'NotArray',
        ];

        $this->api->setService($serviceMock);

        $result = $this->api->admin_update($data);
        $this->assertTrue($result);
    }

    public function testCall(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Custom\CustomHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServiceCustomByOrderId')
            ->willReturn(new \Model_ExtProductCustom());
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
            ->willReturn(new \Model_ExtProductCustom());
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
            ->willReturn(new \Model_ExtProductCustom());
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
