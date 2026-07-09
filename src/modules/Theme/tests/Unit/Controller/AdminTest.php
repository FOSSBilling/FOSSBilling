<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

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
    $themeMock = Mockery::mock(Box\Mod\Theme\Model\Theme::class);
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

test('save theme settings reads body from request and strips preset control keys', function (): void {
    $controller = new Box\Mod\Theme\Controller\Admin();
    $di = container();

    $themeMock = Mockery::mock(Box\Mod\Theme\Model\Theme::class);
    $themeMock->shouldReceive('getName')->andReturn('huraga');
    $themeMock->shouldReceive('isAssetsPathWritable')->andReturn(true);

    $themeServiceMock = Mockery::mock(Box\Mod\Theme\Service::class);
    $themeServiceMock->shouldReceive('getTheme')->andReturn($themeMock);
    $themeServiceMock->shouldReceive('getCurrentThemePreset')->andReturn('default');
    $themeServiceMock->shouldReceive('setCurrentThemePreset')
        ->once()
        ->with($themeMock, 'MyPreset');
    $themeServiceMock->shouldReceive('updateSettings')
        ->once()
        ->with($themeMock, 'MyPreset', Mockery::on(fn (array $body): bool => !array_key_exists('save-current-setting', $body)
            && !array_key_exists('save-current-setting-preset', $body)
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
            && $event['params']['save-current-setting'] === '1'));

    $di['api_admin'] = Mockery::mock();
    $di['is_admin_logged'] = true;
    $di['mod'] = $di->protect(fn () => $modMock);
    $di['events_manager'] = $eventsManager;
    $controller->setDi($di);

    $request = Symfony\Component\HttpFoundation\Request::create('/theme/huraga', 'POST', [
        'color' => 'blue',
        'save-current-setting' => '1',
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
});
