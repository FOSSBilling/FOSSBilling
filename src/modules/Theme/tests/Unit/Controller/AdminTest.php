<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

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
function renderHuragaFooterLinkCheckboxes(array $settings): array
{
    $twig = new Environment(new ArrayLoader(), ['strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', static fn (mixed $value): string => (string) $value));

    $theme = new Theme('huraga');
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

    $di['db'] = $this->createStub('Box_Database');
    $di['is_admin_logged'] = true;
    $di['mod'] = $di->protect(fn () => $modMock);
    $controller->setDi($di);

    $boxAppMock->shouldReceive('getRequest')->andReturn(Symfony\Component\HttpFoundation\Request::create('/theme/huraga'));
    $controller->get_theme($boxAppMock, 'huraga');
});

test('save theme settings reads body from request and strips preset control keys', function (bool $newPreset): void {
    $controller = new Box\Mod\Theme\Controller\Admin();
    $di = container();

    $themeMock = Mockery::mock(Theme::class);
    $themeMock->shouldReceive('getName')->andReturn('huraga');
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
            && $body['color'] === 'blue'));
    $themeServiceMock->shouldReceive('regenerateThemeCssAndJsFiles');
    $themeServiceMock->shouldReceive('regenerateThemeSettingsDataFile');

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getService')->andReturn($themeServiceMock);

    $eventsManager = Mockery::mock();
    $eventsManager->shouldReceive('fire')
        ->once()
        ->with(Mockery::on(fn (array $event): bool => $event['event'] === 'onBeforeThemeSettingsSave'
            && $event['params']['color'] === 'blue'
            && $event['params']['save-current-setting'] === ($newPreset ? '1' : '0')));

    $staffMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffMock->shouldReceive('checkPermissionsAndThrowException')->once()->with('theme', 'manage_settings');

    $di['api_admin'] = Mockery::mock();
    $di['is_admin_logged'] = true;
    $di['mod'] = $di->protect(fn () => $modMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffMock);
    $di['events_manager'] = $eventsManager;
    $di['session'] = Mockery::mock(FOSSBilling\Session::class)->shouldIgnoreMissing();
    $di['session']->shouldReceive('get')->with('csrf_token')->andReturn('valid-token');
    $controller->setDi($di);

    $request = Symfony\Component\HttpFoundation\Request::create('/theme/huraga', 'POST', [
        'CSRFToken' => 'valid-token',
        'color' => 'blue',
        'save-current-setting' => $newPreset ? '1' : '0',
        'save-current-setting-preset' => 'My Preset',
    ]);

    $boxAppMock = Mockery::mock('\Box_App');
    $boxAppMock->shouldReceive('getRequest')->once()->andReturn($request);
    $boxAppMock->shouldReceive('redirect')
        ->once()
        ->with('/theme/huraga')
        ->andReturn(new Symfony\Component\HttpFoundation\RedirectResponse('/theme/huraga'));

    $response = $controller->save_theme_settings($boxAppMock, 'huraga');
    expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\RedirectResponse::class);
})->with([true, false]);

test('theme save rejects restricted staff before any side effects', function (): void {
    $controller = new Box\Mod\Theme\Controller\Admin();
    $di = container();

    $staffMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffMock->shouldReceive('checkPermissionsAndThrowException')->once()->with('theme', 'manage_settings')
        ->andThrow(new FOSSBilling\InformationException('Permission denied', null, 403));

    $di['api_admin'] = Mockery::mock();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffMock);
    $di['mod'] = $di->protect(static fn () => throw new LogicException('Module must not be resolved'));
    $controller->setDi($di);

    $app = Mockery::mock('\Box_App');
    $app->shouldNotReceive('getRequest');

    expect(fn () => $controller->save_theme_settings($app, 'huraga'))
        ->toThrow(FOSSBilling\InformationException::class, 'Permission denied', 403);
});

test('theme settings page requires view permission', function (): void {
    $controller = new Box\Mod\Theme\Controller\Admin();
    $di = container();
    $di['is_admin_logged'] = true;

    $staffMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffMock->shouldReceive('checkPermissionsAndThrowException')->once()->with('theme', 'view')
        ->andThrow(new FOSSBilling\InformationException('Permission denied', null, 403));
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffMock);
    $controller->setDi($di);

    expect(fn () => $controller->get_theme(Mockery::mock('\Box_App'), 'huraga'))
        ->toThrow(FOSSBilling\InformationException::class, 'Permission denied', 403);
});

test('theme save rejects invalid CSRF tokens before any side effects', function (mixed $token, mixed $sessionToken): void {
    $controller = new Box\Mod\Theme\Controller\Admin();
    $di = container();
    $di['api_admin'] = Mockery::mock();

    $staffMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffMock->shouldReceive('checkPermissionsAndThrowException')->once()->with('theme', 'manage_settings');
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $staffMock);

    $sessionMock = Mockery::mock(FOSSBilling\Session::class);
    $sessionMock->shouldReceive('get')->with('csrf_token')->andReturn($sessionToken);
    $di['session'] = $sessionMock;

    $di['mod'] = $di->protect(static fn () => throw new LogicException('Module must not be resolved'));
    $controller->setDi($di);

    $request = Symfony\Component\HttpFoundation\Request::create('/theme/huraga', 'POST', [
        'CSRFToken' => $token,
        'color' => 'blue',
    ]);
    $app = Mockery::mock('\Box_App');
    $app->shouldReceive('getRequest')->once()->andReturn($request);

    expect(fn () => $controller->save_theme_settings($app, 'huraga'))
        ->toThrow(FOSSBilling\InformationException::class, 'CSRF token invalid', 403);
})->with([
    'missing' => [null, 'valid-token'],
    'mismatch' => ['wrong-token', 'valid-token'],
    'array' => [['valid-token'], 'valid-token'],
    'empty session' => ['', ''],
    'missing session' => ['valid-token', null],
]);

test('huraga footer link checkboxes submit canonical enabled values', function (): void {
    $checkboxes = renderHuragaFooterLinkCheckboxes([]);

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

    $checkboxes = renderHuragaFooterLinkCheckboxes($settings);

    expect($checkboxes)->toHaveCount(5);
    foreach ($checkboxes as $checkbox) {
        expect($checkbox->hasAttribute('checked'))->toBeTrue();
    }
});

test('theme settings form renders the session CSRF token', function (): void {
    $renderer = new Tests\Support\StrictTemplateRenderer();
    $html = $renderer->renderTemplate(PATH_MODS . '/Theme/templates/admin/mod_theme_preset.html.twig', [
        'info' => null,
        'error' => null,
        'theme_code' => 'huraga',
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
