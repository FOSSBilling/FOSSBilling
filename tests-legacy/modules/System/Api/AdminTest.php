<?php

declare(strict_types=1);

namespace Box\Mod\System\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Admin $api;

    public function setUp(): void
    {
        $this->api = new Admin();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetParams(): void
    {
        $data = [
        ];

        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getParams')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->get_params($data);
        $this->assertIsArray($result);
    }

    public function testUpdateParams(): void
    {
        $data = [
        ];

        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateParams')
            ->willReturn(true);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('system', 'update_params');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
            if ($serviceName == 'Staff') {
                return $staffServiceMock;
            }

            return false;
        });
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->update_params($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testMessages(): void
    {
        $data = [
        ];

        $di = $this->getDi();

        $this->api->setDi($di);

        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getMessages')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->messages($data);
        $this->assertIsArray($result);
    }

    public function testTemplateExists(): void
    {
        $data = [
            'file' => 'testing.txt',
        ];

        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('templateExists')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->template_exists($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testStringRender(): void
    {
        $data = [
            '_tpl' => 'default',
        ];

        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('renderString')
            ->willReturn('returnStringType');
        $di = $this->getDi();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->string_render($data);
        $this->assertIsString($result);
    }

    public function testEnv(): void
    {
        $data = [];

        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getEnv')
            ->willReturn([]);

        $di = $this->getDi();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->env($data);
        $this->assertIsArray($result);
    }

    public function testIsAllowed(): void
    {
        $data = [
            'mod' => 'extension',
        ];

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->atLeastOnce())
            ->method('hasPermission')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
            if ($serviceName == 'Staff') {
                return $staffServiceMock;
            }

            return false;
        });
        $this->api->setDi($di);

        $result = $this->api->is_allowed($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testRecheckUpdate(): void
    {
        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('system', 'recheck_update');

        $updaterMock = $this->getMockBuilder(\FOSSBilling\Update::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLatestVersionInfo'])
            ->getMock();
        $updaterMock->expects($this->once())
            ->method('getLatestVersionInfo')
            ->with(null, true);

        $di = $this->getDi();
        $di['updater'] = $updaterMock;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
            if ($serviceName == 'Staff') {
                return $staffServiceMock;
            }

            return false;
        });
        $this->api->setDi($di);

        $result = $this->api->recheck_update();
        $this->assertTrue($result);
    }

    public function testToggleErrorReportingRequiresPermission(): void
    {
        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('system', 'toggle_error_reporting')
            ->willThrowException(new \FOSSBilling\InformationException('denied'));

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
            if ($serviceName == 'Staff') {
                return $staffServiceMock;
            }

            return false;
        });
        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->api->toggle_error_reporting();
    }

    public function testGetInterfaceIpsRequiresPermission(): void
    {
        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('system', 'manage_network_interface')
            ->willThrowException(new \FOSSBilling\InformationException('denied'));

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
            if ($serviceName == 'Staff') {
                return $staffServiceMock;
            }

            return false;
        });
        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->api->get_interface_ips();
    }
}
