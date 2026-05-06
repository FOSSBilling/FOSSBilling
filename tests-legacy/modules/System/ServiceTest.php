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

    public function testRenderAdapterTplString(): void
    {
        $apiGuest = new class {
            public function system_company(): array
            {
                return ['name' => 'FOSSBilling'];
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

        $di['twig_factory'] = $twigFactory;
        $this->service->setDi($di);

        $vars = ['invoice' => ['id' => 1, 'total' => 100]];
        $result = $this->service->renderAdapterTplString('Invoice #{{ invoice.id }} - {{ invoice.total }}', $vars);
        $this->assertEquals('Invoice #1 - 100', $result);
    }

    public function testRenderAdapterTplStringSandboxViolation(): void
    {
        $apiGuest = new class {
            public function system_company(): array
            {
                return ['name' => 'FOSSBilling'];
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

        $di['twig_factory'] = $twigFactory;
        $di['logger'] = new class {
            public function setChannel(string $channel): self
            {
                return $this;
            }

            public function warning(string $message, array $context = []): void
            {
            }
        };
        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->service->renderAdapterTplString('{{ range(1, 5) }}', []);
    }

    public function testRenderAdapterTplStringBlocksMethodCalls(): void
    {
        $object = new class {
            public function dangerousMethod(): string
            {
                return 'pwned';
            }
        };

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->renderAdapterTemplate('{{ obj.dangerousMethod() }}', ['obj' => $object]);
    }

    public function testRenderAdapterTplStringBlocksPropertyAccess(): void
    {
        $object = new class {
            public string $secret = 'leaked';
        };

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->renderAdapterTemplate('{{ obj.secret }}', ['obj' => $object]);
    }

    public function testRenderAdapterTplStringBlocksIncludeTag(): void
    {
        $this->expectException(\FOSSBilling\InformationException::class);
        $this->renderAdapterTemplate("{% include 'some_file.html' %}");
    }

    public function testRenderAdapterTplStringBlocksImportTag(): void
    {
        $this->expectException(\FOSSBilling\InformationException::class);
        $this->renderAdapterTemplate("{% import 'macros.html' as macros %}");
    }

    public function testRenderAdapterTplStringBlocksExtendsTag(): void
    {
        $this->expectException(\FOSSBilling\InformationException::class);
        $this->renderAdapterTemplate("{% extends 'base.html' %}");
    }

    public function testRenderAdapterTplStringStripsScriptTags(): void
    {
        $result = $this->renderAdapterTemplate('<p>Hello</p><script>alert(1)</script><b>World</b>');
        $this->assertSame('<p>Hello</p><b>World</b>', $result);
    }

    public function testRenderAdapterTplStringStripsSelfClosingScriptTags(): void
    {
        $result = $this->renderAdapterTemplate('<p>Hello</p><script src="evil.js"/><b>World</b>');
        $this->assertSame('<p>Hello</p><b>World</b>', $result);
    }

    public function testRenderAdapterTplStringStripsEventHandlers(): void
    {
        $result = $this->renderAdapterTemplate('<div onclick="steal()" onerror="x()">text</div>');
        $this->assertSame('<div>text</div>', $result);
    }

    public function testRenderAdapterTplStringStripsSingleQuotedEventHandlers(): void
    {
        $result = $this->renderAdapterTemplate("<div onmouseover='alert(1)'>text</div>");
        $this->assertSame('<div>text</div>', $result);
    }

    public function testRenderAdapterTplStringAllowsHtmlFormatting(): void
    {
        $html = '<h1>Pay to:</h1><b>Bank XYZ</b><br><i>Account: 12345</i><table><tr><td>Row</td></tr></table>';
        $result = $this->renderAdapterTemplate($html);
        $this->assertSame($html, $result);
    }

    public function testRenderAdapterTplStringAllowedFiltersWork(): void
    {
        $result = $this->renderAdapterTemplate('{{ 3.14159|number_format(2) }}');
        $this->assertSame('3.14', $result);
    }

    public function testRenderAdapterTplStringDateFilterWorks(): void
    {
        $result = $this->renderAdapterTemplate('{{ "2026-03-02"|date("Y-m-d") }}');
        $this->assertStringContainsString('2026-03-02', $result);
    }

    public function testRenderAdapterTplStringTransFilterWorks(): void
    {
        $result = $this->renderAdapterTemplate("{{ 'Hello'|trans }}");
        $this->assertSame('Hello', $result);
    }

    public function testSanitizeAdapterOutputStripsScriptTags(): void
    {
        $result = $this->service->sanitizeAdapterOutput('<p>Safe</p><script>alert(1)</script>');
        $this->assertSame('<p>Safe</p>', $result);
    }

    public function testSanitizeAdapterOutputStripsScriptWithAttributes(): void
    {
        $result = $this->service->sanitizeAdapterOutput('<script type="text/javascript" src="evil.js">alert(1)</script>');
        $this->assertSame('', $result);
    }

    public function testSanitizeAdapterOutputStripsEventHandlers(): void
    {
        $result = $this->service->sanitizeAdapterOutput('<img src="x.png" onerror="alert(1)">');
        $this->assertSame('<img src="x.png">', $result);
    }

    public function testSanitizeAdapterOutputPreservesSafeHtml(): void
    {
        $html = '<h1>Title</h1><p>Text <b>bold</b></p><a href="https://example.com">link</a>';
        $result = $this->service->sanitizeAdapterOutput($html);
        $this->assertSame($html, $result);
    }

    public function testSanitizeAdapterOutputStripsJavascriptHref(): void
    {
        $result = $this->service->sanitizeAdapterOutput('<a href="javascript:alert(1)">Pay</a>');
        $this->assertSame('<a>Pay</a>', $result);
    }

    public function testSanitizeAdapterOutputStripsEncodedJavascriptHref(): void
    {
        $result = $this->service->sanitizeAdapterOutput('<a href="java&#x73;cript:alert(1)">Pay</a>');
        $this->assertSame('<a>Pay</a>', $result);
    }

    public function testSanitizeAdapterOutputStripsWhitespaceObfuscatedJavascriptHref(): void
    {
        $result = $this->service->sanitizeAdapterOutput("<a href=\"java\nscript:alert(1)\">Pay</a>");
        $this->assertSame('<a>Pay</a>', $result);
    }

    public function testSanitizeAdapterOutputStripsDataUriSrc(): void
    {
        $result = $this->service->sanitizeAdapterOutput('<iframe src="data:text/html,<script>alert(1)</script>"></iframe>');
        $this->assertSame('<iframe></iframe>', $result);
    }

    public function testSanitizeAdapterOutputHandlesEmptyString(): void
    {
        $result = $this->service->sanitizeAdapterOutput('');
        $this->assertSame('', $result);
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

        $this->assertSame('href="/admin/test" data-fb-api="{&quot;reload&quot;:true,&quot;type&quot;:&quot;link&quot;}"', $result);
        $this->assertStringNotContainsString('"href"', $result);
    }

    public function testBaseTwigEnvironmentFbApiEscapesAttributeJson(): void
    {
        $di = $this->getDi();
        $di['api_guest'] = new \stdClass();
        $di['session'] = $this->mockSession();

        $twig = $this->createBaseTwigEnvironment($di);
        $result = $twig->createTemplate("{{ fb_api_link({ modal: {type: 'confirm', content: content} }) }}")->render([
            'content' => 'Block IP 127.0.0.1\' autofocus onfocus=alert(1) x=\'',
        ]);

        $this->assertStringContainsString('data-fb-api="{&quot;modal&quot;:', $result);
        $this->assertStringContainsString('\u0027 autofocus', $result);
        $this->assertStringNotContainsString("' autofocus", $result);
        $this->assertStringNotContainsString("x='", $result);
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

    private function renderAdapterTemplate(string $template, array $vars = []): string
    {
        $apiGuest = new class {
            public function system_company(): array
            {
                return ['name' => 'Test'];
            }
        };

        $di = $this->getDi();
        $di['api_guest'] = $apiGuest;

        if (!isset($di['logger'])) {
            $di['logger'] = new class {
                public function setChannel(string $channel): self
                {
                    return $this;
                }

                public function warning(string $message, array $context = []): void
                {
                }
            };
        }

        $reflection = new \ReflectionClass(\FOSSBilling\Twig\TwigFactory::class);
        $twigFactory = $reflection->newInstanceWithoutConstructor();

        $diProperty = $reflection->getProperty('di');
        $diProperty->setValue($twigFactory, $di);

        $baseConfigProperty = $reflection->getProperty('baseConfig');
        $baseConfigProperty->setValue($twigFactory, ['cache' => false]);

        $di['twig_factory'] = $twigFactory;
        $this->service->setDi($di);

        return $this->service->renderAdapterTplString($template, $vars);
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
        $expected = 'Every Week';
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
