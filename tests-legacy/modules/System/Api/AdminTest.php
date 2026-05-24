<?php

declare(strict_types=1);

namespace Box\Mod\System\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Admin $api;
    private ?string $originalConfigContents = null;

    public function setUp(): void
    {
        $this->api = $this->createAdminApi(Admin::class);

        $configContents = file_get_contents(PATH_CONFIG);
        if ($configContents === false) {
            self::fail('Failed to read the FOSSBilling config file.');
        }

        $this->originalConfigContents = $configContents;
    }

    public function testGetParams(): void
    {
        $data = [
        ];

        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getParams')
            ->willReturn([]);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('system', 'manage_settings');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
            if ($serviceName == 'Staff') {
                return $staffServiceMock;
            }

            return false;
        });
        $this->api->setDi($di);
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

    public function testLocalizationSettings(): void
    {
        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('system', 'manage_settings');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
            if ($serviceName == 'Staff') {
                return $staffServiceMock;
            }

            return false;
        });
        $this->api->setDi($di);

        $result = $this->api->localization_settings();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('locale', $result);
        $this->assertArrayHasKey('auto_detect_locale', $result);
        $this->assertIsString($result['locale']);
        $this->assertIsBool($result['auto_detect_locale']);
    }

    public function testUpdateLocalizationSettings(): void
    {
        $before = (bool) \FOSSBilling\Config::getProperty('i18n.auto_detect_locale', true);

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

        $result = $this->api->update_localization_settings([
            'auto_detect_locale' => $before ? '0' : '1',
        ]);

        $this->assertTrue($result);
        $this->assertSame(!$before, \FOSSBilling\Config::getProperty('i18n.auto_detect_locale', $before));
    }

    public function testMessages(): void
    {
        $data = [
        ];

        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getMessages')
            ->willReturn([]);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('system', 'manage_settings');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
            if ($serviceName == 'Staff') {
                return $staffServiceMock;
            }

            return false;
        });
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->messages($data);
        $this->assertIsArray($result);
    }

    public function testCasMessagesReturnsEmptyListWithoutManageSettingsPermission(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->never())
            ->method('getCasMessages');

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('system', 'manage_settings')
            ->willThrowException(new \FOSSBilling\InformationException('You do not have permission to perform this action', [], 403));

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
            if ($serviceName == 'Staff') {
                return $staffServiceMock;
            }

            return false;
        });
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $this->assertSame([], $this->api->cas_messages());
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

    public function testEnv(): void
    {
        $data = [];

        $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getEnv')
            ->willReturn([]);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('system', 'manage_settings');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
            if ($serviceName == 'Staff') {
                return $staffServiceMock;
            }

            return false;
        });
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

    protected function tearDown(): void
    {
        if ($this->originalConfigContents !== null) {
            file_put_contents(PATH_CONFIG, $this->originalConfigContents);
            clearstatcache(true, PATH_CONFIG);

            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate(PATH_CONFIG, true);
            }
        }

        @unlink(\Symfony\Component\Filesystem\Path::changeExtension(PATH_CONFIG, 'old.php'));

        parent::tearDown();
    }
}
