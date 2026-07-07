<?php

declare(strict_types=1);

use Box\Mod\Api\Controller\Client;
use FOSSBilling\InformationException;
use Symfony\Component\HttpFoundation\Request;

function invokeControllerPrivate(object $instance, string $method, array $args = []): mixed
{
    $reflection = new ReflectionClass($instance);
    $reflectionMethod = $reflection->getMethod($method);

    return $reflectionMethod->invokeArgs($instance, $args);
}

function createControllerDiWithRequest(): Pimple\Container
{
    $di = new Pimple\Container();
    $di['request'] = Request::create('/', 'GET', [], [], [], $_SERVER);

    return $di;
}

function buildAdminModel(int $id, string $apiToken, ?string $systemName = null): Model_Admin
{
    $admin = new Model_Admin();
    $bean = new stdClass();
    $bean->id = $id;
    $bean->api_token = $apiToken;
    $bean->system_name = $systemName;
    $bean->status = Model_Admin::STATUS_ACTIVE;

    $property = new ReflectionProperty(RedBeanPHP\SimpleModel::class, 'bean');
    $property->setValue($admin, $bean);

    return $admin;
}

readonly class CronStaffServiceDouble
{
    public function __construct(private Model_Admin $cronAdmin)
    {
    }

    public function getCronAdmin(): Model_Admin
    {
        return $this->cronAdmin;
    }
}

uses()->beforeEach(function (): void {
    $this->serverBackup = $_SERVER;
    unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
})->afterEach(function (): void {
    $_SERVER = $this->serverBackup;
});

test('register allowed route roles', function (): void {
    $controller = new Client();

    $roles = invokeControllerPrivate($controller, 'registerAllowedRouteRoles');
    expect($roles)->toBeArray();
    expect($roles)->toHaveKey('role');
    expect($roles['role'])->toBe('guest|client|admin');
});

test('is role allowed rejects system role', function (): void {
    $controller = new Client();

    expect(fn (): mixed => invokeControllerPrivate($controller, 'isRoleAllowed', ['system']))
        ->toThrow(FOSSBilling\Exception::class);
});

test('should use token login ignores non-API basic auth usernames', function (): void {
    $controller = new Client();
    $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('upstream:token');
    $controller->setDi(createControllerDiWithRequest());

    $result = invokeControllerPrivate($controller, 'shouldUseTokenLogin', ['admin']);
    expect($result)->toBeFalse();
});

test('should use token login rejects route role mismatch', function (): void {
    $controller = new Client();
    $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('client:token');
    $controller->setDi(createControllerDiWithRequest());

    expect(fn (): mixed => invokeControllerPrivate($controller, 'shouldUseTokenLogin', ['admin']))
        ->toThrow(InformationException::class);
});

test('should prefer session auth for API key management methods', function (): void {
    $controller = new Client();

    expect(invokeControllerPrivate($controller, 'shouldPreferSessionAuth', ['profile_api_key_get']))->toBeTrue();
    expect(invokeControllerPrivate($controller, 'shouldPreferSessionAuth', ['profile_api_key_reset']))->toBeTrue();
    expect(invokeControllerPrivate($controller, 'shouldPreferSessionAuth', ['profile_generate_api_key']))->toBeTrue();
    expect(invokeControllerPrivate($controller, 'shouldPreferSessionAuth', ['profile_get']))->toBeFalse();
});

test('has authenticated session returns false when session lookup throws', function (): void {
    $controller = new Client();
    $di = new Pimple\Container();
    $di['is_client_logged'] = function (): void {
        throw new Exception('Session lookup failed');
    };
    $controller->setDi($di);

    expect(invokeControllerPrivate($controller, 'hasAuthenticatedSession', ['client']))->toBeFalse();
});

test('require session auth normalizes generic auth exceptions', function (): void {
    $controller = new Client();
    $di = new Pimple\Container();
    $di['is_admin_logged'] = function (): void {
        throw new Exception('Not logged in');
    };
    $controller->setDi($di);

    expect(fn (): mixed => invokeControllerPrivate($controller, 'requireSessionAuth', ['admin']))
        ->toThrow(InformationException::class);
});

test('get auth rejects invalid basic payload', function (): void {
    $controller = new Client();
    $_SERVER['HTTP_AUTHORIZATION'] = 'Basic invalid###';
    $controller->setDi(createControllerDiWithRequest());

    expect(fn (): mixed => invokeControllerPrivate($controller, 'getAuth'))
        ->toThrow(InformationException::class);
});

test('try token login rejects cron admin token', function (): void {
    $controller = new Client();
    $cronToken = 'cron-api-token';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:' . $cronToken);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->with('Admin', 'api_token = ? AND status = ? AND (system_name IS NULL OR system_name != ?)', [$cronToken, Model_Admin::STATUS_ACTIVE, Model_Admin::SYSTEM_CRON])->once()->andReturn(null);

    $di = new Pimple\Container();
    $di['db'] = $dbMock;
    $di['request'] = Request::create('/', 'GET', [], [], [], $_SERVER);
    $controller->setDi($di);

    expect(fn (): mixed => invokeControllerPrivate($controller, '_tryTokenLogin', ['admin']))
        ->toThrow(InformationException::class);
});

test('try token login rejects cron admin by id fallback', function (): void {
    $controller = new Client();
    $cronToken = 'legacy-cron-api-token';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:' . $cronToken);

    $dbAdmin = buildAdminModel(1, $cronToken);
    $cronAdmin = buildAdminModel(1, $cronToken, Model_Admin::SYSTEM_CRON);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->with('Admin', 'api_token = ? AND status = ? AND (system_name IS NULL OR system_name != ?)', [$cronToken, Model_Admin::STATUS_ACTIVE, Model_Admin::SYSTEM_CRON])->once()->andReturn($dbAdmin);

    $di = new Pimple\Container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn ($name): ?object => $name === 'staff' ? new CronStaffServiceDouble($cronAdmin) : null);
    $di['request'] = Request::create('/', 'GET', [], [], [], $_SERVER);
    $controller->setDi($di);

    expect(fn (): mixed => invokeControllerPrivate($controller, '_tryTokenLogin', ['admin']))
        ->toThrow(InformationException::class);
});

test('try token login requires active client token', function (): void {
    $controller = new Client();
    $apiToken = 'inactive-client-token';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('client:' . $apiToken);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->with('Client', 'api_token = ? AND status = ?', [$apiToken, Model_Client::ACTIVE])->once()->andReturn(null);

    $di = new Pimple\Container();
    $di['db'] = $dbMock;
    $di['request'] = Request::create('/', 'GET', [], [], [], $_SERVER);
    $controller->setDi($di);

    expect(fn (): mixed => invokeControllerPrivate($controller, '_tryTokenLogin', ['client']))
        ->toThrow(InformationException::class);
});

test('try token login requires active non-cron admin token', function (): void {
    $controller = new Client();
    $apiToken = 'inactive-admin-token';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:' . $apiToken);

    $dbMock = Mockery::mock(Box_Database::class);
    $dbMock->shouldReceive('findOne')->with('Admin', 'api_token = ? AND status = ? AND (system_name IS NULL OR system_name != ?)', [$apiToken, Model_Admin::STATUS_ACTIVE, Model_Admin::SYSTEM_CRON])->once()->andReturn(null);

    $di = new Pimple\Container();
    $di['db'] = $dbMock;
    $di['request'] = Request::create('/', 'GET', [], [], [], $_SERVER);
    $controller->setDi($di);

    expect(fn (): mixed => invokeControllerPrivate($controller, '_tryTokenLogin', ['admin']))
        ->toThrow(InformationException::class);
});
