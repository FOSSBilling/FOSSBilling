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
