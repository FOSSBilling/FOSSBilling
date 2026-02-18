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

test('get di', function (): void {
    $api = new Box\Mod\Staff\Api\Guest();
    $api = new Box\Mod\Staff\Api\Guest();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('create', function (): void {
    $api = new Box\Mod\Staff\Api\Guest();
    $adminId = 1;

    $apiMock = Mockery::mock(Box\Mod\Staff\Api\Guest::class)->makePartial();
    $apiMock->shouldReceive('login')->atLeast()->once();

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('createAdmin')
    ->atLeast()->once()
    ->andReturn($adminId);

    $validatorStub = $this->createStub(FOSSBilling\Validate::class);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $di['validator'] = $validatorStub;

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();
    $di['tools'] = $toolsMock;

    $apiMock->setDi($di);
    $apiMock->setService($serviceMock);

    $data = [
        'email' => 'example@fossbilling.org',
        'password' => 'EasyToGuess',
    ];
    $result = $apiMock->create($data);
    expect($result)->toBeTrue();
});

test('create exception', function (): void {
    $api = new Box\Mod\Staff\Api\Guest();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn([[]]);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);

    $data = [
        'email' => 'example@fossbilling.org',
        'password' => 'EasyToGuess',
    ];

    expect(fn () => $api->create($data))
        ->toThrow(FOSSBilling\Exception::class, 'Administrator account already exists');
});

test('login without email', function (): void {
    $api = new Box\Mod\Staff\Api\Guest();
    $guestApi = new Box\Mod\Staff\Api\Guest();

    $apiHandler = new Api_Handler(new Model_Admin());
    $reflection = new ReflectionClass($apiHandler);
    $method = $reflection->getMethod('validateRequiredParams');

    expect(fn () => $method->invokeArgs($apiHandler, [$guestApi, 'login', ['email' => null, 'password' => 'pass']]))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('login without password', function (): void {
    $api = new Box\Mod\Staff\Api\Guest();
    $guestApi = new Box\Mod\Staff\Api\Guest();

    $di = container();
    $di['validator'] = new FOSSBilling\Validate();

    $guestApi->setDi($di);
    expect(fn () => $guestApi->login(['email' => 'email@domain.com']))->toThrow(FOSSBilling\Exception::class);
});

test('successful login', function (): void {
    $api = new Box\Mod\Staff\Api\Guest();
    $modMock = Mockery::mock('\\' . FOSSBilling\Module::class);
    $modMock
    ->shouldReceive('getConfig')
    ->atLeast()->once()
    ->andReturn([]);

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('login')
    ->atLeast()->once()
    ->andReturn([]);

    $sessionMock = Mockery::mock(FOSSBilling\Session::class);
    $sessionMock->shouldReceive('delete')->atLeast()->once();

    $di = container();

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();
    $di['tools'] = $toolsMock;
    $di['session'] = $sessionMock;
    $di['validator'] = new FOSSBilling\Validate();

    $guestApi = new Box\Mod\Staff\Api\Guest();
    $guestApi->setMod($modMock);
    $guestApi->setService($serviceMock);
    $guestApi->setDi($di);
    $result = $guestApi->login(['email' => 'email@domain.com', 'password' => 'pass']);
    expect($result)->toBeArray();
});

test('login check ip exception', function (): void {
    $api = new Box\Mod\Staff\Api\Guest();
    $modMock = Mockery::mock('\\' . FOSSBilling\Module::class);
    $configArr = [
        'allowed_ips' => '1.1.1.1' . PHP_EOL . '2.2.2.2',
        'check_ip' => true,
    ];
    $modMock
    ->shouldReceive('getConfig')
    ->atLeast()->once()
    ->andReturn($configArr);

    $di = container();

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();
    $di['tools'] = $toolsMock;
    $di['validator'] = new FOSSBilling\Validate();

    $guestApi = new Box\Mod\Staff\Api\Guest();
    $guestApi->setMod($modMock);
    $guestApi->setDi($di);
    $ip = '192.168.0.1';
    $guestApi->setIp($ip);

    $data = [
        'email' => 'email@domain.com',
        'password' => 'pass',
    ];
    expect(fn () => $guestApi->login($data))
        ->toThrow(FOSSBilling\Exception::class, "You are not allowed to login to admin area from {$ip} address.");
});
