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
        ->atLeast()
        ->once()
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
    $controller->get_theme($boxAppMock, 'huraga');
});
