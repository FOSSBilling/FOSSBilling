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

    $serviceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('getParams')
    ->atLeast()->once()
    ->andReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->get_params($data);
    expect($result)->toBeArray();
});

test('update params', function () {
    $data = [];

    $serviceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('updateParams')
    ->atLeast()->once()
    ->andReturn(true);

    $this->api->setService($serviceMock);

    $result = $this->api->update_params($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('messages', function () {
    $data = [];

    $di = container();

    $this->api->setDi($di);

    $serviceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('getMessages')
    ->atLeast()->once()
    ->andReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->messages($data);
    expect($result)->toBeArray();
});

test('template exists', function () {
    $data = [
        'file' => 'testing.txt',
    ];

    $serviceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('templateExists')
    ->atLeast()->once()
    ->andReturn(true);

    $this->api->setService($serviceMock);

    $result = $this->api->template_exists($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('string render', function () {
    $data = [
        '_tpl' => 'default',
    ];

    $serviceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('renderString')
    ->atLeast()->once()
    ->andReturn('returnStringType');
    $di = container();

    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->string_render($data);
    expect($result)->toBeString();
});

test('env', function () {
    $data = [];

    $serviceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('getEnv')
    ->atLeast()->once()
    ->andReturn([]);

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

    $staffServiceMock = Mockery::mock(\Box\Mod\Staff\Service::class);
    $staffServiceMock
    ->shouldReceive('hasPermission')
    ->atLeast()->once()
    ->andReturn(true);

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
