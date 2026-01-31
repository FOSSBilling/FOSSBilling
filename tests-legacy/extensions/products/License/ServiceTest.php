<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\License;

use FOSSBilling\ProductType\License\LicenseHandler;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?LicenseHandler $service;

    public function setUp(): void
    {
        $this->service = new LicenseHandler();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testAttachOrderConfigEmptyProductConig(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{}';
        $data = [];

        $result = $this->service->attachOrderConfig($productModel, $data);
        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function testAttachOrderConfig(): void
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

    public function testGetLicensePlugins(): void
    {
        $result = $this->service->getLicensePlugins();
        $this->assertIsArray($result);
    }

    public function testActionCreate(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($serviceLicenseModel);

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $result = $this->service->create($clientOrderModel);
        $this->assertInstanceOf('\Model_ServiceLicense', $result);
    }

    public function testActionActivate(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->plugin = 'Simple';

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceLicenseModel);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $result = $this->service->activate($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testActionActivateLicenseCollision(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->plugin = 'Simple';

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn(['iterations' => 3]);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceLicenseModel);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');
        $dbMock->expects($this->exactly(3))
            ->method('findOne')
            ->willReturnOnConsecutiveCalls($serviceLicenseModel, $serviceLicenseModel, null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $result = $this->service->activate($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testActionActivateLicenseCollisionMaxIterationsException(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->plugin = 'Simple';

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceLicenseModel);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())
            ->method('store');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($serviceLicenseModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->service->activate($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testActionActivatePluginNotFound(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->plugin = 'TestPlugin';

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceLicenseModel);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage("License plugin {$serviceLicenseModel->plugin} was not found.");
        $this->service->activate($clientOrderModel);
    }

    public function testActionActivateOrderActivationException(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(null);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Could not activate order. Service was not created');
        $this->service->activate($clientOrderModel);
    }

    public function testActionDelete(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($serviceLicenseModel);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->service->delete($clientOrderModel);
    }

    public function testReset(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $eventMock;

        $this->service->setDi($di);
        $result = $this->service->reset($serviceLicenseModel);
        $this->assertTrue($result);
    }

    public function testIsLicenseActive(): void
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_ACTIVE;

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getServiceOrder')
            ->willReturn($clientOrderModel);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $result = $this->service->isLicenseActive($serviceLicenseModel);
        $this->assertTrue($result);
    }

    public function testIsLicenseNotActive(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getServiceOrder')
            ->willReturn(null);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $result = $this->service->isLicenseActive($serviceLicenseModel);
        $this->assertFalse($result);
    }

    public function testIsValidIp(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->ips = '{}';
        $value = '1.1.1.1';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidIp($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testIsValidIpTest2(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->ips = '["2.2.2.2"]';
        $value = '1.1.1.1';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidIp($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testIsValidIpTest3(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->ips = '["2.2.2.2"]';
        $serviceLicenseModel->validate_ip = '3.3.3.3';
        $value = '1.1.1.1';

        $result = $this->service->isValidIp($serviceLicenseModel, $value);
        $this->assertFalse($result);
    }

    public function testIsValidVersion(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->versions = '{}';
        $value = '1.0';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidVersion($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testIsValidVersionTest2(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->versions = '["2.0"]';
        $value = '1.0';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidVersion($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testIsValidVersionTest3(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->versions = '["2.0"]';
        $serviceLicenseModel->validate_version = '3.3.3.3';
        $value = '1.0';

        $result = $this->service->isValidVersion($serviceLicenseModel, $value);
        $this->assertFalse($result);
    }

    public function testIsValidPath(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->paths = '{}';
        $value = '/var';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidPath($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testIsValidPathTest2(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->paths = '["/"]';
        $value = '/var';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidPath($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testIsValidPathTest3(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->paths = '["/"]';
        $serviceLicenseModel->validate_path = '/user';
        $value = '/var';

        $result = $this->service->isValidPath($serviceLicenseModel, $value);
        $this->assertFalse($result);
    }

    public function testIsValidHost(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->hosts = '{}';
        $value = 'site.com';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidHost($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testIsValidHostTest2(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->hosts = '["fossbilling.org"]';
        $value = 'site.com';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidHost($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testIsValidHostTest3(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->hosts = '["fossbilling.org"]';
        $serviceLicenseModel->validate_host = 'example.com';
        $value = 'site.com';

        $result = $this->service->isValidHost($serviceLicenseModel, $value);
        $this->assertFalse($result);
    }

    public function testGetAdditionalParams(): void
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());
        $serviceLicenseModel->plugin = 'Simple';

        $result = $this->service->getAdditionalParams($serviceLicenseModel);
        $this->assertIsArray($result);
    }

    public function testGetOwnerName(): void
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->first_name = 'John';
        $clientModel->last_name = 'Smith';

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $expected = $clientModel->first_name . ' ' . $clientModel->last_name;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($clientModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getOwnerName($serviceLicenseModel);
        $this->assertIsString($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetExpirationDate(): void
    {
        $expected = '2004-02-12 15:19:21';
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());
        $clientOrderModel->expires_at = $expected;

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \DummyBean());

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getServiceOrder')
            ->willReturn($clientOrderModel);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);

        $result = $this->service->getExpirationDate($serviceLicenseModel);
        $this->assertIsString($result);
        $this->assertEquals($expected, $result);
    }

    public function testToApiArray(): void
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

    public function testUpdate(): void
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

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->update($serviceLicenseModel, $data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testCheckLicenseDetailsFormatEq2(): void
    {
        $loggerMock = $this->getMockBuilder('\Box_Log')
            ->getMock();
        $loggerMock->expects($this->atLeastOnce())
            ->method('setChannel');

        $data = [
            'format' => 2,
        ];

        $licenseServerMock = $this->getMockBuilder(\FOSSBilling\ProductType\License\Server::class)
            ->disableOriginalConstructor()
            ->getMock();
        $licenseServerMock->expects($this->atLeastOnce())
            ->method('process')
            ->willReturn([]);

        $di = $this->getDi();
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

        $licenseServerMock = $this->getMockBuilder(\FOSSBilling\ProductType\License\Server::class)
            ->disableOriginalConstructor()
            ->getMock();
        $licenseServerMock->expects($this->atLeastOnce())
            ->method('process')
            ->willReturn([]);

        $di = $this->getDi();
        $di['logger'] = $loggerMock;
        $di['license_server'] = $licenseServerMock;
        $this->service->setDi($di);

        $result = $this->service->checkLicenseDetails($data);

        $this->assertIsArray($result);
    }
}
