<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

beforeEach(function () {
    $this->controller = new \Box\Mod\Theme\Controller\Admin();
});

test('getDi returns dependency injection container', function () {
    $di = container();
    $db = $this->createStub('Box_Database');

    $di['db'] = $db;
    $this->controller->setDi($di);
    $result = $this->controller->getDi();
    expect($result)->toEqual($di);
});

test('register configures routes', function () {
    $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
    $boxAppMock->expects($this->exactly(1))
        ->method('get');

    $this->controller->register($boxAppMock);
});

test('getTheme renders theme preset', function () {
    $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
    $boxAppMock->expects($this->atLeastOnce())
        ->method('render')
        ->with('mod_theme_preset')
        ->willReturn('Rendering ...');

    $themeMock = $this->getMockBuilder(\Box\Mod\Theme\Model\Theme::class)->disableOriginalConstructor()->getMock();
    $themeMock->expects($this->atLeastOnce())
        ->method('getSettingsPageHtml')
        ->willReturn('');
    $themeMock->expects($this->atLeastOnce())
        ->method('getName');
    $themeMock->expects($this->atLeastOnce())
        ->method('getUploadedAssets');
    $themeMock->expects($this->atLeastOnce())
        ->method('isAssetsPathWritable')
        ->willReturn(false);

    $themeServiceMock = $this->createMock(\Box\Mod\Theme\Service::class);
    $themeServiceMock->expects($this->atLeastOnce())
        ->method('getTheme')
        ->willReturn($themeMock);
    $themeServiceMock->expects($this->atLeastOnce())
        ->method('getCurrentThemePreset')
        ->willReturn('default');
    $themeServiceMock->expects($this->atLeastOnce())
        ->method('getThemeSettings');
    $themeServiceMock->expects($this->atLeastOnce())
        ->method('getThemePresets');

    $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
    $modMock->expects($this->atLeastOnce())
        ->method('getService')
        ->willReturn($themeServiceMock);

    $di = container();
    $di['mod'] = $di->protect(function ($name) use ($modMock) {
        if ($name == 'theme') {
            return $modMock;
        }
    });

    $di['is_admin_logged'] = true;

    $this->controller->setDi($di);
    $this->controller->get_theme($boxAppMock, 'huraga');
});
