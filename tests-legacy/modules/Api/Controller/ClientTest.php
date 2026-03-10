<?php

declare(strict_types=1);

namespace Box\Mod\Api\Controller;

use FOSSBilling\InformationException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

final class TestableClient extends Client
{
    public bool $hasValidSession = false;
    public bool $hasTokenAuthCredentials = false;
    public bool $shouldFailCsrf = false;
    public array $calls = [];
    public mixed $renderedData = null;
    public ?\Exception $renderedException = null;

    public function renderJson($data = null, ?\Exception $e = null): void
    {
        $this->renderedData = $data;
        $this->renderedException = $e;
    }

    protected function isRoleLoggedIn($role): bool
    {
        if (!$this->hasValidSession) {
            throw new \Exception('Client is not logged in');
        }

        return true;
    }

    protected function _tryTokenLogin(): void
    {
        $this->calls[] = 'token';
    }

    protected function hasTokenAuthCredentials(): bool
    {
        return $this->hasTokenAuthCredentials;
    }

    public function _checkCSRFToken()
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
        $controller->hasTokenAuthCredentials = true;

        $this->invokeApiCall($controller, 'client', 'test', 'test_method', []);

        $this->assertSame(['ok' => true], $controller->renderedData);
        $this->assertNull($controller->renderedException);
        $this->assertSame(['token'], $controller->calls);
    }

    public function testTokenAuthenticatedRequestBypassesCsrfEvenWithExistingSession(): void
    {
        $controller = $this->createController();
        $controller->hasValidSession = true;
        $controller->hasTokenAuthCredentials = true;
        $controller->shouldFailCsrf = true;

        $this->invokeApiCall($controller, 'admin', 'test', 'test_method', []);

        $this->assertSame(['ok' => true], $controller->renderedData);
        $this->assertNull($controller->renderedException);
        $this->assertSame(['token'], $controller->calls);
    }

    public function testSessionAuthenticatedRequestStillRequiresCsrfToken(): void
    {
        $controller = $this->createController();
        $controller->hasValidSession = true;
        $controller->shouldFailCsrf = true;

        $this->expectException(InformationException::class);
        $this->expectExceptionCode(403);

        $this->invokeApiCall($controller, 'client', 'test', 'test_method', []);
    }

    private function createController(): TestableClient
    {
        $service = $this->createMock(\Box\Mod\Api\Service::class);
        $service->expects($this->once())
            ->method('logRequest');
        $service->expects($this->once())
            ->method('getRequestCount')
            ->willReturn(0);

        $request = $this->createMock(Request::class);
        $request->expects($this->atLeastOnce())
            ->method('getClientIp')
            ->willReturn('127.0.0.1');

        $api = new class {
            public function test_method(array $params): array
            {
                return ['ok' => true];
            }
        };

        $this->di['mod_service'] = $this->di->protect(fn (string $name) => $service);
        $this->di['request'] = $request;
        $this->di['api'] = $this->di->protect(fn (string $role) => $api);

        $controller = new TestableClient();
        $controller->setDi($this->di);

        return $controller;
    }

    private function invokeApiCall(TestableClient $controller, string $role, string $class, string $method, array $params): void
    {
        $reflection = new \ReflectionMethod(Client::class, '_apiCall');
        $reflection->setAccessible(true);
        $reflection->invoke($controller, $role, $class, $method, $params);
    }
}
