<?php


namespace Box\Mod\Servicelicense;


class ServiceTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Servicelicense\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Servicelicense\Service();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testattachOrderConfigEmptyProductConig()
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \RedBeanPHP\OODBBean());
        $productModel->config = '{}';
        $data                 = array();

        $result = $this->service->attachOrderConfig($productModel, $data);
        $this->assertIsArray($result);
        $this->assertEquals(array(), $result);
    }

    public function testattachOrderConfig()
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \RedBeanPHP\OODBBean());
        $productModel->config = '["hello", "world"]';
        $data                 = array('testing' => 'phase');
        $expected             = array_merge(json_decode($productModel->config, 1), $data);

        $result = $this->service->attachOrderConfig($productModel, $data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetLicensePlugins()
    {
        $result = $this->service->getLicensePlugins();
        $this->assertIsArray($result);
    }

    public function testaction_create()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($serviceLicenseModel));

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array()));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) { return $orderServiceMock; });
        $di['array_get']   = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $result = $this->service->action_create($clientOrderModel);
        $this->assertInstanceOf('\Model_ServiceLicense', $result);
    }

    public function testaction_activate()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->plugin = 'Simple';

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array()));
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($serviceLicenseModel));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) { return $orderServiceMock; });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->service->setDi($di);

        $result = $this->service->action_activate($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testaction_activateLicenseCollision()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->plugin = 'Simple';

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array('iterations' =>3)));
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($serviceLicenseModel));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');
        $dbMock->expects($this->exactly(3))
            ->method('findOne')
            ->will($this->onConsecutiveCalls($serviceLicenseModel, $serviceLicenseModel, null));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) { return $orderServiceMock; });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $result = $this->service->action_activate($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testaction_activateLicenseCollisionMaxIterationsException()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->plugin = 'Simple';

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array()));
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($serviceLicenseModel));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->never())
            ->method('store');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($serviceLicenseModel));

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) { return $orderServiceMock; });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $result = $this->service->action_activate($clientOrderModel);
        $this->assertTrue($result);
    }

    public function testaction_activatePluginNotFound()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->plugin = 'TestPlugin';

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array()));
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($serviceLicenseModel));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) { return $orderServiceMock; });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf('License plugin %s was not found', $serviceLicenseModel->plugin));
        $this->service->action_activate($clientOrderModel);
    }

    public function testaction_activateOrderActivationException()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array()));
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) { return $orderServiceMock; });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->service->setDi($di);

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Could not activate order. Service was not created');
        $this->service->action_activate($clientOrderModel);
    }

    public function testaction_delete()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->will($this->returnValue($serviceLicenseModel));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di                = new \Box_Di();
        $di['db']          = $dbMock;
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) { return $orderServiceMock; });

        $this->service->setDi($di);
        $this->service->action_delete($clientOrderModel);
    }

    public function testreset()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di                   = new \Box_Di();
        $di['db']             = $dbMock;
        $di['logger']         = new \Box_Log();
        $di['events_manager'] = $eventMock;

        $this->service->setDi($di);
        $result = $this->service->reset($serviceLicenseModel);
        $this->assertTrue($result);
    }

    public function testisLicenseActive()
    {
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrderModel->status = \Model_ClientOrder::STATUS_ACTIVE;

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());


        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getServiceOrder')
            ->will($this->returnValue($clientOrderModel));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) { return $orderServiceMock; });

        $this->service->setDi($di);
        $result = $this->service->isLicenseActive($serviceLicenseModel);
        $this->assertTrue($result);
    }

    public function testisLicenseNotActive()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getServiceOrder')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) { return $orderServiceMock; });

        $this->service->setDi($di);
        $result = $this->service->isLicenseActive($serviceLicenseModel);
        $this->assertFalse($result);
    }

    public function testisValidIp()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->ips = '{}';
        $value                    = '1.1.1.1';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidIp($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidIp_test2()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->ips = '["2.2.2.2"]';
        $value                    = '1.1.1.1';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidIp($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidIp_test3()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->ips         = '["2.2.2.2"]';
        $serviceLicenseModel->validate_ip = '3.3.3.3';
        $value                            = '1.1.1.1';

        $result = $this->service->isValidIp($serviceLicenseModel, $value);
        $this->assertFalse($result);
    }

    public function testisValidVersion()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->versions = '{}';
        $value                         = '1.0';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidVersion($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidVersion_test2()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->versions = '["2.0"]';
        $value                         = '1.0';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidVersion($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidVersion_test3()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->versions         = '["2.0"]';
        $serviceLicenseModel->validate_version = '3.3.3.3';
        $value                                 = '1.0';

        $result = $this->service->isValidVersion($serviceLicenseModel, $value);
        $this->assertFalse($result);
    }

    public function testisValidPath()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->paths = '{}';
        $value                      = '/var';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidPath($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidPath_test2()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->paths = '["/"]';
        $value                      = '/var';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidPath($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidPath_test3()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->paths         = '["/"]';
        $serviceLicenseModel->validate_path = '/user';
        $value                              = '/var';

        $result = $this->service->isValidPath($serviceLicenseModel, $value);
        $this->assertFalse($result);
    }

    public function testisValidHost()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->hosts = '{}';
        $value                      = 'site.com';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidHost($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidHost_test2()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->hosts = '["boxbilling.com"]';
        $value                      = 'site.com';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->isValidHost($serviceLicenseModel, $value);
        $this->assertTrue($result);
    }

    public function testisValidHost_test3()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->hosts         = '["boxbilling.com"]';
        $serviceLicenseModel->validate_host = 'example.com';
        $value                              = 'site.com';

        $result = $this->service->isValidHost($serviceLicenseModel, $value);
        $this->assertFalse($result);
    }

    public function testgetAdditionalParams()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());
        $serviceLicenseModel->plugin = 'Simple';

        $result = $this->service->getAdditionalParams($serviceLicenseModel);
        $this->assertIsArray($result);
    }

    public function testgetOwnerName()
    {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientModel->first_name = 'John';
        $clientModel->last_name  = 'Smith';

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());

        $expected = $clientModel->first_name . ' ' . $clientModel->last_name;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->will($this->returnValue($clientModel));

        $di       = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getOwnerName($serviceLicenseModel);
        $this->assertIsString($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetExpirationDate()
    {
        $expected         = '2004-02-12 15:19:21';
        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \RedBeanPHP\OODBBean());
        $clientOrderModel->expires_at = $expected;

        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());

        $orderServiceMock = $this->getMockBuilder('\Box\Mod\Order\Service')->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getServiceOrder')
            ->will($this->returnValue($clientOrderModel));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) { return $orderServiceMock; });

        $this->service->setDi($di);

        $result = $this->service->getExpirationDate($serviceLicenseModel);
        $this->assertIsString($result);
        $this->assertEquals($expected, $result);
    }

    public function testtoApiArray()
    {
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());

        $expected = array(
            'license_key'      => '',
            'validate_ip'      => '',
            'validate_host'    => '',
            'validate_version' => '',
            'validate_path'    => '',
            'ips'              => '',
            'hosts'            => '',
            'paths'            => '',
            'versions'         => '',
            'pinged_at'        => '',
            'plugin'           => '',
        );

        $result = $this->service->toApiArray($serviceLicenseModel, false, new \Model_Admin());
        $this->assertIsArray($result);
        $this->assertTrue(count(array_diff(array_keys($expected), array_keys($result))) == 0, 'Missing array key values.');
    }

    public function testupdate()
    {
        $data                = array(
            'license_key'      => '123456Licence',
            'validate_ip'      => '1.1.1.1',
            'validate_host'    => 'boxbilling.com',
            'validate_version' => '1.0',
            'validate_path'    => '/usr',
            'ips'              => '2.2.2.2\n',
            'pinged_at'        => '',
            'plugin'           => 'Simple',
        );
        $serviceLicenseModel = new \Model_ServiceLicense();
        $serviceLicenseModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di              = new \Box_Di();
        $di['db']        = $dbMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->service->setDi($di);
        $result = $this->service->update($serviceLicenseModel, $data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testcheckLicenseDetailsFormatEq2()
    {
        $loggerMock = $this->getMockBuilder('\Box_Log')
            ->getMock();
        $loggerMock->expects($this->atLeastOnce())
            ->method('addWriter');

        $data = array(
            'format' => 2,
        );

        $licenseServerMock = $this->getMockBuilder('\Box\Mod\Servicelicense\Server')
            ->disableOriginalConstructor()
            ->getMock();
        $licenseServerMock->expects($this->atLeastOnce())
            ->method('process')
            ->willReturn(array());

        $di                   = new \Box_Di();
        $di['logger']         = $loggerMock;
        $di['license_server'] = $licenseServerMock;
        $di['config']         = array('debug' => false);
        $this->service->setDi($di);

        $result = $this->service->checkLicenseDetails($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('error_code', $result);
    }

    public function testCheckLicenseDetails()
    {
        $loggerMock = $this->getMockBuilder('\Box_Log')
            ->getMock();
        $loggerMock->expects($this->atLeastOnce())
            ->method('addWriter');

        $data = array();

        $licenseServerMock = $this->getMockBuilder('\Box\Mod\Servicelicense\Server')
            ->disableOriginalConstructor()
            ->getMock();
        $licenseServerMock->expects($this->atLeastOnce())
            ->method('process')
            ->willReturn(array());

        $di                   = new \Box_Di();
        $di['logger']         = $loggerMock;
        $di['license_server'] = $licenseServerMock;
        $di['config']         = array('debug' => false);
        $this->service->setDi($di);

        $result = $this->service->checkLicenseDetails($data);

        $this->assertIsArray($result);
    }
}
 