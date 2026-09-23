<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Profile\Event\AfterAdminApiKeyChangeEvent;
use Box\Mod\Profile\Event\AfterAdminProfilePasswordChangeEvent;
use Box\Mod\Profile\Event\AfterAdminProfileUpdateEvent;
use Box\Mod\Profile\Event\AfterClientProfilePasswordChangeEvent;
use Box\Mod\Profile\Event\AfterClientProfileUpdateEvent;
use Box\Mod\Profile\Event\BeforeAdminApiKeyChangeEvent;
use Box\Mod\Profile\Event\BeforeAdminProfilePasswordChangeEvent;
use Box\Mod\Profile\Event\BeforeAdminProfileUpdateEvent;
use Box\Mod\Profile\Event\BeforeClientProfilePasswordChangeEvent;
use Box\Mod\Profile\Event\BeforeClientProfileUpdateEvent;
use Box\Mod\Profile\Service;
use FOSSBilling\Events\Event;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

final class ProfileTestEventDispatcher
{
    /** @var list<Event> */
    public array $events = [];

    public function dispatch(Event $event): Event
    {
        $this->events[] = $event;

        return $event;
    }
}

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
    $eventDispatcher = new ProfileTestEventDispatcher();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['event_dispatcher'] = $eventDispatcher;

    $model = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $data = [
        'signature' => 'new signature',
        'email' => 'example@gmail.com',
        'name' => 'Admin',
        'password' => 'secret-password',
        'api_token' => 'secret-token',
    ];

    $service = new Service();
    $service->setDi($di);
    $result = $service->updateAdmin($model, $data);
    expect($result)->toBeTrue();
    expect($eventDispatcher->events)->toHaveCount(2);
    expect($eventDispatcher->events[0])->toBeInstanceOf(BeforeAdminProfileUpdateEvent::class);
    expect($eventDispatcher->events[0]->adminId)->toBe((int) $model->getId());
    expect($eventDispatcher->events[0]->data)->toBe([
        'signature' => 'new signature',
        'email' => 'example@gmail.com',
        'name' => 'Admin',
    ]);
    expect($eventDispatcher->events[1])->toBeInstanceOf(AfterAdminProfileUpdateEvent::class);
    expect($eventDispatcher->events[1]->adminId)->toBe((int) $model->getId());
});

test('generates new api key', function (): void {
    $eventDispatcher = new ProfileTestEventDispatcher();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['tools'] = new FOSSBilling\Tools();

    $model = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('invalidateSessions')
        ->once()
        ->with('admin', (int) $model->getId())
        ->andReturn(true);
    $service->setDi($di);

    $result = $service->generateNewApiKey($model);
    expect($result)->toBeTrue();
    expect($eventDispatcher->events)->toHaveCount(2);
    expect($eventDispatcher->events[0])->toBeInstanceOf(BeforeAdminApiKeyChangeEvent::class);
    expect($eventDispatcher->events[0]->adminId)->toBe((int) $model->getId());
    expect($eventDispatcher->events[1])->toBeInstanceOf(AfterAdminApiKeyChangeEvent::class);
    expect($eventDispatcher->events[1]->adminId)->toBe((int) $model->getId());
});

test('changes admin password', function (): void {
    $password = 'new_pass';
    $eventDispatcher = new ProfileTestEventDispatcher();

    $passwordMock = Mockery::mock(FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')
        ->with($password);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['password'] = $passwordMock;

    $model = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $service = new Service();
    $service->setDi($di);

    $result = $service->changeAdminPassword($model, $password);
    expect($result)->toBeTrue();
    expect($eventDispatcher->events)->toHaveCount(2);
    expect($eventDispatcher->events[0])->toBeInstanceOf(BeforeAdminProfilePasswordChangeEvent::class);
    expect($eventDispatcher->events[0]->adminId)->toBe((int) $model->getId());
    expect($eventDispatcher->events[1])->toBeInstanceOf(AfterAdminProfilePasswordChangeEvent::class);
    expect($eventDispatcher->events[1]->adminId)->toBe((int) $model->getId());
});

test('updates client', function (): void {
    $eventDispatcher = new ProfileTestEventDispatcher();

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
    $di['event_dispatcher'] = $eventDispatcher;
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
        'password' => 'secret-password',
        'api_token' => 'secret-token',
    ];

    $service = new Service();
    $service->setDi($di);
    $result = $service->updateClient($model, $data);
    expect($result)->toBeTrue();
    expect($model->getBillingEmail())->toBe('billing@example.com');
    expect($eventDispatcher->events)->toHaveCount(2);
    expect($eventDispatcher->events[0])->toBeInstanceOf(BeforeClientProfileUpdateEvent::class);
    expect($eventDispatcher->events[0]->clientId)->toBe((int) $model->getId());
    $expectedEventData = $data;
    unset($expectedEventData['password'], $expectedEventData['api_token']);
    expect($eventDispatcher->events[0]->data)->toBe($expectedEventData);
    expect($eventDispatcher->events[1])->toBeInstanceOf(AfterClientProfileUpdateEvent::class);
    expect($eventDispatcher->events[1]->clientId)->toBe((int) $model->getId());
});

test('throws exception when email change is not allowed', function (): void {
    $eventDispatcher = new ProfileTestEventDispatcher();

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
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(fn ($name): Mockery\MockInterface => $clientServiceMock);
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);

    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $data = ['email' => 'email@example.com'];

    $service = new Service();
    $service->setDi($di);

    expect(fn (): bool => $service->updateClient($model, $data))
        ->toThrow(FOSSBilling\Exception::class);
    expect($eventDispatcher->events)->toHaveCount(1);
    expect($eventDispatcher->events[0])->toBeInstanceOf(BeforeClientProfileUpdateEvent::class);
});

test('throws exception when email already registered', function (): void {
    $eventDispatcher = new ProfileTestEventDispatcher();

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
    $di['event_dispatcher'] = $eventDispatcher;
    $di['mod_service'] = $di->protect(fn ($name): Mockery\MockInterface => $clientServiceMock);
    $di['mod'] = $di->protect(fn (): Mockery\MockInterface => $modMock);
    $di['tools'] = $toolsMock;

    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $data = ['email' => 'email@example.com'];

    $service = new Service();
    $service->setDi($di);

    expect(fn (): bool => $service->updateClient($model, $data))
        ->toThrow(FOSSBilling\Exception::class);
    expect($eventDispatcher->events)->toHaveCount(1);
    expect($eventDispatcher->events[0])->toBeInstanceOf(BeforeClientProfileUpdateEvent::class);
});

test('resets api key', function (): void {
    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['tools'] = new FOSSBilling\Tools();

    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('invalidateSessions')
        ->once()
        ->with('client', (int) $model->getId())
        ->andReturn(true);
    $service->setDi($di);
    $result = $service->resetApiKey($model);
    expect($result)->toBeString();
    expect(strlen((string) $result))->toBe(32);
});

test('changes client password', function (): void {
    $eventDispatcher = new ProfileTestEventDispatcher();

    $password = 'new password';

    $passwordMock = Mockery::mock(FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')
        ->with($password);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['password'] = $passwordMock;

    $model = createEntity(Box\Mod\Client\Entity\Client::class);

    $service = new Service();
    $service->setDi($di);
    $result = $service->changeClientPassword($model, $password);
    expect($result)->toBeTrue();
    expect($eventDispatcher->events)->toHaveCount(2);
    expect($eventDispatcher->events[0])->toBeInstanceOf(BeforeClientProfilePasswordChangeEvent::class);
    expect($eventDispatcher->events[0]->clientId)->toBe((int) $model->getId());
    expect($eventDispatcher->events[1])->toBeInstanceOf(AfterClientProfilePasswordChangeEvent::class);
    expect($eventDispatcher->events[1]->clientId)->toBe((int) $model->getId());
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

test('invalidates client sessions stored in Symfony attribute format', function (): void {
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->once()
        ->with('SELECT id, content FROM session WHERE content IS NOT NULL AND OCTET_LENGTH(content) > 0')
        ->andReturn([
            ['id' => 'matching-session', 'content' => '_sf2_attributes|a:1:{s:9:"client_id";i:42;}_symfony_flashes|a:0:{}_sf2_meta|a:0:{}'],
            ['id' => 'other-session', 'content' => '_sf2_attributes|a:1:{s:9:"client_id";i:7;}_symfony_flashes|a:0:{}_sf2_meta|a:0:{}'],
            ['id' => 'malformed-session', 'content' => '_sf2_attributes|not-a-serialized-array'],
        ]);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with('DELETE FROM session WHERE id = :id', ['id' => 'matching-session'])
        ->andReturn(1);

    $entityManager = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $entityManager->shouldReceive('getConnection')->twice()->andReturn($connection);

    $di = container();
    $di['em'] = $entityManager;

    $service = new Service();
    $service->setDi($di);

    expect($service->invalidateSessions('client', 42))->toBeTrue();
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
