<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use FOSSBilling\Http\CookieNames;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

function createSession(?SymfonySession $httpSession = null): FOSSBilling\Session
{
    return new FOSSBilling\Session($httpSession ?? new SymfonySession(new MockArraySessionStorage('PHPSESSID')));
}

/**
 * @return array{FOSSBilling\Session, Pimple\Container}
 */
function createDatabaseSession(Connection $connection): array
{
    $di = Tests\Helpers\container();
    $di['dbal'] = $connection;
    $di['request'] = fn (): Request => Request::create('http://localhost/', server: [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/126.0.0.0',
        'REMOTE_ADDR' => '127.0.0.1',
    ]);

    $session = createSession();
    $session->setDi($di);

    return [$session, $di];
}

function setSessionCookie(string $sessionId): void
{
    $_COOKIE['PHPSESSID'] = $sessionId;
}

function createSessionDbalException(): RuntimeException
{
    return new class('Session database unavailable') extends RuntimeException implements Doctrine\DBAL\Exception {};
}

function invokePrivate(object $instance, string $method, array $args = []): mixed
{
    $reflection = new ReflectionClass($instance);
    $methodReflection = $reflection->getMethod($method);

    return $methodReflection->invokeArgs($instance, $args);
}

afterEach(function (): void {
    foreach ([
        CookieNames::SESSION,
        'PHPSESSID',
    ] as $sessionName) {
        unset($_COOKIE[$sessionName]);
    }
});

test('session cookie name migrates and expires the previous session cookie', function (): void {
    $_COOKIE['PHPSESSID'] = 'legacy-session';
    $httpSession = new SymfonySession(new MockArraySessionStorage('PHPSESSID'));
    $session = createSession($httpSession);

    invokePrivate($session, 'configureCookieName');

    expect($httpSession->getName())->toBe(CookieNames::SESSION)
        ->and($httpSession->getId())->toBe('legacy-session');

    invokePrivate($session, 'expireLegacySessionCookies');

    expect($_COOKIE)->not->toHaveKey('PHPSESSID');
});

test('session validation ignores a missing database record', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchAssociative')
        ->once()
        ->with('SELECT fingerprint, created_at FROM session WHERE id = :id', ['id' => 'missing-session'])
        ->andReturnFalse();

    [$session] = createDatabaseSession($connection);
    setSessionCookie('missing-session');

    invokePrivate($session, 'canUseSession');
});

test('session validation tolerates a database lookup failure', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchAssociative')
        ->once()
        ->andThrow(createSessionDbalException());

    [$session] = createDatabaseSession($connection);
    setSessionCookie('unavailable-session');

    invokePrivate($session, 'canUseSession');
});

test('session validation initializes a missing creation time', function (): void {
    $connection = Mockery::mock(Connection::class);
    [$session, $di] = createDatabaseSession($connection);
    $fingerprint = json_encode((new FOSSBilling\Fingerprint($di['request']))->fingerprint(), JSON_THROW_ON_ERROR);

    $connection->shouldReceive('fetchAssociative')
        ->once()
        ->with('SELECT fingerprint, created_at FROM session WHERE id = :id', ['id' => 'new-session'])
        ->andReturn(['fingerprint' => $fingerprint, 'created_at' => null]);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->withArgs(fn (string $query, array $parameters): bool => $query === 'UPDATE session SET created_at = :created_at WHERE id = :id'
            && $parameters['id'] === 'new-session'
            && is_int($parameters['created_at']))
        ->andReturn(1);

    setSessionCookie('new-session');

    invokePrivate($session, 'canUseSession');
});

test('session validation tolerates a creation time update failure', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchAssociative')
        ->once()
        ->andReturn(['fingerprint' => '[]', 'created_at' => null]);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->withArgs(fn (string $query): bool => str_starts_with($query, 'UPDATE session SET created_at'))
        ->andThrow(createSessionDbalException());

    [$session] = createDatabaseSession($connection);
    setSessionCookie('unavailable-session');

    invokePrivate($session, 'canUseSession');
});

test('session validation deletes an expired session', function (): void {
    $connection = Mockery::mock(Connection::class);
    [$session, $di] = createDatabaseSession($connection);
    $fingerprint = json_encode((new FOSSBilling\Fingerprint($di['request']))->fingerprint(), JSON_THROW_ON_ERROR);

    $connection->shouldReceive('fetchAssociative')
        ->once()
        ->andReturn(['fingerprint' => $fingerprint, 'created_at' => 1]);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with('DELETE FROM session WHERE id = :id', ['id' => 'expired-session'])
        ->andReturn(1);

    setSessionCookie('expired-session');

    invokePrivate($session, 'canUseSession');

    expect($_COOKIE)->not->toHaveKey('PHPSESSID');
});

test('session validation deletes a malformed fingerprint', function (): void {
    $connection = Mockery::mock(Connection::class);
    [$session] = createDatabaseSession($connection);

    $connection->shouldReceive('fetchAssociative')
        ->once()
        ->andReturn(['fingerprint' => '{malformed', 'created_at' => time()]);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with('DELETE FROM session WHERE id = :id', ['id' => 'malformed-session'])
        ->andReturn(1);

    setSessionCookie('malformed-session');

    invokePrivate($session, 'canUseSession');
});

test('session validation clears an invalid cookie when database deletion fails', function (): void {
    $connection = Mockery::mock(Connection::class);
    [$session] = createDatabaseSession($connection);

    $connection->shouldReceive('fetchAssociative')
        ->once()
        ->andReturn(['fingerprint' => '{malformed', 'created_at' => time()]);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with('DELETE FROM session WHERE id = :id', ['id' => 'unavailable-session'])
        ->andThrow(createSessionDbalException());

    setSessionCookie('unavailable-session');

    invokePrivate($session, 'canUseSession');

    expect($_COOKIE)->not->toHaveKey('PHPSESSID');
});

test('fingerprint update persists the current fingerprint', function (): void {
    $connection = Mockery::mock(Connection::class);
    [$session] = createDatabaseSession($connection);

    $connection->shouldReceive('fetchAssociative')
        ->once()
        ->with('SELECT id FROM session WHERE id = :id', ['id' => 'current-session'])
        ->andReturn(['id' => 'current-session']);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->withArgs(fn (string $query, array $parameters): bool => $query === 'UPDATE session SET fingerprint = :fingerprint WHERE id = :id'
            && $parameters['id'] === 'current-session'
            && is_array(json_decode($parameters['fingerprint'], true)))
        ->andReturn(1);

    setSessionCookie('current-session');

    invokePrivate($session, 'updateFingerprint');
});

test('fingerprint update ignores a missing database record', function (): void {
    $connection = Mockery::mock(Connection::class);
    [$session] = createDatabaseSession($connection);

    $connection->shouldReceive('fetchAssociative')
        ->once()
        ->with('SELECT id FROM session WHERE id = :id', ['id' => 'missing-session'])
        ->andReturnFalse();

    setSessionCookie('missing-session');

    invokePrivate($session, 'updateFingerprint');
});

test('fingerprint update tolerates a database failure', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchAssociative')
        ->once()
        ->andThrow(createSessionDbalException());

    [$session] = createDatabaseSession($connection);
    setSessionCookie('unavailable-session');

    invokePrivate($session, 'updateFingerprint');
});

test('disabled fingerprinting skips validation and stores an empty fingerprint', function (): void {
    $previousSetting = FOSSBilling\Config::getProperty('security.perform_session_fingerprinting', true);
    FOSSBilling\Config::setProperty('security.perform_session_fingerprinting', false, false);

    try {
        $connection = Mockery::mock(Connection::class);
        [$session] = createDatabaseSession($connection);

        $connection->shouldReceive('fetchAssociative')
            ->once()
            ->with('SELECT fingerprint, created_at FROM session WHERE id = :id', ['id' => 'fingerprinting-disabled'])
            ->andReturn(['fingerprint' => '{not-valid-json', 'created_at' => null]);
        $connection->shouldReceive('executeStatement')
            ->once()
            ->withArgs(fn (string $query, array $parameters): bool => $query === 'UPDATE session SET created_at = :created_at WHERE id = :id'
                && $parameters['id'] === 'fingerprinting-disabled'
                && is_int($parameters['created_at']))
            ->andReturn(1);
        $connection->shouldReceive('fetchAssociative')
            ->once()
            ->with('SELECT id FROM session WHERE id = :id', ['id' => 'fingerprinting-disabled'])
            ->andReturn(['id' => 'fingerprinting-disabled']);
        $connection->shouldReceive('executeStatement')
            ->once()
            ->with('UPDATE session SET fingerprint = :fingerprint WHERE id = :id', [
                'fingerprint' => '[]',
                'id' => 'fingerprinting-disabled',
            ])
            ->andReturn(1);

        setSessionCookie('fingerprinting-disabled');

        invokePrivate($session, 'canUseSession');
        invokePrivate($session, 'updateFingerprint');
    } finally {
        FOSSBilling\Config::setProperty('security.perform_session_fingerprinting', $previousSetting, false);
    }
});

test('obsolete session is detected', function (): void {
    $session = createSession();

    $result = invokePrivate($session, 'isObsoleteSession', [[
        'fb_session_obsolete' => true,
    ]]);

    expect($result)->toBeTrue();
});

test('obsolete session without expiry is expired', function (): void {
    $session = createSession();

    $result = invokePrivate($session, 'isObsoleteSessionExpired', [[
        'fb_session_obsolete' => true,
    ], 100]);

    expect($result)->toBeTrue();
});

test('obsolete session expiry honors grace window', function (): void {
    $session = createSession();

    $active = invokePrivate($session, 'isObsoleteSessionExpired', [[
        'fb_session_obsolete' => true,
        'fb_session_obsolete_expires_at' => 150,
    ], 100]);

    $expired = invokePrivate($session, 'isObsoleteSessionExpired', [[
        'fb_session_obsolete' => true,
        'fb_session_obsolete_expires_at' => 150,
    ], 151]);

    expect($active)->toBeFalse();
    expect($expired)->toBeTrue();
});

test('destroying a client login preserves the admin login', function (): void {
    $session = createSession();
    $session->set('admin', ['id' => 1]);
    $session->set('client', ['id' => 2]);
    $session->set('client_id', 2);

    $result = $session->destroy('client');

    expect($result)->toBeTrue()
        ->and($session->get('admin'))->toBe(['id' => 1])
        ->and($session->get('client'))->toBeNull()
        ->and($session->get('client_id'))->toBeNull();
});

test('destroying an admin login preserves the client login', function (): void {
    $session = createSession();
    $session->set('admin', ['id' => 1]);
    $session->set('client', ['id' => 2]);
    $session->set('client_id', 2);

    $result = $session->destroy('admin');

    expect($result)->toBeTrue()
        ->and($session->get('admin'))->toBeNull()
        ->and($session->get('client'))->toBe(['id' => 2])
        ->and($session->get('client_id'))->toBe(2);
});

test('destroying a client login regenerates the session with the configured grace period, not zero', function (): void {
    $httpSession = new SymfonySession(new MockArraySessionStorage('PHPSESSID'));

    $session = Mockery::mock(FOSSBilling\Session::class, [$httpSession])->makePartial();
    $session->shouldReceive('regenerateId')->withNoArgs()->once();

    $session->set('client', ['id' => 2]);
    $session->set('client_id', 2);

    $session->destroy('client');
});

test('destroying an admin login regenerates the session with the configured grace period, not zero', function (): void {
    $httpSession = new SymfonySession(new MockArraySessionStorage('PHPSESSID'));

    $session = Mockery::mock(FOSSBilling\Session::class, [$httpSession])->makePartial();
    $session->shouldReceive('regenerateId')->withNoArgs()->once();

    $session->set('admin', ['id' => 1]);

    $session->destroy('admin');
});
