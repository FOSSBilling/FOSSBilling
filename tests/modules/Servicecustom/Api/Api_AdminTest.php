<?php

namespace Box\Tests\Mod\Servicecustom\Api;

class Api_AdminTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicecustom\Api\Admin
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new \Box\Mod\Servicecustom\Api\Admin();
    }

    public function testUpdate()
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicecustom\Service::class)->onlyMethods(['updateConfig'])->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateConfig')
            ->willReturn(null);

        $data = [
            'order_id' => random_int(1, 100),
            'config' => [
                'param1' => 'value1',
            ],
        ];

        $this->api->setService($serviceMock);

        $this->api->update($data);
    }

    public function testUpdateOrderIdNotSetException()
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicecustom\Service::class)->onlyMethods(['updateConfig'])->getMock();
        $serviceMock->expects($this->never())
            ->method('updateConfig')
            ->willReturn(null);

        $data = [
            'config' => [
                'param1' => 'value1',
            ],
        ];

        $this->api->setService($serviceMock);
        $this->expectException(\Exception::class);
        $result = $this->api->update($data);

        $this->assertTrue($result);
    }

    public function testUpdateConfigNotSet()
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicecustom\Service::class)->onlyMethods(['updateConfig'])->getMock();
        $serviceMock->expects($this->never())
            ->method('updateConfig')
            ->willReturn(null);

        $data = [
            'order_id' => random_int(1, 100),
        ];

        $this->api->setService($serviceMock);

        $result = $this->api->update($data);
        $this->assertTrue($result);
    }

    public function testUpdateConfigIsNotArray()
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicecustom\Service::class)->onlyMethods(['updateConfig'])->getMock();
        $serviceMock->expects($this->never())
            ->method('updateConfig')
            ->willReturn(null);

        $data = [
            'order_id' => random_int(1, 100),
            'config' => 'NotArray',
        ];

        $this->api->setService($serviceMock);

        $result = $this->api->update($data);
        $this->assertTrue($result);
    }

    public function testCall()
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

    public function testCallArgumentsNotSetException()
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

    public function testCallOrderIdNotSetException()
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
