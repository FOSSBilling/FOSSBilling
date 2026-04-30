<?php

declare(strict_types=1);

namespace Box\Mod\Api\Controller;

use FOSSBilling\InformationException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

final class TestableClient extends Client
{
    public bool $hasValidSession = false;
    public bool $shouldUseTokenLogin = false;
    public bool $shouldFailTokenLogin = false;
    public bool $shouldFailCsrf = false;
    public array $calls = [];
    public mixed $renderedData = null;
    public ?\Exception $renderedException = null;

    #[\Override]
    public function renderJson($data = null, ?\Exception $e = null): void
    {
        $this->renderedData = $data;
        $this->renderedException = $e;
    }

    #[\Override]
    protected function isRoleLoggedIn($role): bool
    {
        if (!$this->hasValidSession) {
            throw new \Exception('Client is not logged in');
        }

        return true;
    }

    #[\Override]
    protected function _tryTokenLogin(string $routeRole): void
    {
        $this->calls[] = 'token';

        if ($this->shouldFailTokenLogin) {
            throw new InformationException('Authentication Failed', null, 204);
        }
    }

    #[\Override]
    protected function shouldUseTokenLogin(string $routeRole): bool
    {
        return $this->shouldUseTokenLogin;
    }

    #[\Override]
    public function _checkCSRFToken(): bool
    {
        $this->calls[] = 'csrf';

        if ($this->shouldFailCsrf) {
            throw new InformationException('CSRF token invalid', null, 403);
        }

        return true;
    }
}

#[Group('Core')]
final class ClientTest extends \BBTestCase
{
    private ?array $serverBackup = [];
    private ?array $getBackup = [];
    private ?array $postBackup = [];
    private ?array $cookieBackup = [];
    private ?\Pimple\Container $di = null;
    private ?\ArrayObject $rateLimitCalls = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $this->cookieBackup = $_COOKIE;

        $_GET['_url'] = '/api/client/test/test_method';
        $_POST = [];
        $_COOKIE = [];
        $this->di = $this->getDi();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $_COOKIE = $this->cookieBackup;

        parent::tearDown();
    }

    public function testTokenAuthenticatedRequestBypassesCsrfCheck(): void
    {
        $controller = $this->createController();
        $controller->hasValidSession = false;
        $controller->shouldUseTokenLogin = true;

        $this->invokeApiCall($controller, 'client', 'test', 'testMethod', []);

        $this->assertSame(['ok' => true], $controller->renderedData);
        $this->assertNull($controller->renderedException);
        $this->assertSame(['token'], $controller->calls);
    }

    public function testTokenAuthenticatedRequestBypassesCsrfEvenWithExistingSession(): void
    {
        $controller = $this->createController();
        $controller->hasValidSession = true;
        $controller->shouldUseTokenLogin = true;
        $controller->shouldFailCsrf = true;

        $this->invokeApiCall($controller, 'admin', 'test', 'testMethod', []);

        $this->assertSame(['ok' => true], $controller->renderedData);
        $this->assertNull($controller->renderedException);
        $this->assertSame(['token'], $controller->calls);
    }

    public function testTokenAuthenticationFailureConsumesPreAuthRateLimit(): void
    {
        $controller = $this->createController();
        $controller->shouldUseTokenLogin = true;
        $controller->shouldFailTokenLogin = true;

        try {
            $this->invokeApiCall($controller, 'client', 'test', 'testMethod', []);
            self::fail('Expected token authentication to fail');
        } catch (InformationException $exception) {
            $this->assertSame(204, $exception->getCode());
        }

        $this->assertSame([['api_authenticated', '127.0.0.1', 1]], $this->rateLimitCalls?->getArrayCopy());
        $this->assertSame(['token'], $controller->calls);
    }

    public function testMissingSessionConsumesPreAuthRateLimit(): void
    {
        $controller = $this->createController();
        $controller->hasValidSession = false;

        try {
            $this->invokeApiCall($controller, 'client', 'test', 'testMethod', []);
            self::fail('Expected session authentication to fail');
        } catch (InformationException $exception) {
            $this->assertSame(201, $exception->getCode());
        }

        $this->assertSame([['api_authenticated', '127.0.0.1', 1]], $this->rateLimitCalls?->getArrayCopy());
        $this->assertSame([], $controller->calls);
    }

    public function testSessionAuthenticatedRequestStillRequiresCsrfToken(): void
    {
        $controller = $this->createController();
        $controller->hasValidSession = true;
        $controller->shouldFailCsrf = true;

        try {
            $this->invokeApiCall($controller, 'client', 'test', 'testMethod', []);
            self::fail('Expected CSRF authentication to fail');
        } catch (InformationException $exception) {
            $this->assertSame(403, $exception->getCode());
        }

        $this->assertSame([['api_authenticated', '127.0.0.1', 1]], $this->rateLimitCalls?->getArrayCopy());
        $this->assertSame(['csrf'], $controller->calls);
    }

    public function testGuestRequestIgnoresTokenAuthCredentials(): void
    {
        $controller = $this->createController();
        $controller->hasValidSession = true;
        $controller->shouldUseTokenLogin = true;

        $this->invokeApiCall($controller, 'guest', 'test', 'testMethod', []);

        $this->assertSame(['ok' => true], $controller->renderedData);
        $this->assertNull($controller->renderedException);
        $this->assertSame([], $controller->calls);
    }

    private function createController(): TestableClient
    {
        $request = $this->createMock(Request::class);
        $request->method('getClientIp')
            ->willReturn('127.0.0.1');

        $this->rateLimitCalls = new \ArrayObject();

        $rateLimiter = new class($this->rateLimitCalls) {
            public function __construct(private \ArrayObject $calls)
            {
            }

            public function consume(string $policy, string $subject, int $tokens = 1): \FOSSBilling\Security\RateLimitResult
            {
                $this->calls[] = [$policy, $subject, $tokens];

                return new \FOSSBilling\Security\RateLimitResult($policy, false, 100, 99);
            }

            public function consumeOrThrow(string $policy, string $subject, int $tokens = 1): \FOSSBilling\Security\RateLimitResult
            {
                return $this->consume($policy, $subject, $tokens);
            }
        };

        $api = new class {
            public function testMethod(array $params): array
            {
                return ['ok' => true];
            }
        };

        $this->di['request'] = $request;
        $this->di['rate_limiter'] = $rateLimiter;
        $this->di['session'] = new class {
            public function get(string $key): mixed
            {
                return null;
            }
        };
        $this->di['api'] = $this->di->protect(fn (string $role): object => $api);

        $controller = new TestableClient();
        $controller->setDi($this->di);

        return $controller;
    }

    private function invokeApiCall(TestableClient $controller, string $role, string $class, string $method, array $params): void
    {
        $reflection = new \ReflectionMethod(Client::class, '_apiCall');
        $reflection->invoke($controller, $role, $class, $method, $params);
    }
}
