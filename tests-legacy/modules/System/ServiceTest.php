<?php

declare(strict_types=1);

namespace Box\Mod\System;

use PHPUnit\Framework\Attributes\Group;
use Twig\Environment;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;

    public function setUp(): void
    {
        $this->service = new Service();
    }

    public function testGetParamValueMissingKeyParam(): void
    {
        $param = [];
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Parameter key is missing');

        $this->service->getParamValue($param);
    }

    public function testGetCompany(): void
    {
        $expected = [
            'www' => SYSTEM_URL,
            'name' => 'Inc. Test',
            'email' => 'work@example.eu',
            'tel' => null,
            'signature' => null,
            'logo_url' => null,
            'logo_url_dark' => null,
            'favicon_url' => null,
            'address_1' => null,
            'address_2' => null,
            'address_3' => null,
            'account_number' => null,
            'bank_name' => null,
            'bic' => null,
            'display_bank_info' => null,
            'bank_info_pagebottom' => null,
            'number' => null,
            'note' => null,
            'privacy_policy' => null,
            'tos' => null,
            'vat_number' => null,
        ];

        $multParamsResults = [
            [
                'param' => 'company_name',
                'value' => 'Inc. Test',
            ],
            [
                'param' => 'company_email',
                'value' => 'work@example.eu',
            ],
        ];
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($multParamsResults);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getCompany();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetLanguages(): void
    {
        $result = $this->service->getLanguages(true);
        $this->assertIsArray($result);
    }

    public function testGetParams(): void
    {
        $expected = [
            'company_name' => 'Inc. Test',
            'company_email' => 'work@example.eu',
        ];
        $multParamsResults = [
            [
                'param' => 'company_name',
                'value' => 'Inc. Test',
            ],
            [
                'param' => 'company_email',
                'value' => 'work@example.eu',
            ],
        ];
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($multParamsResults);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getParams([]);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testUpdateParams(): void
    {
        $data = [
            'company_name' => 'newValue',
        ];

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())
            ->method('fire');

        $logMock = $this->createMock('\Box_Log');

        $systemServiceMock = $this->getMockBuilder(Service::class)->onlyMethods(['setParamValue'])->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('setParamValue')
            ->willReturn(true);

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['logger'] = $logMock;

        $systemServiceMock->setDi($di);
        $result = $systemServiceMock->updateParams($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGetMessages(): void
    {
        $latestVersion = '1.0.0';
        $type = 'info';

        $systemServiceMock = $this->getMockBuilder(Service::class)->onlyMethods(['getParamValue'])->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->willReturn(false);

        $updaterMock = $this->createMock(\FOSSBilling\Update::class);
        $updaterMock->expects($this->atLeastOnce())
            ->method('isUpdateAvailable')
            ->willReturn(true);
        $updaterMock->expects($this->atLeastOnce())
            ->method('getLatestVersion')
            ->willReturn($latestVersion);

        $di = $this->getDi();
        $di['updater'] = $updaterMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);

        $systemServiceMock->setDi($di);

        $result = $systemServiceMock->getMessages($type);
        $this->assertIsArray($result);
    }

    public function testTemplateExistsEmptyPaths(): void
    {
        $getThemeResults = ['paths' => []];
        $themeServiceMock = $this->getMockBuilder(\Box\Mod\Theme\Service::class)->onlyMethods(['getThemeConfig'])->getMock();
        $themeServiceMock->expects($this->atLeastOnce())->method('getThemeConfig')
            ->willReturn($getThemeResults);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeServiceMock);
        $this->service->setDi($di);

        $result = $this->service->templateExists('defaultFile.cp');
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testRenderStringTemplateException(): void
    {
        $vars = [
            '_client_id' => 1,
        ];

        $twigMock = $this->getMockBuilder(Environment::class)->disableOriginalConstructor()->getMock();
        $twigMock->expects($this->atLeastOnce())
            ->method('addGlobal');
        $twigMock->method('createTemplate')
            ->will($this->throwException(new \Error('Error')));

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(new \Model_Client());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['twig'] = $twigMock;
        $di['api_client'] = new \Model_Client();
        $this->service->setDi($di);

        $this->expectException(\Error::class);
        $this->service->renderString('test', false, $vars);
    }

    public function testRenderStringTemplate(): void
    {
        $vars = [
            '_client_id' => 1,
        ];

        $twigMock = $this->getMockBuilder(Environment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $twigMock->expects($this->atLeastOnce())
            ->method('addGlobal');
        // $twigMock->method('createTemplate')
        //     ->willReturn(new \FakeTemplateWrapper('test'));
        // $twigMock->method('load')
        //     ->willReturn(new \FakeTemplateWrapper('test'));
        $twigMock->method('render')
            ->willReturn('');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(new \Model_Client());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['twig'] = $twigMock;
        $di['api_client'] = new \Model_Client();
        $this->service->setDi($di);

        $string = $this->service->renderString('test', true, $vars);
        $this->assertEquals($string, 'test');
    }

    public function testClearCache(): void
    {
        // Use a temporary directory for testing instead of PATH_CACHE
        $cacheDir = sys_get_temp_dir() . '/fossbilling_test_cache_' . uniqid();

        // Create cache directory with .gitkeep
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0o755, true);
        }

        $gitkeepFile = $cacheDir . '/.gitkeep';
        file_put_contents($gitkeepFile, '');

        // Call clearCache with the temp directory
        $result = $this->service->clearCache($cacheDir);

        // Restore .gitkeep file after clearCache removes it
        file_put_contents($gitkeepFile, '');

        $this->assertIsBool($result);
        $this->assertTrue($result);

        // Cleanup temp directory
        if (is_dir($cacheDir)) {
            // Remove .gitkeep file first, then the directory
            if (file_exists($gitkeepFile)) {
                unlink($gitkeepFile);
            }
            rmdir($cacheDir);
        }
    }

    public function testGetPeriod(): void
    {
        $code = '1W';
        $expected = 'Every week';
        $result = $this->service->getPeriod($code);

        $this->assertIsString($result);
        $this->assertEquals($expected, $result);
    }

    public function testGetCountries(): void
    {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn(['countries' => 'US']);

        $di = $this->getDi();
        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);
        $result = $this->service->getCountries();
        $this->assertIsArray($result);
    }

    public function testGetEuCountries(): void
    {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn(['countries' => 'US']);

        $di = $this->getDi();
        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $this->service->setDi($di);
        $result = $this->service->getEuCountries();
        $this->assertIsArray($result);
    }

    public function testGetStates(): void
    {
        $result = $this->service->getStates();
        $this->assertIsArray($result);
    }

    public function testGetPhoneCodes(): void
    {
        $data = [];
        $result = $this->service->getPhoneCodes($data);
        $this->assertIsArray($result);
    }

    public function testGetVersion(): void
    {
        $result = $this->service->getVersion();
        $this->assertIsString($result);
        $this->assertEquals(\FOSSBilling\Version::VERSION, $result);
    }

    public function testGetPendingMessages(): void
    {
        $di = $this->getDi();

        $sessionMock = $this->getMockBuilder(\FOSSBilling\Session::class)->disableOriginalConstructor()->getMock();
        $sessionMock->expects($this->atLeastOnce())
            ->method('get')
            ->with('pending_messages')
            ->willReturn([]);

        $di['session'] = $sessionMock;

        $this->service->setDi($di);
        $result = $this->service->getPendingMessages();
        $this->assertIsArray($result);
    }

    public function testGetPendingMessagesGetReturnsNotArray(): void
    {
        $di = $this->getDi();

        $sessionMock = $this->getMockBuilder(\FOSSBilling\Session::class)->disableOriginalConstructor()->getMock();
        $sessionMock->expects($this->atLeastOnce())
            ->method('get')
            ->with('pending_messages')
            ->willReturn(null);

        $di['session'] = $sessionMock;

        $this->service->setDi($di);
        $result = $this->service->getPendingMessages();
        $this->assertIsArray($result);
    }

    public function testSetPendingMessage(): void
    {
        $serviceMock = $this->getMockBuilder(Service::class)
            ->onlyMethods(['getPendingMessages'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getPendingMessages')
            ->willReturn([]);

        $di = $this->getDi();

        $sessionMock = $this->getMockBuilder(\FOSSBilling\Session::class)->disableOriginalConstructor()->getMock();
        $sessionMock->expects($this->atLeastOnce())
            ->method('set')
            ->with('pending_messages');

        $di['session'] = $sessionMock;

        $serviceMock->setDi($di);

        $message = 'Important Message';
        $result = $serviceMock->setPendingMessage($message);
        $this->assertTrue($result);
    }

    public function testClearPendingMessages(): void
    {
        $di = $this->getDi();

        $sessionMock = $this->getMockBuilder(\FOSSBilling\Session::class)->disableOriginalConstructor()->getMock();
        $sessionMock->expects($this->atLeastOnce())
            ->method('delete')
            ->with('pending_messages');
        $di['session'] = $sessionMock;
        $this->service->setDi($di);
        $result = $this->service->clearPendingMessages();
        $this->assertTrue($result);
    }
}
