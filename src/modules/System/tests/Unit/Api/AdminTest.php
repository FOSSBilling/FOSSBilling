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

test('dependency injection', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('get params', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $data = [];

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('getParams')
    ->atLeast()->once()
    ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->get_params($data);
    expect($result)->toBeArray();
});

test('update params', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $data = [];

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('updateParams')
    ->atLeast()->once()
    ->andReturn(true);

    $api->setService($serviceMock);

    $result = $api->update_params($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('messages', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $data = [];

    $di = container();

    $api->setDi($di);

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('getMessages')
    ->atLeast()->once()
    ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->messages($data);
    expect($result)->toBeArray();
});

test('template exists', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $data = [
        'file' => 'testing.txt',
    ];

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('templateExists')
    ->atLeast()->once()
    ->andReturn(true);

    $api->setService($serviceMock);

    $result = $api->template_exists($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('string render', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $data = [
        '_tpl' => 'default',
    ];

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('renderString')
    ->atLeast()->once()
    ->andReturn('returnStringType');
    $di = container();

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->string_render($data);
    expect($result)->toBeString();
});

test('env', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $data = [];

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('getEnv')
    ->atLeast()->once()
    ->andReturn([]);

    $di = container();

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->env($data);
    expect($result)->toBeArray();
});

test('is allowed', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $data = [
        'mod' => 'extension',
    ];

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffServiceMock
    ->shouldReceive('hasPermission')
    ->atLeast()->once()
    ->andReturn(true);

    $validatorStub = $this->createStub(FOSSBilling\Validate::class);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
        if ($serviceName == 'Staff') {
            return $staffServiceMock;
        }

        return false;
    });
    $di['validator'] = $validatorStub;
    $api->setDi($di);

    $result = $api->is_allowed($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});
