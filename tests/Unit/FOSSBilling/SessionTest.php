<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use FOSSBilling\Http\CookieNames;
use Symfony\Component\HttpFoundation\Request;

function createSession(): FOSSBilling\Session
{
    $handler = new class extends PdoSessionHandler {
        public function __construct()
        {
        }
    };

    return new FOSSBilling\Session($handler);
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
    $sessionName = session_name();
    if ($sessionName !== false) {
        $_COOKIE[$sessionName] = $sessionId;
    }
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
    $_SESSION = [];
    foreach ([
        CookieNames::SESSION,
        'PHPSESSID',
    ] as $sessionName) {
        unset($_COOKIE[$sessionName]);
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_id('');
        session_name('PHPSESSID');
    }
});

test('session cookie name migrates and expires the previous session cookie', function (): void {
    $_COOKIE['PHPSESSID'] = 'legacy-session';
    $session = createSession();

    invokePrivate($session, 'configureCookieName');

    expect(session_name())->toBe(CookieNames::SESSION)
        ->and(session_id())->toBe('legacy-session');

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
        ->withArgs(function (string $query, array $parameters): bool {
            return $query === 'UPDATE session SET created_at = :created_at WHERE id = :id'
                && $parameters['id'] === 'new-session'
                && is_int($parameters['created_at']);
        })
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

    expect($_COOKIE)->not->toHaveKey((string) session_name());
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

    expect($_COOKIE)->not->toHaveKey((string) session_name());
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
        ->withArgs(function (string $query, array $parameters): bool {
            return $query === 'UPDATE session SET fingerprint = :fingerprint WHERE id = :id'
                && $parameters['id'] === 'current-session'
                && is_array(json_decode($parameters['fingerprint'], true));
        })
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
            ->withArgs(function (string $query, array $parameters): bool {
                return $query === 'UPDATE session SET created_at = :created_at WHERE id = :id'
                    && $parameters['id'] === 'fingerprinting-disabled'
                    && is_int($parameters['created_at']);
            })
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
    $_SESSION = [
        'admin' => ['id' => 1],
        'client' => ['id' => 2],
        'client_id' => 2,
    ];

    $result = createSession()->destroy('client');

    expect($result)->toBeTrue()
        ->and($_SESSION)->toHaveKey('admin')
        ->not->toHaveKeys(['client', 'client_id']);
});

test('destroying an admin login preserves the client login', function (): void {
    $_SESSION = [
        'admin' => ['id' => 1],
        'client' => ['id' => 2],
        'client_id' => 2,
    ];

    $result = createSession()->destroy('admin');

    expect($result)->toBeTrue()
        ->and($_SESSION)->not->toHaveKey('admin')
        ->and($_SESSION)->toHaveKeys(['client', 'client_id']);
});
