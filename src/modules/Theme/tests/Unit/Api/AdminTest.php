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
use Box\Mod\Theme\Api\Admin;

beforeEach(function (): void {
    $this->api = new Admin();
});

test('testGetDi', function (): void {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toBe($di);
});

test('testGetList', function (): void {
    $systemServiceMock = $this->createMock(\Box\Mod\Theme\Service::class);
    $systemServiceMock->expects($this->atLeastOnce())
        ->method('getThemes')
        ->willReturn([]);

    $this->api->setService($systemServiceMock);

    $result = $this->api->get_list([]);
    expect($result)->toBeArray();
});

test('testGet', function (): void {
    $data = [
        'code' => 'themeCode',
    ];

    $systemServiceMock = $this->createMock(\Box\Mod\Theme\Service::class);
    $systemServiceMock->expects($this->atLeastOnce())
        ->method('loadTheme')
        ->willReturn([]);

    $di = container();
    $this->api->setDi($di);
    $this->api->setService($systemServiceMock);

    $result = $this->api->get($data);
    expect($result)->toBeArray();
});

test('testSelectNotAdminTheme', function (): void {
    $data = [
        'code' => 'pjw',
    ];

    $themeMock = $this->getMockBuilder(\Box\Mod\Theme\Model\Theme::class)->disableOriginalConstructor()->getMock();
    $themeMock->expects($this->atLeastOnce())
        ->method('isAdminAreaTheme')
        ->willReturn(false);

    $serviceMock = $this->createMock(\Box\Mod\Theme\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getTheme')
        ->willReturn($themeMock);

    $systemServiceMock = $this->createMock(\Box\Mod\System\Service::class);
    $systemServiceMock->expects($this->atLeastOnce())
        ->method('setParamValue')
        ->with($this->equalTo('theme'));

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->select($data);
    expect($result)->toBeTrue();
});

test('testSelectAdminTheme', function (): void {
    $data = [
        'code' => 'pjw',
    ];

    $themeMock = $this->getMockBuilder(\Box\Mod\Theme\Model\Theme::class)->disableOriginalConstructor()->getMock();
    $themeMock->expects($this->atLeastOnce())
        ->method('isAdminAreaTheme')
        ->willReturn(true);

    $serviceMock = $this->createMock(\Box\Mod\Theme\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getTheme')
        ->willReturn($themeMock);

    $systemServiceMock = $this->createMock(\Box\Mod\System\Service::class);
    $systemServiceMock->expects($this->atLeastOnce())
        ->method('setParamValue')
        ->with($this->equalTo('admin_theme'));

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->select($data);
    expect($result)->toBeTrue();
});

test('testPresetDelete', function (): void {
    $data = [
        'code' => 'themeCode',
        'preset' => 'themePreset',
    ];

    $themeStub = $this->createStub(\Box\Mod\Theme\Model\Theme::class);

    $serviceMock = $this->createMock(\Box\Mod\Theme\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getTheme')
        ->willReturn($themeStub);
    $serviceMock->expects($this->atLeastOnce())
        ->method('deletePreset');

    $di = container();
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->preset_delete($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testPresetSelect', function (): void {
    $data = [
        'code' => 'themeCode',
        'preset' => 'themePreset',
    ];

    $themeStub = $this->createStub(\Box\Mod\Theme\Model\Theme::class);

    $serviceMock = $this->createMock(\Box\Mod\Theme\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getTheme')
        ->willReturn($themeStub);
    $serviceMock->expects($this->atLeastOnce())
        ->method('setCurrentThemePreset');

    $di = container();
    $this->api->setDi($di);

    $this->api->setService($serviceMock);

    $result = $this->api->preset_select($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});
