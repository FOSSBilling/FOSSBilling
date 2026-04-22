<?php

declare(strict_types=1);

namespace Box\Mod\System;

use PHPUnit\Framework\Attributes\Group;

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
        $param = '';
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

        $result = $this->service->templateExists('defaultFile.html.twig');
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testRenderStringTemplateException(): void
    {
        $vars = [
            '_client_id' => 1,
        ];

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(new \Model_Client());

        $sessionMock = $this->createMock(\FOSSBilling\Session::class);
        $sessionMock->method('get')->willReturn('test_csrf_token');

        $guestModel = new \Model_Guest();
        $apiGuest = new \Api_Handler($guestModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['session'] = $sessionMock;
        $di['api_guest'] = $apiGuest;
        $di['api_client'] = new \Model_Client();
        $di['twig_factory'] = new \FOSSBilling\Twig\TwigFactory($di);
        $this->service->setDi($di);

        // Use an invalid Twig template that will cause a syntax error
        $this->expectException(\FOSSBilling\InformationException::class);
        $this->service->renderTplString('{% invalid syntax %}', false, $vars);
    }

    public function testRenderStringTemplate(): void
    {
        $vars = [
            '_client_id' => 1,
        ];

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn(new \Model_Client());

        $sessionMock = $this->createMock(\FOSSBilling\Session::class);
        $sessionMock->method('get')->willReturn('test_csrf_token');

        $guestModel = new \Model_Guest();
        $apiGuest = new \Api_Handler($guestModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['session'] = $sessionMock;
        $di['api_guest'] = $apiGuest;
        $di['api_client'] = new \Model_Client();
        $di['twig_factory'] = new \FOSSBilling\Twig\TwigFactory($di);
        $this->service->setDi($di);

        $string = $this->service->renderTplString('test', true, $vars);
        $this->assertEquals($string, 'test');
    }

    public function testRenderEmailTemplateSupportsPeriodTitleInSandbox(): void
    {
        $apiGuest = new class {
            public function system_company(): array
            {
                return ['name' => 'FOSSBilling'];
            }

            public function system_period_title(array $data): string
            {
                return match ($data['code'] ?? null) {
                    '1M' => 'Every month',
                    default => 'Unknown period',
                };
            }
        };

        $di = $this->getDi();
        $di['api_guest'] = $apiGuest;

        $reflection = new \ReflectionClass(\FOSSBilling\Twig\TwigFactory::class);
        $twigFactory = $reflection->newInstanceWithoutConstructor();

        $diProperty = $reflection->getProperty('di');
        $diProperty->setValue($twigFactory, $di);

        $baseConfigProperty = $reflection->getProperty('baseConfig');
        $baseConfigProperty->setValue($twigFactory, ['cache' => false]);

        $twig = $twigFactory->createEmailEnvironment();
        $result = $twig->createTemplate('{{ "1M"|period_title }}')->render([]);

        $this->assertSame('Every month', $result);
    }

    public function testRenderEmailTemplateSupportsMarkdownAndDateInSandbox(): void
    {
        $apiGuest = new class {
            public function system_company(): array
            {
                return ['name' => 'FOSSBilling'];
            }
        };
        $themeService = new class {
            public function getDefaultMarkdownAttributes(): array
            {
                return [];
            }
        };

        $di = $this->getDi();
        $di['api_guest'] = $apiGuest;
        $di['mod_service'] = $di->protect(fn (string $service) => match ($service) {
            'theme' => $themeService,
            default => throw new \RuntimeException(sprintf('Unexpected mod_service request: %s', $service)),
        });

        $reflection = new \ReflectionClass(\FOSSBilling\Twig\TwigFactory::class);
        $twigFactory = $reflection->newInstanceWithoutConstructor();

        $diProperty = $reflection->getProperty('di');
        $diProperty->setValue($twigFactory, $di);

        $baseConfigProperty = $reflection->getProperty('baseConfig');
        $baseConfigProperty->setValue($twigFactory, ['cache' => false]);

        $twig = $twigFactory->createEmailEnvironment();
        $result = $twig->createTemplate('{% apply markdown_to_html %}**Bolded**{% endapply %} {{ "2026-03-02"|date("Y-m-d") }}')->render([]);

        $this->assertStringContainsString('<strong>Bolded</strong>', $result);
        $this->assertStringContainsString('2026-03-02', $result);
    }

    public function testBaseTwigEnvironmentSupportsHasPermissionFunction(): void
    {
        $authMock = new class {
            public function isAdminLoggedIn(): bool
            {
                return true;
            }
        };

        $staffService = new class {
            public function hasPermission($admin, string $module, ?string $permission = null): bool
            {
                return $admin->id === 42 && $module === 'system' && $permission === 'update_params';
            }
        };

        $di = $this->getDi();
        $di['auth'] = $authMock;
        $di['api_guest'] = new \stdClass();
        $di['loggedin_admin'] = (object) ['id' => 42];
        $di['session'] = $this->mockSession();
        $di['mod_service'] = $di->protect(fn (string $service) => match ($service) {
            'Staff' => $staffService,
            default => throw new \RuntimeException(sprintf('Unexpected mod_service request: %s', $service)),
        });

        $twig = $this->createBaseTwigEnvironment($di);
        $result = $twig->createTemplate("{{ has_permission('system', 'update_params') ? 'yes' : 'no' }}")->render([]);

        $this->assertSame('yes', $result);
    }

    public function testBaseTwigEnvironmentFbApiLinkDoesNotIncludeHrefInPayload(): void
    {
        $di = $this->getDi();
        $di['api_guest'] = new \stdClass();
        $di['session'] = $this->mockSession();

        $twig = $this->createBaseTwigEnvironment($di);
        $result = $twig->createTemplate("{{ fb_api_link({ href: '/admin/test', reload: true }) }}")->render([]);

        $this->assertSame('href="/admin/test" data-fb-api=\'{"reload":true,"type":"link"}\'', $result);
        $this->assertStringNotContainsString('"href"', $result);
    }

    public function testRenderEmailTemplateBlocksSetTag(): void
    {
        $this->expectException(\Twig\Sandbox\SecurityNotAllowedTagError::class);
        $this->expectExceptionMessage('Tag "set" is not allowed');

        $this->renderEmailTemplateWithSandbox("{% set x = 'malicious' %}{{ x }}");
    }

    public function testRenderEmailTemplateBlocksFunctionCalls(): void
    {
        $this->expectException(\Twig\Sandbox\SecurityNotAllowedFunctionError::class);
        $this->expectExceptionMessage('Function "max" is not allowed');

        $this->renderEmailTemplateWithSandbox('{{ max(1, 2, 3) }}');
    }

    public function testRenderEmailTemplateBlocksMethodCalls(): void
    {
        $this->expectException(\Twig\Sandbox\SecurityNotAllowedMethodError::class);
        $this->expectExceptionMessage('is not allowed');

        $object = new class {
            public function dangerousMethod(): string
            {
                return 'pwned';
            }
        };
        $this->renderEmailTemplateWithSandbox('{{ obj.dangerousMethod() }}', ['obj' => $object]);
    }

    public function testRenderEmailTemplateGuestGlobalIsRestricted(): void
    {
        $apiGuest = new class {
            public function system_company(): array
            {
                return ['name' => 'Test Company'];
            }

            public function system_params(): array
            {
                return ['secret' => 'value'];
            }
        };

        $di = $this->getDi();
        $di['api_guest'] = $apiGuest;

        $result = $this->renderEmailTemplateWithSandbox('{{ guest.system_company.name }}', [], $di);
        $this->assertSame('Test Company', $result);
    }

    public function testRenderEmailTemplateBlocksIncludeTag(): void
    {
        $this->expectException(\Twig\Sandbox\SecurityNotAllowedTagError::class);
        $this->expectExceptionMessage('Tag "include" is not allowed');

        $this->renderEmailTemplateWithSandbox("{% include 'some_file.html' %}");
    }

    public function testRenderEmailTemplateBlocksImportTag(): void
    {
        $this->expectException(\Twig\Sandbox\SecurityNotAllowedTagError::class);
        $this->expectExceptionMessage('Tag "import" is not allowed');

        $this->renderEmailTemplateWithSandbox("{% import 'macros.html' as macros %}");
    }

    public function testRenderEmailTemplateBlocksExtendsTag(): void
    {
        // Extends is blocked by the ArrayLoader not having the template,
        // but the sandbox would also block it if it got that far
        $this->expectException(\Twig\Error\LoaderError::class);

        $this->renderEmailTemplateWithSandbox("{% extends 'base.html' %}");
    }

    public function testRenderEmailTemplateUrlFilterWithClientAreaNamedArgument(): void
    {
        $apiGuest = new class {
            public function system_company(): array
            {
                return ['name' => 'Test'];
            }
        };

        $urlMock = $this->createMock(\Box_Url::class);
        $urlMock->method('link')
            ->willReturn('http://example.com/login?email=test%40example.com');

        $di = $this->getDi();
        $di['api_guest'] = $apiGuest;
        $di['url'] = $urlMock;

        $result = $this->renderEmailTemplateWithSandbox(
            "{{ 'login'|url(query: { 'email': 'test@example.com' }, area: 'client') }}",
            [],
            $di
        );

        $this->assertSame('http://example.com/login?email=test%40example.com', $result);
    }

    public function testRenderEmailTemplateUrlFilterWithAdminAreaNamedArgument(): void
    {
        $apiGuest = new class {
            public function system_company(): array
            {
                return ['name' => 'Test'];
            }
        };

        $urlMock = $this->createMock(\Box_Url::class);
        $urlMock->expects($this->once())
            ->method('adminLink')
            ->with('staff/login', ['email' => 'staff@example.com'])
            ->willReturn('http://example.com/admin/staff/login?email=staff%40example.com');

        $di = $this->getDi();
        $di['api_guest'] = $apiGuest;
        $di['url'] = $urlMock;

        $result = $this->renderEmailTemplateWithSandbox(
            "{{ 'staff/login'|url(query: { 'email': 'staff@example.com' }, area: 'admin') }}",
            [],
            $di
        );

        $this->assertSame('http://example.com/admin/staff/login?email=staff%40example.com', $result);
    }

    private function renderEmailTemplateWithSandbox(string $template, array $vars = [], ?\Pimple\Container $di = null): string
    {
        $di ??= $this->getDi();
        if (!isset($di['api_guest'])) {
            $di['api_guest'] = new class {
                public function system_company(): array
                {
                    return ['name' => 'Test'];
                }
            };
        }
        if (!isset($di['logger'])) {
            $di['logger'] = $this->createMock(\Box_Log::class);
        }

        $reflection = new \ReflectionClass(\FOSSBilling\Twig\TwigFactory::class);
        $twigFactory = $reflection->newInstanceWithoutConstructor();

        $diProperty = $reflection->getProperty('di');
        $diProperty->setValue($twigFactory, $di);

        $baseConfigProperty = $reflection->getProperty('baseConfig');
        $baseConfigProperty->setValue($twigFactory, ['cache' => false]);

        $twig = $twigFactory->createEmailEnvironment();

        return $twig->createTemplate($template)->render($vars);
    }

    private function createBaseTwigEnvironment(\Pimple\Container $di): \Twig\Environment
    {
        $reflection = new \ReflectionClass(\FOSSBilling\Twig\TwigFactory::class);
        $twigFactory = $reflection->newInstanceWithoutConstructor();

        $diProperty = $reflection->getProperty('di');
        $diProperty->setValue($twigFactory, $di);

        $baseConfigProperty = $reflection->getProperty('baseConfig');
        $baseConfigProperty->setValue($twigFactory, ['cache' => false]);

        return $twigFactory->createBaseEnvironment();
    }

    private function mockSession(): \FOSSBilling\Session
    {
        $sessionMock = $this->createMock(\FOSSBilling\Session::class);
        $sessionMock->method('get')->willReturn('test_csrf_token');

        return $sessionMock;
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
