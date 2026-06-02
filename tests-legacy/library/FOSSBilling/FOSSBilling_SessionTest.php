<?php

declare(strict_types=1);

final class SessionHandlerStub extends PdoSessionHandler
{
    public function __construct()
    {
    }
}

final class FOSSBilling_SessionTest extends BBTestCase
{
    public function testObsoleteSessionIsDetected(): void
    {
        $session = $this->createSession();

        $result = $this->invokePrivate($session, 'isObsoleteSession', [[
            'fb_session_obsolete' => true,
        ]]);

        $this->assertTrue($result);
    }

    public function testObsoleteSessionWithoutExpiryIsExpired(): void
    {
        $session = $this->createSession();

        $result = $this->invokePrivate($session, 'isObsoleteSessionExpired', [[
            'fb_session_obsolete' => true,
        ], 100]);

        $this->assertTrue($result);
    }

    public function testObsoleteSessionExpiryHonorsGraceWindow(): void
    {
        $session = $this->createSession();

        $active = $this->invokePrivate($session, 'isObsoleteSessionExpired', [[
            'fb_session_obsolete' => true,
            'fb_session_obsolete_expires_at' => 150,
        ], 100]);
        $expired = $this->invokePrivate($session, 'isObsoleteSessionExpired', [[
            'fb_session_obsolete' => true,
            'fb_session_obsolete_expires_at' => 150,
        ], 151]);

        $this->assertFalse($active);
        $this->assertTrue($expired);
    }

    private function createSession(): FOSSBilling\Session
    {
        return new FOSSBilling\Session(new SessionHandlerStub());
    }

    private function invokePrivate(object $instance, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionClass($instance);
        $methodReflection = $reflection->getMethod($method);

        return $methodReflection->invokeArgs($instance, $args);
    }
}
