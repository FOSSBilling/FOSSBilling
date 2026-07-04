<?php

declare(strict_types=1);

use Box\Mod\Api\Controller\Client;
use FOSSBilling\InformationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientTestRateLimiterDouble
{
    public function __construct(private ArrayObject $calls)
    {
    }

    public function consume(string $policy, string $subject, int $tokens = 1): FOSSBilling\Security\RateLimitResult
    {
        $this->calls[] = [$policy, $subject, $tokens];

        return new FOSSBilling\Security\RateLimitResult($policy, false, 100, 99);
    }

    public function consumeOrThrow(string $policy, string $subject, int $tokens = 1): FOSSBilling\Security\RateLimitResult
    {
        return $this->consume($policy, $subject, $tokens);
    }
}

class ClientTestDefaultApiDouble
{
    public function getIdentity(): Model_Client
    {
        return new Model_Client();
    }
}

class ClientTestApiDispatcherDouble
{
    public function __construct(private readonly mixed $result = ['ok' => true])
    {
    }

    public function dispatch(object $identity, string $method, array $params): mixed
    {
        return $this->result;
    }
}

class ClientTestSessionDouble
{
    public function __construct(private array $data)
    {
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
}

class ClientTestUpdateFinalizationDouble
{
    public function isRequired(): bool
    {
        return false;
    }

    public function isAdminApiCallAllowed(string $class, string $method): bool
    {
        return true;
    }
}

class ClientTestUrlDouble
{
    public function link(string $path): string
    {
        return 'https://client.example.test/' . ltrim($path, '/');
    }

    public function adminLink(string $path): string
    {
        return 'https://admin.example.test/' . ltrim($path, '/');
    }
}

class TestableClient extends Client
{
    public bool $hasValidSession = false;
    public bool $shouldUseTokenLogin = false;
    public bool $shouldFailTokenLogin = false;
    public bool $shouldFailCsrf = false;
    public array $calls = [];
    public mixed $renderedData = null;
    public ?Exception $renderedException = null;
    public ?Response $sentResponse = null;

    #[Override]
    public function renderJson($data = null, ?Exception $e = null): Response
    {
        $this->renderedData = $data;
        $this->renderedException = $e;

        return new JsonResponse(['result' => $data, 'error' => $e?->getMessage()]);
    }

    #[Override]
    protected function sendResponse(Response $response): Response
    {
        $this->sentResponse = $response;

        return $response;
    }

    #[Override]
    protected function isRoleLoggedIn($role): bool
    {
        if (!$this->hasValidSession) {
            throw new Exception('Client is not logged in');
        }

        return true;
    }

    #[Override]
    protected function _tryTokenLogin(string $routeRole): void
    {
        $this->calls[] = 'token';

        if ($this->shouldFailTokenLogin) {
            throw new InformationException('Authentication Failed', null, 204);
        }
    }

    #[Override]
    protected function shouldUseTokenLogin(string $routeRole): bool
    {
        return $this->shouldUseTokenLogin;
    }

    #[Override]
    public function _checkCSRFToken(): bool
    {
        $this->calls[] = 'csrf';

        if ($this->shouldFailCsrf) {
            throw new InformationException('CSRF token invalid', null, 403);
        }

        return true;
    }
}

function invokeApiCall(TestableClient $controller, string $role, string $class, string $method, array $params): mixed
{
    $reflection = new ReflectionMethod(Client::class, '_apiCall');

    return $reflection->invoke($controller, $role, $class, $method, $params);
}

function createTestController(array $sessionData = [], ?object $api = null, mixed $dispatcherResult = ['ok' => true]): array
{
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('getClientIp')->andReturn('127.0.0.1');
    $request->shouldReceive('isXmlHttpRequest')->byDefault()->andReturn(false);

    $rateLimitCalls = new ArrayObject();
    $rateLimiter = new ClientTestRateLimiterDouble($rateLimitCalls);
    $api ??= new ClientTestDefaultApiDouble();

    $di = new Pimple\Container();
    $di['request'] = $request;
    $di['rate_limiter'] = $rateLimiter;
    $di['session'] = new ClientTestSessionDouble($sessionData);
    $di['update_finalization'] = new ClientTestUpdateFinalizationDouble();
    $di['api_identity'] = $di->protect(fn (string $role): object => $api);
    $di['api_dispatcher'] = new ClientTestApiDispatcherDouble($dispatcherResult);
    $di['url'] = new ClientTestUrlDouble();

    $controller = new TestableClient();
    $controller->setDi($di);

    $_GET['_url'] = '/api/client/test/test_method';
    $_POST = [];
    $_COOKIE = [];

    return [$controller, $rateLimitCalls];
}

uses()->beforeEach(function (): void {
    $this->serverBackup = $_SERVER;
    $this->getBackup = $_GET;
    $this->postBackup = $_POST;
    $this->cookieBackup = $_COOKIE;
})->afterEach(function (): void {
    $_SERVER = $this->serverBackup;
    $_GET = $this->getBackup;
    $_POST = $this->postBackup;
    $_COOKIE = $this->cookieBackup;
});

test('token authenticated request bypasses CSRF check', function (): void {
    [$controller] = createTestController();
    $controller->hasValidSession = false;
    $controller->shouldUseTokenLogin = true;

    invokeApiCall($controller, 'client', 'test', 'testMethod', []);

    expect($controller->renderedData)->toBe(['ok' => true]);
    expect($controller->renderedException)->toBeNull();
    expect($controller->calls)->toBe(['token']);
});

test('token authenticated request bypasses CSRF even with existing session', function (): void {
    [$controller] = createTestController();
    $controller->hasValidSession = true;
    $controller->shouldUseTokenLogin = true;
    $controller->shouldFailCsrf = true;

    invokeApiCall($controller, 'admin', 'test', 'testMethod', []);

    expect($controller->renderedData)->toBe(['ok' => true]);
    expect($controller->renderedException)->toBeNull();
    expect($controller->calls)->toBe(['token']);
});

test('token authentication failure consumes pre-auth rate limit', function (): void {
    [$controller, $rateLimitCalls] = createTestController();
    $controller->shouldUseTokenLogin = true;
    $controller->shouldFailTokenLogin = true;

    try {
        invokeApiCall($controller, 'client', 'test', 'testMethod', []);
        expect(true)->toBeFalse('Expected token authentication to fail');
    } catch (InformationException $e) {
        expect($e->getCode())->toBe(204);
    }

    expect($rateLimitCalls->getArrayCopy())->toBe([['api_authenticated_ip', '127.0.0.1', 1]]);
    expect($controller->calls)->toBe(['token']);
});

test('missing session consumes pre-auth rate limit', function (): void {
    [$controller, $rateLimitCalls] = createTestController();
    $controller->hasValidSession = false;

    try {
        invokeApiCall($controller, 'client', 'test', 'testMethod', []);
        expect(true)->toBeFalse('Expected session authentication to fail');
    } catch (InformationException $e) {
        expect($e->getCode())->toBe(201);
    }

    expect($rateLimitCalls->getArrayCopy())->toBe([['api_authenticated_ip', '127.0.0.1', 1]]);
    expect($controller->calls)->toBe([]);
});

test('session authenticated request still requires CSRF token', function (): void {
    [$controller, $rateLimitCalls] = createTestController();
    $controller->hasValidSession = true;
    $controller->shouldFailCsrf = true;

    try {
        invokeApiCall($controller, 'client', 'test', 'testMethod', []);
        expect(true)->toBeFalse('Expected CSRF authentication to fail');
    } catch (InformationException $e) {
        expect($e->getCode())->toBe(403);
    }

    expect($rateLimitCalls->getArrayCopy())->toBe([['api_authenticated_ip', '127.0.0.1', 1]]);
    expect($controller->calls)->toBe(['csrf']);
});

test('guest request ignores token auth credentials', function (): void {
    [$controller] = createTestController();
    $controller->hasValidSession = true;
    $controller->shouldUseTokenLogin = true;

    invokeApiCall($controller, 'guest', 'test', 'testMethod', []);

    expect($controller->renderedData)->toBe(['ok' => true]);
    expect($controller->renderedException)->toBeNull();
    expect($controller->calls)->toBe([]);
});

test('raw response bypasses JSON rendering', function (): void {
    $response = new Response('pdf-bytes', 200, ['Content-Type' => 'application/pdf']);
    $api = new readonly class($response) {
        public function __construct(private Response $response)
        {
        }

        public function getIdentity(): Model_Guest
        {
            return new Model_Guest();
        }
    };

    [$controller] = createTestController(api: $api, dispatcherResult: $response);

    invokeApiCall($controller, 'guest', 'test', 'testMethod', []);

    expect($controller->sentResponse)->toBe($response);
    expect($controller->renderedData)->toBeNull();
    expect($controller->renderedException)->toBeNull();
});

test('non-AJAX client login returns a redirect response', function (): void {
    [$controller] = createTestController();

    $response = invokeApiCall($controller, 'guest', 'client', 'login', []);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->isRedirect())->toBeTrue()
        ->and($response->headers->get('Location'))->toBe('https://client.example.test/');
});
