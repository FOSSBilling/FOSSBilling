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
    $this->api = new \Box\Mod\Staff\Api\Guest();
});

test('get di', function () {
        $di = container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        expect($getDi)->toEqual($di);
    });

    test('create', function () {
        $adminId = 1;

        $apiMock = $this->getMockBuilder(\Box\Mod\Staff\Api\Guest::class)
            ->onlyMethods(['login'])
            ->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('login');

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('createAdmin')
            ->willReturn($adminId);

        $validatorStub = $this->createStub(\FOSSBilling\Validate::class);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn([]);

        $di = container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorStub;

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
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

    test('create exception', function () {
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn([[]]);

        $di = container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $data = [
            'email' => 'example@fossbilling.org',
            'password' => 'EasyToGuess',
        ];

        expect(fn () => $this->api->create($data))
            ->toThrow(\FOSSBilling\Exception::class, 'Administrator account already exists');
    });

    test('login without email', function () {
        $guestApi = new \Box\Mod\Staff\Api\Guest();

        $apiHandler = new \Api_Handler(new \Model_Admin());
        $reflection = new \ReflectionClass($apiHandler);
        $method = $reflection->getMethod('validateRequiredParams');

        expect(fn () => $method->invokeArgs($apiHandler, [$guestApi, 'login', ['email' => null, 'password' => 'pass']]))
            ->toThrow(\FOSSBilling\InformationException::class);
    });

    test('login without password', function () {
        $guestApi = new \Box\Mod\Staff\Api\Guest();

        $di = container();
        $di['validator'] = new \FOSSBilling\Validate();

        $guestApi->setDi($di);
        expect(fn () => $guestApi->login(['email' => 'email@domain.com']))->toThrow(\FOSSBilling\Exception::class);
    });

    test('successful login', function () {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)
            ->disableOriginalConstructor()
            ->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Staff\Service::class)
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('login')
            ->willReturn([]);

        $sessionMock = $this->getMockBuilder(\FOSSBilling\Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $sessionMock->expects($this->atLeastOnce())
            ->method('delete');

        $di = container();

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;
        $di['session'] = $sessionMock;
        $di['validator'] = new \FOSSBilling\Validate();

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setMod($modMock);
        $guestApi->setService($serviceMock);
        $guestApi->setDi($di);
        $result = $guestApi->login(['email' => 'email@domain.com', 'password' => 'pass']);
        expect($result)->toBeArray();
    });

    test('login check ip exception', function () {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)
            ->disableOriginalConstructor()
            ->getMock();
        $configArr = [
            'allowed_ips' => '1.1.1.1' . PHP_EOL . '2.2.2.2',
            'check_ip' => true,
        ];
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn($configArr);

        $di = container();

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;
        $di['validator'] = new \FOSSBilling\Validate();

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setMod($modMock);
        $guestApi->setDi($di);
        $ip = '192.168.0.1';
        $guestApi->setIp($ip);

        $data = [
            'email' => 'email@domain.com',
            'password' => 'pass',
        ];
        expect(fn () => $guestApi->login($data))
            ->toThrow(\FOSSBilling\Exception::class, "You are not allowed to login to admin area from {$ip} address.");
    });
