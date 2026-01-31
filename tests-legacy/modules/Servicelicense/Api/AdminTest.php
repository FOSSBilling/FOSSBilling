<?php

declare(strict_types=1);

namespace Box\Mod\Servicelicense\Api;

use FOSSBilling\ProductType\License\Api\Admin as LicenseAdmin;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?LicenseAdmin $api;

    public function setUp(): void
    {
        $this->api = new LicenseAdmin();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testPluginGetPairs(): void
    {
        $licensePluginArray[]['filename'] = 'plugin1';
        $licensePluginArray[]['filename'] = 'plugin2';
        $licensePluginArray[]['filename'] = 'plugin3';

        $expected = [
            'plugin1' => 'plugin1',
            'plugin2' => 'plugin2',
            'plugin3' => 'plugin3',
        ];

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\License\LicenseHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getLicensePlugins')
            ->willReturn($licensePluginArray);

        $this->api->setService($serviceMock);

        $result = $this->api->plugin_get_pairs([]);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testUpdate(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $apiMock = $this->getMockBuilder(LicenseAdmin::class)
            ->onlyMethods(['getServiceModel'])
            ->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('getServiceModel')
            ->willReturn(new \Model_ServiceLicense());

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\License\LicenseHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(true);

        $apiMock->setService($serviceMock);
        $result = $apiMock->update($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testReset(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $apiMock = $this->getMockBuilder(LicenseAdmin::class)
            ->onlyMethods(['getServiceModel'])
            ->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('getServiceModel')
            ->willReturn(new \Model_ServiceLicense());

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\License\LicenseHandler::class);
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

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceLicense());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ClientOrder());

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);

        $result = $this->api->getServiceModel($data);
        $this->assertInstanceOf('\Model_ServiceLicense', $result);
    }

    public function testGetServiceOrderNotActivated(): void
    {
        $data['order_id'] = 1;

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(null);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ClientOrder());

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        $this->api->getServiceModel($data);
    }
}
