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
    $this->api = new \Box\Mod\System\Api\Admin();
});

test('dependency injection', function () {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toEqual($di);
});

test('get params', function () {
    $data = [];

    $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getParams')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->get_params($data);
    expect($result)->toBeArray();
});

test('update params', function () {
    $data = [];

    $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('updateParams')
        ->willReturn(true);

    $this->api->setService($serviceMock);

    $result = $this->api->update_params($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('messages', function () {
    $data = [];

    $di = container();

    $this->api->setDi($di);

    $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getMessages')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->messages($data);
    expect($result)->toBeArray();
});

test('template exists', function () {
    $data = [
        'file' => 'testing.txt',
    ];

    $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('templateExists')
        ->willReturn(true);

    $this->api->setService($serviceMock);

    $result = $this->api->template_exists($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('string render', function () {
    $data = [
        '_tpl' => 'default',
    ];

    $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('renderString')
        ->willReturn('returnStringType');
    $di = container();

    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->string_render($data);
    expect($result)->toBeString();
});

test('env', function () {
    $data = [];

    $serviceMock = $this->createMock(\Box\Mod\System\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getEnv')
        ->willReturn([]);

    $di = container();

    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->env($data);
    expect($result)->toBeArray();
});

test('is allowed', function () {
    $data = [
        'mod' => 'extension',
    ];

    $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
    $staffServiceMock->expects($this->atLeastOnce())
        ->method('hasPermission')
        ->willReturn(true);

    $validatorStub = $this->createStub(\FOSSBilling\Validate::class);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
        if ($serviceName == 'Staff') {
            return $staffServiceMock;
        }

        return false;
    });
    $di['validator'] = $validatorStub;
    $this->api->setDi($di);

    $result = $this->api->is_allowed($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});
