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
    public function getIdentity(): Box\Mod\Client\Entity\Client
    {
        return new Box\Mod\Client\Entity\Client();
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

        public function getIdentity(): FOSSBilling\Identity\Guest
        {
            return new FOSSBilling\Identity\Guest();
        }
    };

    [$controller] = createTestController(api: $api, dispatcherResult: $response);

    invokeApiCall($controller, 'guest', 'test', 'testMethod', []);

    expect($controller->sentResponse)->toBe($response);
    expect($controller->renderedData)->toBeNull();
    expect($controller->renderedException)->toBeNull();
});

test('guest client login is throttled under the anti-brute-force api_login policy', function (): void {
    [$controller, $rateLimitCalls] = createTestController(['csrf_token' => 'browser-nonce']);
    $controller->getDi()['request'] = Request::create('/api/guest/client/login', 'POST');

    invokeApiCall($controller, 'guest', 'client', 'client_login', ['CSRFToken' => 'browser-nonce']);

    expect($controller->renderedData)->toBe(['ok' => true])
        ->and($rateLimitCalls->getArrayCopy())->toBe([['api_login', '127.0.0.1', 1]]);
});

test('admin impersonation of client login is not throttled under the guest api_login policy', function (): void {
    [$controller, $rateLimitCalls] = createTestController(['admin' => ['id' => 7]]);
    $controller->hasValidSession = true;

    invokeApiCall($controller, 'admin', 'client', 'client_login', []);

    expect($rateLimitCalls->getArrayCopy())->toBe([
        ['api_authenticated_ip', '127.0.0.1', 1],
        ['api_authenticated_account', 'admin:7', 1],
    ]);
});

class ClientTestThrowingDispatcherDouble
{
    public function __construct(private readonly Throwable $error)
    {
    }

    public function dispatch(object $identity, string $method, array $params): mixed
    {
        throw $this->error;
    }
}

class ClientTestArrayLoggerDouble
{
    public array $errors = [];

    public function error(string $message, array $context = []): void
    {
        $this->errors[] = ['message' => $message, 'context' => $context];
    }
}

function invokeTryCall(TestableClient $controller, string $role, string $class, string $call, array $params)
{
    $reflection = new ReflectionMethod(Client::class, 'tryCall');

    return $reflection->invoke($controller, $role, $class, $call, $params);
}

function createFailingController(Throwable $error, string $role = 'guest'): array
{
    [$controller] = createTestController();
    if ($role !== 'guest') {
        $controller->hasValidSession = true;
    }
    $di = $controller->getDi();
    $di['api_dispatcher'] = new ClientTestThrowingDispatcherDouble($error);
    $logger = new ClientTestArrayLoggerDouble();
    $di['logger'] = $logger;

    return [$controller, $logger];
}

test('guest internal errors return a generic message without leaking details', function (): void {
    $internal = new RuntimeException("An exception occurred while executing a query: SQLSTATE[42S22]: Column not found: 1054 Unknown column 't0.locked' in 'SELECT'");
    [$controller, $logger] = createFailingController($internal, 'guest');

    invokeTryCall($controller, 'guest', 'servicedomain', 'servicedomain_check', []);

    $rendered = $controller->renderedException;
    expect($rendered)->toBeInstanceOf(InformationException::class)
        ->and($rendered->getMessage())->toBe('An unexpected error occurred. Please try again later.')
        ->and($rendered->getMessage())->not->toContain('t0.locked')
        ->and($rendered->getMessage())->not->toContain('SQLSTATE');
    expect($logger->errors)->toHaveCount(1)
        ->and($logger->errors[0]['context']['exception_class'])->toBe(RuntimeException::class)
        ->and($logger->errors[0]['context']['message'])->toContain('t0.locked');
});

test('guest application errors keep their user-facing message', function (): void {
    [$controller, $logger] = createFailingController(new InformationException('Domain is not available.'), 'guest');

    invokeTryCall($controller, 'guest', 'servicedomain', 'servicedomain_check', []);

    expect($controller->renderedException)->toBeInstanceOf(InformationException::class)
        ->and($controller->renderedException->getMessage())->toBe('Domain is not available.');
    expect($logger->errors)->toBeEmpty();
});

test('authenticated internal errors keep their details for debugging', function (): void {
    $internal = new RuntimeException("An exception occurred while executing a query: SQLSTATE[42S22]: Column not found: 1054 Unknown column 't0.locked' in 'SELECT'");
    [$controller, $logger] = createFailingController($internal, 'admin');

    invokeTryCall($controller, 'admin', 'servicedomain', 'servicedomain_get', []);

    expect($controller->renderedException)->toBe($internal);
    expect($logger->errors)->toBeEmpty();
});

function createGuestAuthenticationController(Request $request, mixed $sessionToken = 'browser-nonce'): TestableClient
{
    [$controller] = createTestController(['csrf_token' => $sessionToken, 'client_id' => 123]);
    $controller->getDi()['request'] = $request;

    return $controller;
}

test('guest authentication rejects unsafe requests before dispatch and preserves identity', function (string $class, string $method, string $verb, array $params, array $headers, mixed $sessionToken, int $code): void {
    $request = Request::create('/api/guest/' . $class . '/' . $method, $verb, $params);
    $request->headers->add($headers);
    $controller = createGuestAuthenticationController($request, $sessionToken);
    $dispatcher = Mockery::mock();
    $dispatcher->shouldNotReceive('dispatch');
    $controller->getDi()['api_dispatcher'] = $dispatcher;
    $app = Mockery::mock(Box_App::class);
    $app->shouldReceive('getRequest')->andReturn($request);

    $verb === 'GET'
        ? $controller->get_method($app, 'guest', $class, $method)
        : $controller->post_method($app, 'guest', $class, $method);

    expect($controller->renderedException)->toBeInstanceOf(InformationException::class)
        ->and($controller->renderedException->getCode())->toBe($code)
        ->and($controller->getDi()['session']->get('client_id'))->toBe(123);
})->with(function (): iterable {
    foreach ([['client', 'login'], ['CLIENT', 'LoGiN'], ['client', 'create'], ['CLIENT', 'CrEaTe']] as [$class, $method]) {
        $credentials = ['email' => 'attacker@example.test', 'password' => 'attacker-password'];
        $valid = $credentials + ['CSRFToken' => 'browser-nonce'];
        $cases = [
            'GET' => ['GET', $valid, [], 'browser-nonce', 405],
            'missing nonce' => ['POST', $credentials, [], 'browser-nonce', 403],
            'wrong nonce' => ['POST', $credentials + ['CSRFToken' => 'attacker-nonce'], [], 'browser-nonce', 403],
            'array nonce' => ['POST', $credentials + ['CSRFToken' => ['browser-nonce']], [], 'browser-nonce', 403],
            'no session' => ['POST', $valid, [], null, 403],
            'empty nonce' => ['POST', $credentials + ['CSRFToken' => ''], [], '', 403],
            'foreign origin' => ['POST', $valid, ['Origin' => 'https://attacker.example'], 'browser-nonce', 403],
            'opaque origin' => ['POST', $valid, ['Origin' => 'null'], 'browser-nonce', 403],
            'origin prefix' => ['POST', $valid, ['Origin' => Request::create(SYSTEM_URL)->getSchemeAndHttpHost() . '.attacker.example'], 'browser-nonce', 403],
            'cross-site' => ['POST', $valid, ['Sec-Fetch-Site' => 'cross-site'], 'browser-nonce', 403],
        ];
        foreach ($cases as $label => $case) {
            yield $class . '/' . $method . ': ' . $label => [$class, $method, ...$case];
        }
    }
});

test('guest authentication accepts session-bound form JSON and header tokens', function (string $method, string $encoding, bool $originHeaders): void {
    $params = ['email' => 'client@example.test', 'password' => 'legitimate-password'];
    $headers = $originHeaders ? ['Origin' => Request::create(SYSTEM_URL)->getSchemeAndHttpHost(), 'Sec-Fetch-Site' => 'same-origin'] : [];
    if ($encoding === 'header') {
        $headers['X-CSRF-TOKEN'] = 'browser-nonce';
    } else {
        $params['CSRFToken'] = 'browser-nonce';
    }
    $request = $encoding === 'json'
        ? Request::create('/api/guest/client/' . $method, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($params, JSON_THROW_ON_ERROR))
        : Request::create('/api/guest/client/' . $method, 'POST', $params);
    $request->headers->add($headers);
    $controller = createGuestAuthenticationController($request);
    $dispatcher = Mockery::mock();
    unset($params['CSRFToken']);
    $dispatcher->shouldReceive('dispatch')->once()->with(Mockery::type('object'), 'client_' . $method, $params)->andReturn(['ok' => true]);
    $controller->getDi()['api_dispatcher'] = $dispatcher;
    $app = Mockery::mock(Box_App::class);
    $app->shouldReceive('getRequest')->andReturn($request);

    $controller->post_method($app, 'guest', 'client', $method);

    expect($controller->renderedException)->toBeNull()
        ->and($controller->renderedData)->toBe(['ok' => true]);
})->with(['login', 'create'])->with(['form', 'json', 'header'])->with([true, false]);

test('guest login cannot take its nonce or credentials from the query string', function (): void {
    $request = Request::create('/api/guest/client/login?CSRFToken=browser-nonce&email=attacker&password=attacker', 'POST');
    $controller = createGuestAuthenticationController($request);
    $dispatcher = Mockery::mock();
    $dispatcher->shouldNotReceive('dispatch');
    $controller->getDi()['api_dispatcher'] = $dispatcher;
    $app = Mockery::mock(Box_App::class);
    $app->shouldReceive('getRequest')->andReturn($request);

    $controller->post_method($app, 'guest', 'client', 'login');

    expect($controller->renderedException?->getCode())->toBe(403);
});

test('shipped client authentication forms submit the pre-login session nonce', function (string $template, int $formCount): void {
    $renderer = new Tests\Support\StrictTemplateRenderer();
    $html = $renderer->renderTemplate(PATH_MODS . '/' . $template, ['CSRFToken' => 'browser-nonce']);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $tokens = $xpath->query('//form//input[@name="CSRFToken" and @value="browser-nonce"]');

    expect($tokens->length)->toBe($formCount);
})->with([
    ['Page/templates/client/mod_page_login.html.twig', 1],
    ['Page/templates/client/mod_page_signup.html.twig', 1],
    ['Orderbutton/templates/client/mod_orderbutton_client.html.twig', 2],
    ['Embed/templates/client/mod_embed_loginform.html.twig', 1],
]);
