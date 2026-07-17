<?php

declare(strict_types=1);

function createSession(): FOSSBilling\Session
{
    $handler = new class extends PdoSessionHandler {
        public function __construct()
        {
        }
    };

    return new FOSSBilling\Session($handler);
}

function invokePrivate(object $instance, string $method, array $args = []): mixed
{
    $reflection = new ReflectionClass($instance);
    $methodReflection = $reflection->getMethod($method);

    return $methodReflection->invokeArgs($instance, $args);
}

afterEach(function (): void {
    $_SESSION = [];
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
