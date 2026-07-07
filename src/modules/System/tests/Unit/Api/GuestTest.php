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

test('getDi returns set dependency injection container', function (): void {
    $api = new Box\Mod\System\Api\Guest();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('company returns company data when public display is enabled', function (): void {
    $api = new Box\Mod\System\Api\Guest();
    $companyData = ['companyName' => 'TestCo'];

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive('isAdminLoggedIn')
        ->atLeast()->once()
        ->andReturn(false);
    $authMock->shouldReceive('isClientLoggedIn')
        ->atLeast()->once()
        ->andReturn(false);

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('getCompany')
    ->atLeast()->once()
    ->andReturn($companyData);
    $serviceMock
    ->shouldReceive('getParamValue')
    ->atLeast()->once()
        ->with('hide_company_public')
    ->andReturn(0);

    $di = container();
    $di['auth'] = $authMock;
    $api->setDi($di);
    $api->setService($serviceMock);
    $result = $api->company();

    expect($result)->toBeArray()->not->toBeEmpty();
});

test('company filters sensitive data when public display is disabled', function (): void {
    $api = new Box\Mod\System\Api\Guest();
    $companyData = [
        'companyName' => 'TestCo',
        'vat_number' => 'Test VAT',
        'email' => 'test@email.com',
        'tel' => '123456789',
        'account_number' => '987654321',
        'number' => '123456',
        'address_1' => 'Test Address 1',
        'address_2' => 'Test Address 2',
        'address_3' => 'Test Address 3',
    ];

    $authMock = Mockery::mock('\Box_Authorization');
    $authMock->shouldReceive('isAdminLoggedIn')
        ->atLeast()->once()
        ->andReturn(false);
    $authMock->shouldReceive('isClientLoggedIn')
        ->atLeast()->once()
        ->andReturn(false);

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('getCompany')
    ->atLeast()->once()
    ->andReturn($companyData);
    $serviceMock
    ->shouldReceive('getParamValue')
    ->atLeast()->once()
        ->with('hide_company_public')
    ->andReturn(1);

    $di = container();
    $di['auth'] = $authMock;
    $api->setDi($di);
    $api->setService($serviceMock);
    $result = $api->company();

    expect($result)->toBeArray();
    expect($result)->not->toHaveKey('vat_number');
    expect($result)->not->toHaveKey('email');
    expect($result)->not->toHaveKey('tel');
    expect($result)->not->toHaveKey('account_number');
    expect($result)->not->toHaveKey('number');
    expect($result)->not->toHaveKey('address_1');
    expect($result)->not->toHaveKey('address_2');
    expect($result)->not->toHaveKey('address_3');
});

test('period_title returns period title string', function (): void {
    $api = new Box\Mod\System\Api\Guest();
    $data = ['code' => 'periodCode'];

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('getPeriod')
    ->atLeast()->once()
    ->andReturn('periodTitleValue');
    $di = container();

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->period_title($data);
    expect($result)->toBeString();
});

test('period_title returns dash when code is missing', function (): void {
    $api = new Box\Mod\System\Api\Guest();
    $data = [];
    $expected = '-';
    $di = container();

    $api->setDi($di);
    $result = $api->period_title($data);
    expect($result)->toBeString()->toEqual($expected);
});

test('get_pending_messages returns and clears pending messages', function (): void {
    $api = new Box\Mod\System\Api\Guest();
    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $messageArr = ['Important message to user'];
    $serviceMock
    ->shouldReceive('getPendingMessages')
    ->atLeast()->once()
    ->andReturn($messageArr);

    $serviceMock->shouldReceive('clearPendingMessages')->atLeast()->once();

    $api->setService($serviceMock);
    $result = $api->get_pending_messages();
    expect($result)->toBeArray()->toEqual($messageArr);
});

test('timezones returns the grouped IANA timezone list', function (): void {
    $api = new Box\Mod\System\Api\Guest();
    $api->setDi(container());

    $result = $api->timezones();

    expect($result)->toBeArray();
    expect($result)->toHaveKey('UTC');
    expect($result['UTC'])->toContain('UTC');
    expect($result)->toHaveKey('America');
    expect($result['America'])->toContain('America/New_York');
    expect($result)->toHaveKey('Europe');
    expect($result['Europe'])->toContain('Europe/London');

    // The list should not contain invalid or empty entries.
    foreach ($result as $identifiers) {
        expect($identifiers)->toBeArray();
        foreach ($identifiers as $identifier) {
            expect($identifier)->toBeString()->not->toBeEmpty();
            expect(in_array($identifier, DateTimeZone::listIdentifiers(), true))->toBeTrue();
        }
    }
});

test('locale returns the active locale string', function (): void {
    $api = new Box\Mod\System\Api\Guest();
    $api->setDi(container());

    $result = $api->locale();

    expect($result)->toBeString()->not->toBeEmpty();
});
