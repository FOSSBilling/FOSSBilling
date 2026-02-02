<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\License\Tests\Api;

use FOSSBilling\ProductType\License\Api;
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

        $result = $this->api->admin_plugin_get_pairs([]);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testUpdate(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $apiMock = $this->getMockBuilder(Api::class)
            ->onlyMethods(['getServiceModelForAdmin'])
            ->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('getServiceModelForAdmin')
            ->willReturn(new \Model_ExtProductLicense());

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\License\LicenseHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(true);

        $apiMock->setService($serviceMock);
        $apiMock->setIdentity(new \Model_Admin());
        $result = $apiMock->admin_update($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testReset(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $apiMock = $this->getMockBuilder(Api::class)
            ->onlyMethods(['getServiceModelForAdmin'])
            ->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('getServiceModelForAdmin')
            ->willReturn(new \Model_ExtProductLicense());

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\License\LicenseHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('reset')
            ->willReturn(true);

        $apiMock->setService($serviceMock);
        $apiMock->setIdentity(new \Model_Admin());
        $result = $apiMock->admin_reset($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGetService(): void
    {
        $data['order_id'] = 1;

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ExtProductLicense());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ClientOrder());

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');

        $licenseServiceMock = $this->createMock(\FOSSBilling\ProductType\License\LicenseHandler::class);
        $licenseServiceMock->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($licenseServiceMock);

        $result = $this->api->admin_update($data);
        $this->assertTrue($result);
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
        $this->api->admin_update($data);
    }
}
