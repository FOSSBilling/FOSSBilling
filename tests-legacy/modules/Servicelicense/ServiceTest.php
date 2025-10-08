<?php

namespace Box\Mod\Servicelicense;

class ServiceTest extends \BBTestCase
{
    /**
     * @var Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new Service();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testattachOrderConfigEmptyProductConig(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{}';
        $data = [];

        $result = $this->service->attachOrderConfig($productModel, $data);
        $this->assertIsArray($result);
        $this->assertEquals([], $result);
    }

    public function testattachOrderConfig(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '["hello", "world"]';
        $data = ['testing' => 'phase'];
        $expected = array_merge(json_decode($productModel->config ?? '', true), $data);

        $result = $this->service->attachOrderConfig($productModel, $data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetLicensePlugins(): void
    {
        $result = $this->service->getLicensePlugins();
        $this->assertIsArray($result);
    }

    public function testactionCreate(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($serviceLicenseModel);

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $result = $this->service->action_create($clientOrderModel);
        $this->assertInstanceOf('\Model_ServiceLicense', $result);
    }

    public function testactionActivate(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->plugin = 'Simple';

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceLicenseModel);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $result = $this->service->action_activate($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testactionActivateLicenseCollision(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->plugin = 'Simple';

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn(['iterations' => 3]);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceLicenseModel);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');
        $dbMock->expects($this->exactly(3))
            ->method('findOne')
            ->willReturnOnConsecutiveCalls($serviceLicenseModel, $serviceLicenseModel, null);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $result = $this->service->action_activate($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testactionActivateLicenseCollisionMaxIterationsException(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->plugin = 'Simple';

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceLicenseModel);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('store');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($serviceLicenseModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->action_activate($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testactionActivatePluginNotFound(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->plugin = 'TestPlugin';

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceLicenseModel);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage("License plugin {$serviceLicenseModel->plugin} was not found.");
        $this->service->action_activate($clientOrderModel);
    }

    public function testactionActivateOrderActivationException(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Could not activate order. Service was not created');
        $this->service->action_activate($clientOrderModel);
    }

    public function testactionDelete(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceLicenseModel);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->service->action_delete($clientOrderModel);
    }

    public function testreset(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $eventMock;

        $this->service->setDi($di);
        $result = $this->service->reset($serviceLicenseModel);
        $this->assertTrue($result);
    }

    public function testisLicenseActive(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_ACTIVE;

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getServiceOrder')
            ->willReturn($clientOrderModel);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $result = $this->service->isLicenseActive($serviceLicenseModel);
        $this->assertTrue($result);
    }

    public function testisLicenseNotActive(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getServiceOrder')
            ->willReturn(null);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $result = $this->service->isLicenseActive($serviceLicenseModel);
        $this->assertFalse($result);
    }

    public function testisValidIp(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->ips = '{}';
        $value = '1.1.1.1';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidIp($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidIpTest2(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->ips = '["2.2.2.2"]';
        $value = '1.1.1.1';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidIp($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidIpTest3(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->ips = '["2.2.2.2"]';
        $serviceLicenseModel->validate_ip = '3.3.3.3';
        $value = '1.1.1.1';

        $result = $this->service->isValidIp($serviceLicenseModel, $value);
        $this->assertFalse($result);
    }

    public function testisValidVersion(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->versions = '{}';
        $value = '1.0';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidVersion($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidVersionTest2(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->versions = '["2.0"]';
        $value = '1.0';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidVersion($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidVersionTest3(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->versions = '["2.0"]';
        $serviceLicenseModel->validate_version = '3.3.3.3';
        $value = '1.0';

        $result = $this->service->isValidVersion($serviceLicenseModel, $value);
        $this->assertFalse($result);
    }

    public function testisValidPath(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->paths = '{}';
        $value = '/var';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidPath($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidPathTest2(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->paths = '["/"]';
        $value = '/var';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidPath($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidPathTest3(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->paths = '["/"]';
        $serviceLicenseModel->validate_path = '/user';
        $value = '/var';

        $result = $this->service->isValidPath($serviceLicenseModel, $value);
        $this->assertFalse($result);
    }

    public function testisValidHost(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->hosts = '{}';
        $value = 'site.com';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidHost($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidHostTest2(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->hosts = '["fossbilling.org"]';
        $value = 'site.com';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidHost($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidHostTest3(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->hosts = '["fossbilling.org"]';
        $serviceLicenseModel->validate_host = 'example.com';
        $value = 'site.com';

        $result = $this->service->isValidHost($serviceLicenseModel, $value);
        $this->assertFalse($result);
    }

    public function testgetAdditionalParams(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->plugin = 'Simple';

        $result = $this->service->getAdditionalParams($serviceLicenseModel);
        $this->assertIsArray($result);
    }

    public function testgetOwnerName(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->first_name = 'John';
        $clientModel->last_name = 'Smith';

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $expected = $clientModel->first_name . ' ' . $clientModel->last_name;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($clientModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getOwnerName($serviceLicenseModel);
        $this->assertIsString($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetExpirationDate(): void
    {
        $expected = '2004-02-12 15:19:21';
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->expires_at = $expected;

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getServiceOrder')
            ->willReturn($clientOrderModel);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $result = $this->service->getExpirationDate($serviceLicenseModel);
        $this->assertIsString($result);
        $this->assertEquals($expected, $result);
    }

    public function testtoApiArray(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $expected = [
            'license_key' => '',
            'validate_ip' => '',
            'validate_host' => '',
            'validate_version' => '',
            'validate_path' => '',
            'ips' => '',
            'hosts' => '',
            'paths' => '',
            'versions' => '',
            'pinged_at' => '',
            'plugin' => '',
        ];

        $result = $this->service->toApiArray($serviceLicenseModel, false, new \Model_Admin());
        $this->assertIsArray($result);
        $this->assertTrue(count(array_diff(array_keys($expected), array_keys($result))) == 0, 'Missing array key values.');
    }

    public function testupdate(): void
    {
        $data = [
            'license_key' => '123456Licence',
            'validate_ip' => '1.1.1.1',
            'validate_host' => 'fossbilling.org',
            'validate_version' => '1.0',
            'validate_path' => '/usr',
            'ips' => '2.2.2.2\n',
            'pinged_at' => '',
            'plugin' => 'Simple',
        ];
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->update($serviceLicenseModel, $data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testcheckLicenseDetailsFormatEq2(): void
    {
        $loggerMock = $this->getMockBuilder('\Box_Log')
            ->getMock();
        $loggerMock->expects($this->atLeastOnce())
            ->method('setChannel');

        $data = [
            'format' => 2,
        ];

        $licenseServerMock = $this->getMockBuilder('\\' . Server::class)
            ->disableOriginalConstructor()
            ->getMock();
        $licenseServerMock->expects($this->atLeastOnce())
            ->method('process')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['logger'] = $loggerMock;
        $di['license_server'] = $licenseServerMock;
        $this->service->setDi($di);

        $result = $this->service->checkLicenseDetails($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('error_code', $result);
    }

    public function testCheckLicenseDetails(): void
    {
        $loggerMock = $this->getMockBuilder('\Box_Log')
            ->getMock();
        $loggerMock->expects($this->atLeastOnce())
            ->method('setChannel');

        $data = [];

        $licenseServerMock = $this->getMockBuilder('\\' . Server::class)
            ->disableOriginalConstructor()
            ->getMock();
        $licenseServerMock->expects($this->atLeastOnce())
            ->method('process')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['logger'] = $loggerMock;
        $di['license_server'] = $licenseServerMock;
        $this->service->setDi($di);

        $result = $this->service->checkLicenseDetails($data);

        $this->assertIsArray($result);
    }
}
