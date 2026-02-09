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
use Symfony\Component\HttpFoundation\Request;

beforeEach(function () {
    $this->service = new \Box\Mod\Spamchecker\Service();
});

test('dependency injection', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toEqual($di);
});

test('on before client sign up', function () {
    $spamCheckerService = Mockery::mock(\Box\Mod\Spamchecker\Service::class);
    $spamCheckerService->shouldReceive('isBlockedIp')
        ->atLeast()->once();
    $spamCheckerService->shouldReceive('isSpam')
        ->atLeast()->once();
    $spamCheckerService->shouldReceive('isTemp')
        ->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $spamCheckerService);
    $boxEventMock = Mockery::mock('\Box_Event');
    $boxEventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $this->service->onBeforeClientSignUp($boxEventMock);
});

test('on before guest public ticket open', function () {
    $spamCheckerService = Mockery::mock(\Box\Mod\Spamchecker\Service::class);
    $spamCheckerService->shouldReceive('isBlockedIp')
        ->atLeast()->once();
    $spamCheckerService->shouldReceive('isSpam')
        ->atLeast()->once();
    $spamCheckerService->shouldReceive('isTemp')
        ->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $spamCheckerService);
    $boxEventMock = Mockery::mock('\Box_Event');
    $boxEventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $this->service->onBeforeGuestPublicTicketOpen($boxEventMock);
});

test('is blocked ip ip not blocked', function () {
    $clientIp = '214.1.4.99';
    $modConfig = [
        'block_ips' => true,
        'blocked_ips' => '1.1.1.1' . PHP_EOL . '2.2.2.2',
    ];

    $di = container();
    $di['request'] = Request::createFromGlobals();
    $di['mod_config'] = $di->protect(function ($modName) use ($modConfig) {
        if ($modName == 'Spamchecker') {
            return $modConfig;
        }
    });

    $boxEventMock = Mockery::mock('\Box_Event');
    $boxEventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $this->service->isBlockedIp($boxEventMock);
});

test('is blocked ip block ips not enabled', function () {
    $modConfig = [
        'block_ips' => false,
    ];

    $di = container();
    $di['mod_config'] = $di->protect(function ($modName) use ($modConfig) {
        if ($modName == 'Spamchecker') {
            return $modConfig;
        }
    });

    $boxEventMock = Mockery::mock('\Box_Event');
    $boxEventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);

    $this->service->isBlockedIp($boxEventMock);
});

dataset('spam responses', function () {
    return [
        [
            '{"success" : "true", "username" : {"appears" : "true" }}', 'Your username is blacklisted in the Stop Forum Spam database',
        ],
        [
            '{"success" : "true", "email" : {"appears" : "true" }}', 'Your e-mail is blacklisted in the Stop Forum Spam database',
        ],
        [
            '{"success" : "true", "ip" : {"appears" : "true" }}', 'Your IP address is blacklisted in the Stop Forum Spam database',
        ],
    ];
});
