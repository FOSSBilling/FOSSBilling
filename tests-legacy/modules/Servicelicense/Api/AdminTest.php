<?php

namespace Box\Mod\Servicelicense\Api;

class AdminTest extends \BBTestCase
{
    /**
     * @var Admin
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Admin();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testpluginGetPairs(): void
    {
        $licensePluginArray[]['filename'] = 'plugin1';
        $licensePluginArray[]['filename'] = 'plugin2';
        $licensePluginArray[]['filename'] = 'plugin3';

        $expected = [
            'plugin1' => 'plugin1',
            'plugin2' => 'plugin2',
            'plugin3' => 'plugin3',
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicelicense\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getLicensePlugins')
            ->willReturn($licensePluginArray);

        $this->api->setService($serviceMock);

        $result = $this->api->plugin_get_pairs([]);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testupdate(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $apiMock = $this->getMockBuilder('\\' . Admin::class)
            ->onlyMethods(['_getService'])
            ->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->willReturn(new \Model_ServiceLicense());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicelicense\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(true);

        $apiMock->setService($serviceMock);
        $result = $apiMock->update($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testreset(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $apiMock = $this->getMockBuilder('\\' . Admin::class)
            ->onlyMethods(['_getService'])
            ->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->willReturn(new \Model_ServiceLicense());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicelicense\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('reset')
            ->willReturn(true);

        $apiMock->setService($serviceMock);
        $result = $apiMock->reset($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGetService(): void
    {
        $data['order_id'] = 1;

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceLicense());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ClientOrder());

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);

        $result = $this->api->_getService($data);
        $this->assertInstanceOf('\Model_ServiceLicense', $result);
    }

    public function testGetServiceOrderNotActivated(): void
    {
        $data['order_id'] = 1;

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(null);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ClientOrder());

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        $this->api->_getService($data);
    }
}
