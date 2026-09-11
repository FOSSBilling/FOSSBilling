<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);
use Box\Mod\Profile\Service;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('gets dependency injection container', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('gets admin identity array', function (): void {
    $model = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $service = new Service();
    $result = $service->getAdminIdentityArray($model);
    expect($result)->toBeArray();
});

test('updates admin', function (): void {
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;

    $model = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $data = [
        'signature' => 'new signature',
        'email' => 'example@gmail.com',
        'name' => 'Admin',
    ];

    $service = new Service();
    $service->setDi($di);
    $result = $service->updateAdmin($model, $data);
    expect($result)->toBeTrue();
});

test('generates new api key', function (): void {
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;
    $di['tools'] = new FOSSBilling\Tools();

    $model = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $service = new Service();
    $service->setDi($di);

    $result = $service->generateNewApiKey($model);
    expect($result)->toBeTrue();
});

test('changes admin password', function (): void {
    $password = 'new_pass';
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $passwordMock = Mockery::mock(FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')
        ->with($password);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;
    $di['password'] = $passwordMock;

    $model = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $service = new Service();
    $service->setDi($di);

    $result = $service->changeAdminPassword($model, $password);
    expect($result)->toBeTrue();
});

test('updates client', function (): void {
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([
            'disable_change_email' => 0,
        ]);

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail');

    $clientServiceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $clientServiceMock->shouldReceive('emailAlreadyRegistered')
        ->andReturn(false);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;
    $di['mod_service'] = $di->protect(fn ($name): Mockery\MockInterface => $clientServiceMock);
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);
    $di['tools'] = $toolsMock;

    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $data = [
        'email' => 'email@example.com',
        'billing_email' => 'billing@example.com',
        'first_name' => 'string',
        'last_name' => 'string',
        'gender' => 'other',
        'birthday' => '1981-01-01',
        'company' => 'string',
        'company_vat' => 'string',
        'company_number' => 'string',
        'type' => 'string',
        'address_1' => 'string',
        'address_2' => 'string',
        'phone_cc' => random_int(10, 300),
        'phone' => random_int(10000, 90000),
        'country' => 'US',
        'postcode' => 'string',
        'city' => 'string',
        'state' => 'string',
        'lang' => 'en_US',
        'notes' => 'string',
        'custom_1' => 'string',
        'custom_2' => 'string',
        'custom_3' => 'string',
        'custom_4' => 'string',
        'custom_5' => 'string',
        'custom_6' => 'string',
        'custom_7' => 'string',
        'custom_8' => 'string',
        'custom_9' => 'string',
        'custom_10' => 'string',
        'custom_11' => 'string',
        'custom_12' => 'string',
        'custom_13' => 'string',
        'custom_14' => 'string',
        'custom_15' => 'string',
        'custom_16' => 'string',
        'custom_17' => 'string',
        'custom_18' => 'string',
        'custom_19' => 'string',
        'custom_20' => 'string',
    ];

    $service = new Service();
    $service->setDi($di);
    $result = $service->updateClient($model, $data);
    expect($result)->toBeTrue();
    expect($model->getBillingEmail())->toBe('billing@example.com');
});

test('throws exception when email change is not allowed', function (): void {
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->never()
        ->andReturn(true);

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([
            'disable_change_email' => 1,
        ]);

    $clientServiceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $clientServiceMock->shouldReceive('emailAlreadyRegistered')
        ->never()
        ->andReturn(false);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn ($name): Mockery\MockInterface => $clientServiceMock);
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);

    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $data = ['email' => 'email@example.com'];

    $service = new Service();
    $service->setDi($di);

    expect(fn (): bool => $service->updateClient($model, $data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('throws exception when email already registered', function (): void {
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->never()
        ->andReturn(true);

    $modMock = Mockery::mock(FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([
            'disable_change_email' => 0,
        ]);

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail');

    $clientServiceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $clientServiceMock->shouldReceive('emailAlreadyRegistered')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn ($name): Mockery\MockInterface => $clientServiceMock);
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);
    $di['tools'] = $toolsMock;

    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $data = ['email' => 'email@example.com'];

    $service = new Service();
    $service->setDi($di);

    expect(fn (): bool => $service->updateClient($model, $data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('resets api key', function (): void {
    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['tools'] = new FOSSBilling\Tools();

    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $service = new Service();
    $service->setDi($di);
    $result = $service->resetApiKey($model);
    expect($result)->toBeString();
    expect(strlen((string) $result))->toBe(32);
});

test('changes client password', function (): void {
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $password = 'new password';

    $passwordMock = Mockery::mock(FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')
        ->with($password);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;
    $di['password'] = $passwordMock;

    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $service = new Service();
    $service->setDi($di);
    $result = $service->changeClientPassword($model, $password);
    expect($result)->toBeTrue();
});

test('logs out client', function (): void {
    $sessionMock = Mockery::mock(FOSSBilling\Session::class);
    $sessionMock->shouldReceive('destroy')
        ->atLeast()->once();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['session'] = $sessionMock;

    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $service = new Service();
    $service->setDi($di);
    $result = $service->logoutClient();
    expect($result)->toBeTrue();
});

test('i18n::validateTimezone returns null for null and empty input', function (): void {
    expect(FOSSBilling\i18n::validateTimezone(null))->toBeNull();
    expect(FOSSBilling\i18n::validateTimezone(''))->toBeNull();
});

test('i18n::validateTimezone accepts any IANA identifier', function (): void {
    expect(FOSSBilling\i18n::validateTimezone('America/New_York'))->toBe('America/New_York');
    expect(FOSSBilling\i18n::validateTimezone('Asia/Tokyo'))->toBe('Asia/Tokyo');
    expect(FOSSBilling\i18n::validateTimezone('UTC'))->toBe('UTC');
});

test('i18n::validateTimezone throws InformationException for unknown identifier', function (): void {
    expect(fn (): ?string => FOSSBilling\i18n::validateTimezone('Mars/Olympus'))->toThrow(FOSSBilling\InformationException::class);
});

// Builds a session table payload with the real encoder, keys in given order.
function encodeProfileTestSession(array $values): string
{
    $sessionWasActive = session_status() === PHP_SESSION_ACTIVE;
    if (!$sessionWasActive) {
        session_start();
    }
    $backup = $_SESSION;
    $_SESSION = [];
    foreach ($values as $key => $value) {
        $_SESSION[$key] = $value;
    }
    $raw = session_encode();
    $_SESSION = $backup;
    if (!$sessionWasActive) {
        session_abort();
    }

    return base64_encode((string) $raw);
}

function profileTestService(array $rows, array &$deletedIds): Service
{
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->once()
        ->andReturn($rows);
    $connection->shouldReceive('executeStatement')
        ->andReturnUsing(function (string $sql, array $params) use (&$deletedIds): int {
            $deletedIds[] = $params['id'];

            return 1;
        });

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')
        ->andReturn($connection);

    $di = container();
    $di['em'] = $em;

    $service = new Service();
    $service->setDi($di);

    return $service;
}

test('invalidates admin sessions regardless of where the identity key sits', function (): void {
    $csrfToken = bin2hex(random_bytes(32));
    $rows = [
        // csrf_token is written first in real sessions; the old prefix match missed these rows.
        ['id' => 'sess-a', 'content' => encodeProfileTestSession(['csrf_token' => $csrfToken, 'admin' => ['id' => 7, 'email' => 'admin@example.com', 'name' => 'Admin']])],
        ['id' => 'sess-b', 'content' => encodeProfileTestSession(['admin' => ['id' => 7, 'email' => 'admin@example.com', 'name' => 'Admin']])],
        ['id' => 'sess-c', 'content' => encodeProfileTestSession(['csrf_token' => $csrfToken, 'admin' => ['id' => 9, 'email' => 'other@example.com', 'name' => 'Other']])],
        ['id' => 'sess-d', 'content' => encodeProfileTestSession(['csrf_token' => $csrfToken, 'client_id' => 3])],
        ['id' => 'sess-e', 'content' => '!!!not-base64!!!'],
        ['id' => 'sess-f', 'content' => base64_encode('plain string, not session data')],
        ['id' => 'sess-g', 'content' => base64_encode('a|a:1:{a:0:{}i:1;}')],
    ];

    $deletedIds = [];
    $service = profileTestService($rows, $deletedIds);

    expect($service->invalidateSessions('admin', 7))->toBeTrue();
    expect($deletedIds)->toBe(['sess-a', 'sess-b']);
});

test('invalidates client sessions regardless of where the identity key sits', function (): void {
    $csrfToken = bin2hex(random_bytes(32));
    $rows = [
        ['id' => 'sess-a', 'content' => encodeProfileTestSession(['csrf_token' => $csrfToken, 'client_id' => 3])],
        ['id' => 'sess-b', 'content' => encodeProfileTestSession(['client_id' => 3])],
        ['id' => 'sess-c', 'content' => encodeProfileTestSession(['csrf_token' => $csrfToken, 'client_id' => 4])],
        ['id' => 'sess-d', 'content' => encodeProfileTestSession(['csrf_token' => $csrfToken, 'admin' => ['id' => 7, 'email' => 'admin@example.com', 'name' => 'Admin']])],
    ];

    $deletedIds = [];
    $service = profileTestService($rows, $deletedIds);

    expect($service->invalidateSessions('client', 3))->toBeTrue();
    expect($deletedIds)->toBe(['sess-a', 'sess-b']);
});
