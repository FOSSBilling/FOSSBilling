<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\System\Service;

use function Tests\Helpers\container;

test('getParamValue throws exception when key parameter is missing', function (): void {
    $service = new Service();
    $param = '';
    $this->expectException(FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Parameter key is missing');

    $service->getParamValue($param);
});

test('getParamValue returns the stored value', function (): void {
    $service = new Service();
    $setting = Tests\Helpers\createEntity(Box\Mod\System\Entity\Setting::class, ['param' => 'company_name', 'value' => 'Inc. Test']);

    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findOneByParam')->once()->with('company_name')->andReturn($setting);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $service->setDi($di);

    expect($service->getParamValue('company_name'))->toBe('Inc. Test');
});

test('getParamValue returns the default when the parameter is missing', function (): void {
    $service = new Service();

    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findOneByParam')->once()->with('missing')->andReturn(null);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $service->setDi($di);

    expect($service->getParamValue('missing', 'fallback'))->toBe('fallback');
});

test('setParamValue updates an existing setting', function (): void {
    $service = new Service();
    $timestamp = time();
    $setting = Tests\Helpers\createEntity(Box\Mod\System\Entity\Setting::class, ['param' => 'last_cron_exec', 'value' => 'old']);

    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findOneByParam')->once()->with('last_cron_exec')->andReturn($setting);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $di['em']->shouldReceive('flush')->once();
    $service->setDi($di);

    expect($service->setParamValue('last_cron_exec', $timestamp))->toBeTrue();
    expect($setting->getValue())->toBe((string) $timestamp);
});

test('setParamValue persists a new setting when missing', function (): void {
    $service = new Service();

    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findOneByParam')->once()->with('new_param')->andReturn(null);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $di['em']->shouldReceive('persist')->once();
    $di['em']->shouldReceive('flush')->once();
    $service->setDi($di);

    expect($service->setParamValue('new_param', 'value'))->toBeTrue();
});

test('setParamValue does not create a setting when createIfNotExists is false', function (): void {
    $service = new Service();

    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findOneByParam')->once()->with('new_param')->andReturn(null);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $di['em']->shouldReceive('persist')->never();
    $service->setDi($di);

    expect($service->setParamValue('new_param', 'value', false))->toBeTrue();
});

test('setParamValue propagates a concurrent duplicate insert', function (): void {
    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findOneByParam')->once()->with('new_param')->andReturn(null);

    $driverException = new class extends Exception implements Doctrine\DBAL\Driver\Exception {
        public function getSQLState(): ?string
        {
            return '23000';
        }
    };
    $duplicateKeyException = new Doctrine\DBAL\Exception\UniqueConstraintViolationException($driverException, null);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $di['em']->shouldReceive('persist')->once();
    $di['em']->shouldReceive('flush')->once()->andThrow($duplicateKeyException);
    $service = new Service();
    $service->setDi($di);

    expect(fn (): bool => $service->setParamValue('new_param', 'ours'))->toThrow($duplicateKeyException);
});

test('setParamValue skips the update when the staff member lacks permission', function (): void {
    $service = new Service();

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffServiceMock->shouldReceive('hasPermission')->once()->with(null, 'system', 'manage_company_details')->andReturn(false);

    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findOneByParam')->never();

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $di['mod_service'] = $di->protect(fn (): object => $staffServiceMock);
    $service->setDi($di);

    expect($service->setParamValue('company_name', 'Inc.'))->toBeTrue();
});

test('paramExists checks for a stored setting', function (): void {
    $service = new Service();

    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findOneByParam')->with('existing')->andReturn(Tests\Helpers\createEntity(Box\Mod\System\Entity\Setting::class, ['param' => 'existing', 'value' => 'x']));
    $settingRepository->shouldReceive('findOneByParam')->with('missing')->andReturn(null);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $service->setDi($di);

    expect($service->paramExists('existing'))->toBeTrue();
    expect($service->paramExists('missing'))->toBeFalse();
});

test('getCompany returns company information', function (): void {
    $service = new Service();
    $expected = [
        'www' => SYSTEM_URL,
        'name' => 'Inc. Test',
        'email' => 'work@example.eu',
        'tel' => null,
        'signature' => null,
        'logo_url' => null,
        'logo_url_dark' => null,
        'favicon_url' => null,
        'address_1' => null,
        'address_2' => null,
        'address_3' => null,
        'account_number' => null,
        'bank_name' => null,
        'bic' => null,
        'display_bank_info' => null,
        'bank_info_pagebottom' => null,
        'number' => null,
        'note' => null,
        'privacy_policy' => null,
        'tos' => null,
        'vat_number' => null,
    ];

    $settings = [
        Tests\Helpers\createEntity(Box\Mod\System\Entity\Setting::class, ['param' => 'company_name', 'value' => 'Inc. Test']),
        Tests\Helpers\createEntity(Box\Mod\System\Entity\Setting::class, ['param' => 'company_email', 'value' => 'work@example.eu']),
    ];
    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findByParams')->once()
        ->with(Mockery::on(static fn (array $params): bool => in_array('company_name', $params, true) && in_array('company_email', $params, true)))
        ->andReturn($settings);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $service->setDi($di);

    $result = $service->getCompany();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('getParams returns system parameters', function (): void {
    $service = new Service();
    $expected = [
        'company_name' => 'Inc. Test',
        'company_email' => 'work@example.eu',
    ];
    $settings = [
        Tests\Helpers\createEntity(Box\Mod\System\Entity\Setting::class, ['param' => 'company_name', 'value' => 'Inc. Test']),
        Tests\Helpers\createEntity(Box\Mod\System\Entity\Setting::class, ['param' => 'company_email', 'value' => 'work@example.eu']),
    ];
    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findAll')->once()->andReturn($settings);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $service->setDi($di);

    $result = $service->getParams([]);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('getNameservers returns setting pairs', function (): void {
    $service = new Service();
    $expected = [
        'nameserver_1' => 'ns1.example.test',
        'nameserver_2' => 'ns2.example.test',
    ];
    $settings = [
        Tests\Helpers\createEntity(Box\Mod\System\Entity\Setting::class, ['param' => 'nameserver_1', 'value' => 'ns1.example.test']),
        Tests\Helpers\createEntity(Box\Mod\System\Entity\Setting::class, ['param' => 'nameserver_2', 'value' => 'ns2.example.test']),
    ];
    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findByParams')->once()
        ->with(['nameserver_1', 'nameserver_2', 'nameserver_3', 'nameserver_4'])
        ->andReturn($settings);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $service->setDi($di);

    expect($service->getNameservers())->toBe($expected);
});

test('getPublicParamValue returns the value of a public setting', function (): void {
    $service = new Service();
    $setting = Tests\Helpers\createEntity(Box\Mod\System\Entity\Setting::class, ['param' => 'company_name', 'value' => 'Inc. Test', 'public' => true]);

    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findOnePublicByParam')->once()->with('company_name')->andReturn($setting);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $service->setDi($di);

    expect($service->getPublicParamValue('company_name'))->toBe('Inc. Test');
});

test('getPublicParamValue throws when the parameter is missing or not public', function (): void {
    $service = new Service();

    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findOnePublicByParam')->once()->with('company_name')->andReturn(null);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $service->setDi($di);

    $this->expectException(FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Parameter company_name does not exist');
    $service->getPublicParamValue('company_name');
});

test('updateParams updates system parameters in a single flush', function (): void {
    $service = new Service();
    $data = [
        'company_name' => 'Inc. Test',
        'company_email' => 'work@example.eu',
    ];

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $logStub = $this->createStub(FOSSBilling\Logger::class);

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffServiceMock->shouldReceive('hasPermission')->andReturn(true);

    $companyName = Tests\Helpers\createEntity(Box\Mod\System\Entity\Setting::class, ['param' => 'company_name', 'value' => 'Old Name']);
    $settingRepository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $settingRepository->shouldReceive('findOneByParam')->with('company_name')->andReturn($companyName);
    $settingRepository->shouldReceive('findOneByParam')->with('company_email')->andReturn(null);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['logger'] = $logStub;
    $di['mod_service'] = $di->protect(fn (): object => $staffServiceMock);
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\System\Entity\Setting::class)->andReturn($settingRepository);
    $di['em']->shouldReceive('persist')->once();
    $di['em']->shouldReceive('flush')->once();
    $service->setDi($di);

    $result = $service->updateParams($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
    expect($companyName->getValue())->toBe('Inc. Test');
});

test('getMessages returns system messages', function (): void {
    $service = new Service();
    $latestVersion = '1.0.0';
    $type = 'info';

    $filesystemMock = Mockery::mock(Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->allows()->exists(Mockery::any())->andReturn(false);
    $systemServiceMock = Mockery::mock(new Service($filesystemMock))->makePartial();
    $systemServiceMock->allows()->getParamValue(Mockery::any())->andReturn(false);

    $updaterMock = Mockery::mock(FOSSBilling\Update::class);
    $updaterMock->allows()->isUpdateAvailable()->andReturn(true);
    $updaterMock->allows()->getLatestVersion()->andReturn($latestVersion);
    $updaterMock->allows()->isBehindOnDBPatches()->andReturn(false);

    $urlMock = Mockery::mock(FOSSBilling\Url::class);
    $urlMock->allows()->adminLink(Mockery::any())->andReturn('http://example.com');

    $di = container();
    $di['updater'] = $updaterMock;
    $di['url'] = $urlMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemServiceMock);

    $systemServiceMock->setDi($di);

    $result = $systemServiceMock->getMessages($type);
    expect($result)->toBeArray();
});

test('templateExists returns false when paths are empty', function (): void {
    $service = new Service();
    $getThemeResults = ['paths' => []];
    $themeServiceMock = Mockery::mock(Box\Mod\Theme\Service::class)->makePartial();
    $themeServiceMock->shouldReceive('getThemeConfig')->atLeast()->once()
        ->andReturn($getThemeResults);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $themeServiceMock);
    $service->setDi($di);

    $result = $service->templateExists('defaultFile.cp');
    expect($result)->toBeBool();
    expect($result)->toBeFalse();
});

test('clearCache clears cache directory', function (): void {
    $service = new Service();
    // Use a temporary directory for testing instead of PATH_CACHE
    $cacheDir = sys_get_temp_dir() . '/fossbilling_test_cache_' . uniqid();

    // Create cache directory with .gitkeep
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0o755, true);
    }

    $gitkeepFile = $cacheDir . '/.gitkeep';
    file_put_contents($gitkeepFile, '');

    // Call clearCache with the temp directory
    $result = $service->clearCache($cacheDir);

    // Restore .gitkeep file after clearCache removes it
    file_put_contents($gitkeepFile, '');

    expect($result)->toBeBool();
    expect($result)->toBeTrue();

    // Cleanup temp directory
    if (is_dir($cacheDir)) {
        // Remove .gitkeep file first, then the directory
        if (file_exists($gitkeepFile)) {
            unlink($gitkeepFile);
        }
        rmdir($cacheDir);
    }
});

test('getPeriod returns period description', function (): void {
    $service = new Service();
    $code = '1W';
    $expected = 'Every Week';
    $result = $service->getPeriod($code);

    expect($result)->toBeString();
    expect($result)->toBe($expected);
});

test('getPendingMessages returns pending messages from session', function (): void {
    $service = new Service();
    $di = container();

    $sessionMock = Mockery::mock(FOSSBilling\Session::class);
    $sessionMock->shouldReceive('get')->atLeast()->once()
        ->with('pending_messages')
        ->andReturn([]);

    $di['session'] = $sessionMock;

    $service->setDi($di);
    $result = $service->getPendingMessages();
    expect($result)->toBeArray();
});

test('getPendingMessages returns empty array when session returns non-array', function (): void {
    $service = new Service();
    $di = container();

    $sessionMock = Mockery::mock(FOSSBilling\Session::class);
    $sessionMock->shouldReceive('get')->atLeast()->once()
        ->with('pending_messages')
        ->andReturn(null);

    $di['session'] = $sessionMock;

    $service->setDi($di);
    $result = $service->getPendingMessages();
    expect($result)->toBeArray();
});

test('setPendingMessage adds message to pending messages', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getPendingMessages')->atLeast()->once()
        ->andReturn([]);

    $di = container();

    $sessionMock = Mockery::mock(FOSSBilling\Session::class);
    $sessionMock->shouldReceive('set')->atLeast()->once()
        ->with('pending_messages', Mockery::any());

    $di['session'] = $sessionMock;

    $serviceMock->setDi($di);

    $message = 'Important Message';
    $result = $serviceMock->setPendingMessage($message);
    expect($result)->toBeTrue();
});

test('clearPendingMessages clears pending messages', function (): void {
    $service = new Service();
    $di = container();

    $sessionMock = Mockery::mock(FOSSBilling\Session::class);
    $sessionMock->shouldReceive('delete')->atLeast()->once()
        ->with('pending_messages');
    $di['session'] = $sessionMock;
    $service->setDi($di);
    $result = $service->clearPendingMessages();
    expect($result)->toBeTrue();
});

test('reserveNextNumericParamValue claims the current value and advances the counter under lock', function (): void {
    $service = new Service();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $dbalMock->shouldReceive('transactional')
        ->once()
        ->andReturnUsing(fn (callable $callback): mixed => $callback($dbalMock));
    // The read must take the row lock, otherwise two callers can be handed the same number.
    $dbalMock->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT value FROM setting WHERE param = :param FOR UPDATE', ['param' => 'invoice_starting_number'])
        ->andReturn('7');
    $dbalMock->shouldReceive('executeStatement')
        ->once()
        ->with(
            'UPDATE setting SET value = :value, updated_at = :updated_at WHERE param = :param',
            Mockery::on(fn (array $params): bool => $params['value'] === '8' && $params['param'] === 'invoice_starting_number')
        )
        ->andReturn(1);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    expect($service->reserveNextNumericParamValue('invoice_starting_number'))->toBe(7);
});

test('reserveNextNumericParamValue returns null when the counter is missing or not numeric', function (): void {
    $service = new Service();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $dbalMock->shouldReceive('transactional')
        ->once()
        ->andReturnUsing(fn (callable $callback): mixed => $callback($dbalMock));
    $dbalMock->shouldReceive('fetchOne')->once()->andReturn(false);
    $dbalMock->shouldNotReceive('executeStatement');

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    expect($service->reserveNextNumericParamValue('invoice_starting_number'))->toBeNull();
});

test('reserveNextNumericParamValue rejects non-integer counter values', function (): void {
    $service = new Service();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $dbalMock->shouldReceive('transactional')
        ->once()
        ->andReturnUsing(fn (callable $callback): mixed => $callback($dbalMock));
    // Truncating this to 5 and writing 6 would silently skip a number.
    $dbalMock->shouldReceive('fetchOne')->once()->andReturn('5.5');
    $dbalMock->shouldNotReceive('executeStatement');

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    expect($service->reserveNextNumericParamValue('invoice_starting_number'))->toBeNull();
});

test('reserveNextNumericParamValue seeds a missing counter and reserves from it', function (): void {
    $service = new Service();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $dbalMock->shouldReceive('transactional')
        ->once()
        ->andReturnUsing(fn (callable $callback): mixed => $callback($dbalMock));
    $dbalMock->shouldReceive('fetchOne')->once()->andReturn(false);
    $dbalMock->shouldReceive('executeStatement')
        ->once()
        ->with(
            Mockery::pattern('/^INSERT INTO setting/'),
            Mockery::on(fn (array $params): bool => $params['value'] === '102' && $params['param'] === 'invoice_starting_number')
        )
        ->andReturn(1);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    expect($service->reserveNextNumericParamValue('invoice_starting_number', 101))->toBe(101);
});

test('reserveNextNumericParamValue ignores the seed when the counter became valid under the lock', function (): void {
    $service = new Service();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $dbalMock->shouldReceive('transactional')
        ->once()
        ->andReturnUsing(fn (callable $callback): mixed => $callback($dbalMock));
    // A concurrent caller seeded the counter while we waited for the lock.
    $dbalMock->shouldReceive('fetchOne')->once()->andReturn('102');
    $dbalMock->shouldReceive('executeStatement')
        ->once()
        ->with(
            Mockery::pattern('/^UPDATE setting/'),
            Mockery::on(fn (array $params): bool => $params['value'] === '103')
        )
        ->andReturn(1);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    expect($service->reserveNextNumericParamValue('invoice_starting_number', 101))->toBe(102);
});

test('reserveNextNumericParamValue reserves from the winning row when seeding collides', function (): void {
    $service = new Service();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $dbalMock->shouldReceive('transactional')
        ->twice()
        ->andReturnUsing(fn (callable $callback): mixed => $callback($dbalMock));

    // First attempt: the row is missing, so we seed it, but another caller inserts it first.
    // Second attempt: that caller's row is now visible, so reserve from it rather than failing.
    $dbalMock->shouldReceive('fetchOne')->once()->ordered()->andReturn(false);
    $dbalMock->shouldReceive('executeStatement')
        ->once()
        ->ordered()
        ->with(Mockery::pattern('/^INSERT INTO setting/'), Mockery::any())
        ->andThrow(Mockery::mock(Doctrine\DBAL\Exception\UniqueConstraintViolationException::class));
    // The winner seeded the row with 102, having itself reserved 101.
    $dbalMock->shouldReceive('fetchOne')->once()->ordered()->andReturn('102');
    $dbalMock->shouldReceive('executeStatement')
        ->once()
        ->ordered()
        ->with(
            Mockery::pattern('/^UPDATE setting/'),
            Mockery::on(fn (array $params): bool => $params['value'] === '103')
        )
        ->andReturn(1);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    // Must not be 101: that is the number the winner reserved.
    expect($service->reserveNextNumericParamValue('invoice_starting_number', 101))->toBe(102);
});

test('reserveNextNumericParamValue issues a lock-escalating no-op UPDATE before the read on SQLite', function (): void {
    $service = new Service();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\SQLitePlatform::class));
    $dbalMock->shouldReceive('transactional')
        ->once()
        ->andReturnUsing(fn (callable $callback): mixed => $callback($dbalMock));

    // SQLite has no ' FOR UPDATE' clause, and a plain deferred transaction - whether opened at
    // the top level or nested via SAVEPOINT - takes no lock at all until the first write. This
    // no-op UPDATE runs before the read purely to force SQLite's write lock (RESERVED) upfront,
    // uniformly regardless of nesting depth - matching what FOR UPDATE achieves elsewhere.
    $dbalMock->shouldReceive('executeStatement')
        ->once()
        ->ordered()
        ->with('UPDATE setting SET updated_at = updated_at WHERE param = :param', ['param' => 'invoice_starting_number']);
    // No ' FOR UPDATE' suffix: RowLock::suffix() is a no-op on SQLite, the write above is what
    // actually serializes this read against other writers.
    $dbalMock->shouldReceive('fetchOne')
        ->once()
        ->ordered()
        ->with('SELECT value FROM setting WHERE param = :param', ['param' => 'invoice_starting_number'])
        ->andReturn('7');
    $dbalMock->shouldReceive('executeStatement')
        ->once()
        ->ordered()
        ->with(
            'UPDATE setting SET value = :value, updated_at = :updated_at WHERE param = :param',
            Mockery::on(fn (array $params): bool => $params['value'] === '8' && $params['param'] === 'invoice_starting_number')
        )
        ->andReturn(1);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    expect($service->reserveNextNumericParamValue('invoice_starting_number'))->toBe(7);
});

test('reserveNextNumericParamValue propagates errors from the guarded work on SQLite', function (): void {
    $service = new Service();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\SQLitePlatform::class));
    // transactional() rolls back and rethrows on its own when the closure throws - there is
    // nothing left for reserveNumericParamValue() to catch or roll back itself.
    $dbalMock->shouldReceive('transactional')
        ->once()
        ->andReturnUsing(fn (callable $callback): mixed => $callback($dbalMock));

    $dbalMock->shouldReceive('executeStatement')->once()->ordered()
        ->with('UPDATE setting SET updated_at = updated_at WHERE param = :param', Mockery::any());
    $dbalMock->shouldReceive('fetchOne')->once()->ordered()->andThrow(new RuntimeException('boom'));

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    expect(fn () => $service->reserveNextNumericParamValue('invoice_starting_number'))
        ->toThrow(RuntimeException::class, 'boom');
});

test('reserveNextNumericParamValue retries once when SQLite reports the write lock is already held', function (): void {
    $service = new Service();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\SQLitePlatform::class));

    // A concurrent writer holds the RESERVED lock, so the first attempt's lock-escalating write
    // fails outright - Doctrine's SQLite ExceptionConverter maps "database is locked" to
    // LockWaitTimeoutException. transactional() rolls back and rethrows, so the retry starts a
    // brand new transaction and its own lock-escalating write.
    $dbalMock->shouldReceive('transactional')
        ->twice()
        ->andReturnUsing(fn (callable $callback): mixed => $callback($dbalMock));

    $dbalMock->shouldReceive('executeStatement')
        ->once()
        ->ordered()
        ->with('UPDATE setting SET updated_at = updated_at WHERE param = :param', Mockery::any())
        ->andThrow(Mockery::mock(Doctrine\DBAL\Exception\LockWaitTimeoutException::class));
    $dbalMock->shouldReceive('executeStatement')->once()->ordered()
        ->with('UPDATE setting SET updated_at = updated_at WHERE param = :param', Mockery::any());
    $dbalMock->shouldReceive('fetchOne')->once()->ordered()->andReturn('7');
    $dbalMock->shouldReceive('executeStatement')->once()->ordered()->with(
        'UPDATE setting SET value = :value, updated_at = :updated_at WHERE param = :param',
        Mockery::any()
    );

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    expect($service->reserveNextNumericParamValue('invoice_starting_number'))->toBe(7);
});
