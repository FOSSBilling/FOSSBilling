<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Theme\Event\BeforeAdminThemeSettingsSaveEvent;
use Box\Mod\Theme\Model\Theme;
use FOSSBilling\Sanitizer\BrowserHtmlSanitizer;
use FOSSBilling\Twig\SandboxedStringRenderer;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

use function Tests\Helpers\container;

/**
 * @param array<string, mixed> $settings
 *
 * @return list<DOMElement>
 */
function renderClientThemeFooterLinkCheckboxes(array $settings): array
{
    $twig = new Environment(new ArrayLoader(), ['strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', static fn (mixed $value): string => (string) $value));

    $theme = new Theme('default/client');
    $html = SandboxedStringRenderer::render(
        $twig,
        $theme->getSettingsPageHtml(),
        ['settings' => $settings],
        'Theme settings template',
    );
    $html = BrowserHtmlSanitizer::sanitizeThemeSettingsHtml($html);

    $document = new DOMDocument();
    $previousLibxmlErrorsSetting = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>');
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlErrorsSetting);

    $checkboxes = (new DOMXPath($document))->query('//input[@type="checkbox" and starts-with(@name, "footer_link_")]');
    assert($checkboxes instanceof DOMNodeList);

    return array_values(array_filter(
        iterator_to_array($checkboxes),
        static fn (DOMNode $node): bool => $node instanceof DOMElement,
    ));
}

test('getDi returns dependency injection container', function (): void {
    $controller = new Box\Mod\Theme\Controller\Admin();
    $di = container();
    $controller->setDi($di);
    $result = $controller->getDi();
    expect($result)->toEqual($di);
});

test('register configures routes', function (): void {
    $controller = new Box\Mod\Theme\Controller\Admin();
    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('get')
        ->atLeast()
        ->once();
    $boxAppMock->shouldReceive('post')
        ->atLeast()
        ->once();

    $controller->register($boxAppMock);
});

test('getTheme renders theme preset', function (): void {
    $controller = new Box\Mod\Theme\Controller\Admin();
    $di = container();

    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('render')
        ->atLeast()
        ->once()
        ->andReturn('Rendering ...');

    // Create theme mock first
    $themeMock = Mockery::mock(Theme::class);
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('test_theme');
    $themeMock->shouldReceive('getUploadedAssets')
        ->atLeast()
        ->once()
        ->andReturn([]);
    $themeMock->shouldReceive('isAssetsPathWritable')
        ->atLeast()
        ->once()
        ->andReturn(false);
    $themeMock->shouldReceive('getSettingsPageHtml')
        ->zeroOrMoreTimes()
        ->andReturn('');
    $themeMock->shouldReceive('getPathAssets')
        ->atLeast()
        ->once()
        ->andReturn('/tmp/test');

    // Create service mock that uses theme mock
    $themeServiceMock = Mockery::mock(Box\Mod\Theme\Service::class);
    $themeServiceMock->shouldReceive('getTheme')
        ->atLeast()
        ->once()
        ->andReturn($themeMock);
    $themeServiceMock->shouldReceive('getCurrentThemePreset')
        ->atLeast()
        ->once()
        ->andReturn('default');
    $themeServiceMock->shouldReceive('getThemeSettings')
        ->atLeast()
        ->once()
        ->andReturn([]);
    $themeServiceMock->shouldReceive('renderThemeSettingsPageHtml')
        ->atLeast()
        ->once()
        ->andReturn('');
    $themeServiceMock->shouldReceive('getThemePresets')
        ->atLeast()
        ->once()
        ->andReturn([]);

    // Create a mod mock that returns the service via getService()
    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getService')
        ->atLeast()
        ->once()
        ->andReturn($themeServiceMock);

    $di['is_admin_logged'] = true;
    $di['mod'] = $di->protect(fn () => $modMock);
    $controller->setDi($di);

    $boxAppMock->shouldReceive('getRequest')->andReturn(Symfony\Component\HttpFoundation\Request::create('/theme/default/client'));
    $controller->get_theme($boxAppMock, 'default/client');
});

test('save theme settings dispatches safe typed event and strips preset control keys', function (bool $newPreset): void {
    $controller = new Box\Mod\Theme\Controller\Admin();
    $di = container();
    $steps = [];
    $events = [];

    $themeMock = Mockery::mock(Theme::class);
    $themeMock->shouldReceive('getName')->andReturn('default/client');
    $themeMock->shouldReceive('isAssetsPathWritable')->andReturn(true);

    $themeServiceMock = Mockery::mock(Box\Mod\Theme\Service::class);
    $themeServiceMock->shouldReceive('getTheme')->andReturn($themeMock);
    $themeServiceMock->shouldReceive('getCurrentThemePreset')->andReturn('default');
    if ($newPreset) {
        $themeServiceMock->shouldReceive('setCurrentThemePreset')->once()->with($themeMock, 'MyPreset');
    } else {
        $themeServiceMock->shouldNotReceive('setCurrentThemePreset');
    }
    $themeServiceMock->shouldReceive('updateSettings')
        ->once()
        ->with($themeMock, $newPreset ? 'MyPreset' : 'default', Mockery::on(fn (array $body): bool => !array_key_exists('save-current-setting', $body)
            && !array_key_exists('save-current-setting-preset', $body)
            && !array_key_exists('CSRFToken', $body)
            && $body['inject_javascript'] === '<script>window.themeControl = true;</script>'
            && $body['color'] === 'blue'
            && $body['api_key'] === 'never-expose-this-value'));
    $themeServiceMock->shouldReceive('regenerateThemeCssAndJsFiles');
    $themeServiceMock->shouldReceive('regenerateThemeSettingsDataFile');

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getService')->andReturn($themeServiceMock);

    $eventDispatcher = new class($steps, $events) {
        public function __construct(private array &$steps, private array &$events)
        {
        }

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->steps[] = 'event';
            $this->events[] = $event;

            return $event;
        }
    };

    $di['api_admin'] = Mockery::mock();
    $di['session']->shouldReceive('get')->with('csrf_token')->andReturn('valid-token');
    $di['mod_service']('Staff')->shouldReceive('checkPermissionsAndThrowException')->once()->with('theme', 'manage_settings');
    $di['is_admin_logged'] = true;
    $di['mod'] = $di->protect(function () use ($modMock, &$steps) {
        $steps[] = 'module';

        return $modMock;
    });
    $di['event_dispatcher'] = $eventDispatcher;
    $controller->setDi($di);

    $request = Symfony\Component\HttpFoundation\Request::create('/theme/default/client', 'POST', [
        'CSRFToken' => 'valid-token',
        'inject_javascript' => '<script>window.themeControl = true;</script>',
        'color' => 'blue',
        'api_key' => 'never-expose-this-value',
        'save-current-setting' => $newPreset ? '1' : '0',
        'save-current-setting-preset' => 'My Preset',
    ]);

    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('getRequest')->once()->andReturn($request);
    $boxAppMock->shouldReceive('redirect')
        ->once()
        ->with('/theme/default/client')
        ->andReturn(new Symfony\Component\HttpFoundation\RedirectResponse('/theme/default/client'));

    $response = $controller->save_theme_settings($boxAppMock, 'default/client');
    expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\RedirectResponse::class)
        ->and($steps)->toBe(['event', 'module'])
        ->and($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(BeforeAdminThemeSettingsSaveEvent::class)
        ->and($events[0]->themeName)->toBe('default/client')
        ->and($events[0]->settingNames)->toBe(['inject_javascript', 'color', 'api_key'])
        ->and($events[0]->settingNames)->not->toContain('never-expose-this-value');
})->with([true, false]);

test('default/client footer link checkboxes submit canonical enabled values', function (): void {
    $checkboxes = renderClientThemeFooterLinkCheckboxes([]);

    expect($checkboxes)->toHaveCount(5);
    foreach ($checkboxes as $index => $checkbox) {
        expect($checkbox->getAttribute('name'))->toBe(sprintf('footer_link_%d_enabled', $index + 1))
            ->and($checkbox->getAttribute('value'))->toBe('1')
            ->and($checkbox->hasAttribute('checked'))->toBeFalse();
    }
});

test('theme settings restore checkbox values saved with the browser default', function (): void {
    $settings = [];
    for ($index = 1; $index <= 5; ++$index) {
        $settings['footer_link_' . $index . '_enabled'] = 'on';
    }

    $checkboxes = renderClientThemeFooterLinkCheckboxes($settings);

    expect($checkboxes)->toHaveCount(5);
    foreach ($checkboxes as $checkbox) {
        expect($checkbox->hasAttribute('checked'))->toBeTrue();
    }
});

function themeStaffWithPermissions(Pimple\Container $di, array $permissions): Box\Mod\Staff\Service
{
    $member = Mockery::mock(Box\Mod\Staff\Entity\Admin::class);
    $member->shouldReceive('getId')->andReturn(42);
    $member->shouldReceive('isCron')->andReturn(false);
    $di['loggedin_admin'] = $member;
    $di['auth']->shouldReceive('isAdminLoggedIn')->andReturn(true);

    $staff = Mockery::mock(Box\Mod\Staff\Service::class)->makePartial();
    $staff->shouldReceive('isSuperAdministrator')->with(42)->andReturn(false);
    $staff->shouldReceive('getPermissions')->with(42)->andReturn(['theme' => $permissions]);
    $extension = Mockery::mock(Box\Mod\Extension\Service::class);
    $extension->shouldReceive('getSpecificModulePermissions')->with('theme')->andReturn([]);
    $di['mod_service'] = $di->protect(static fn (string $name): object => strtolower($name) === 'staff' ? $staff : $extension);
    $staff->setDi($di);

    return $staff;
}

test('theme save rejects restricted staff before any side effects', function (array $permissions): void {
    $di = container();
    $di['api_admin'] = Mockery::mock();
    $staff = themeStaffWithPermissions($di, $permissions);
    expect($staff->hasPermission(null, 'theme'))->toBeTrue();
    $di['mod'] = $di->protect(static fn () => throw new LogicException('Module must not be resolved'));
    $dispatcher = Mockery::mock();
    $dispatcher->shouldNotReceive('dispatch');
    $di['event_dispatcher'] = $dispatcher;
    $app = Mockery::mock(Box_App::class);
    $app->shouldNotReceive('getRequest');
    $controller = new Box\Mod\Theme\Controller\Admin();
    $controller->setDi($di);

    expect(fn () => $controller->save_theme_settings($app, 'default/client'))
        ->toThrow(FOSSBilling\InformationException::class, 'theme.manage_settings', 403);
})->with([
    'missing permission' => [['access' => true, 'view' => true]],
    'false permission' => [['access' => true, 'view' => true, 'manage_settings' => false]],
    'preset manager only' => [['access' => true, 'view' => true, 'manage' => true]],
]);

test('theme settings page requires view permission', function (): void {
    $di = container();
    $di['is_admin_logged'] = true;
    themeStaffWithPermissions($di, ['access' => true]);
    $controller = new Box\Mod\Theme\Controller\Admin();
    $controller->setDi($di);
    expect(fn () => $controller->get_theme(Mockery::mock(Box_App::class), 'default/client'))
        ->toThrow(FOSSBilling\InformationException::class, 'theme.view', 403);
});

test('theme save rejects invalid CSRF tokens before any side effects', function (mixed $token, mixed $sessionToken): void {
    $di = container();
    $di['api_admin'] = Mockery::mock();
    themeStaffWithPermissions($di, ['access' => true, 'manage_settings' => true]);
    $di['session']->shouldReceive('get')->with('csrf_token')->andReturn($sessionToken);
    $di['mod'] = $di->protect(static fn () => throw new LogicException('Module must not be resolved'));
    $dispatcher = Mockery::mock();
    $dispatcher->shouldNotReceive('dispatch');
    $di['event_dispatcher'] = $dispatcher;
    $request = Symfony\Component\HttpFoundation\Request::create('/theme/default/client', 'POST', [
        'CSRFToken' => $token,
        'inject_javascript' => '<script>alert(1)</script>',
        'save-current-setting' => '1',
        'save-current-setting-preset' => 'Malicious',
    ]);
    $app = Mockery::mock(Box_App::class);
    $app->shouldReceive('getRequest')->once()->andReturn($request);
    $controller = new Box\Mod\Theme\Controller\Admin();
    $controller->setDi($di);

    expect(fn () => $controller->save_theme_settings($app, 'default/client'))
        ->toThrow(FOSSBilling\InformationException::class, 'CSRF token invalid', 403);
})->with([
    'missing' => [null, 'valid-token'],
    'mismatch' => ['wrong-token', 'valid-token'],
    'array' => [['valid-token'], 'valid-token'],
    'empty session' => ['', ''],
    'missing session' => ['valid-token', null],
]);

test('theme settings form renders the session CSRF token', function (): void {
    $renderer = new Tests\Support\StrictTemplateRenderer();
    $html = $renderer->renderTemplate(PATH_MODS . '/Theme/templates/admin/mod_theme_preset.html.twig', [
        'info' => null,
        'error' => null,
        'theme_code' => 'default/client',
        'settings_html' => new Twig\Markup('<input name="color">', 'UTF-8'),
        'current_preset' => 'Default',
        'presets' => ['Default'],
        'settings' => [],
        'uploaded' => [],
        'snippets' => [],
        'CSRFToken' => 'session-token',
    ]);
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $tokens = (new DOMXPath($document))->query('//form[@method="post"]/input[@type="hidden" and @name="CSRFToken"]');

    expect($tokens->length)->toBe(1)
        ->and($tokens->item(0)->getAttribute('value'))->toBe('session-token');
});
