<?php

declare(strict_types=1);

use Box\Mod\Api\Controller\Client;
use Box\Mod\Staff\Entity\Admin;
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
    $admin = createEntity(\Box\Mod\Staff\Entity\Admin::class);
    $bean = new stdClass();
    $bean->id = $id;
    $bean->api_token = $apiToken;
    $bean->system_name = $systemName;
    $bean->status = 'active';

    $property = new ReflectionProperty(RedBeanPHP\SimpleModel::class, 'bean');
    $property->setValue($admin, $bean);

    return $admin;
}

readonly class CronStaffServiceDouble
{
    public function __construct(private Admin $cronAdmin)
    {
    }

    public function getCronAdmin(): Admin
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

    $adminRepository = Mockery::mock(\Box\Mod\Staff\Repository\AdminRepository::class)->shouldIgnoreMissing();
    $adminRepository->shouldReceive('findBy')->once()->with(['apiToken' => $cronToken, 'status' => 'active'])->andReturn([]);
    $emMock = Mockery::mock(\Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Admin::class)->andReturn($adminRepository);

    $sessionMock = Mockery::mock(FOSSBilling\Session::class)->shouldIgnoreMissing();

    $di = new Pimple\Container();
    $di['em'] = $emMock;
    $di['session'] = $sessionMock;
    $di['request'] = Request::create('/', 'GET', [], [], [], $_SERVER);
    $controller->setDi($di);

    expect(fn (): mixed => invokeControllerPrivate($controller, '_tryTokenLogin', ['admin']))
        ->toThrow(InformationException::class);
});

test('try token login rejects cron admin by id fallback', function (): void {
    $controller = new Client();
    $cronToken = 'legacy-cron-api-token';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:' . $cronToken);

    $entityAdmin = \Tests\Helpers\createEntity(Admin::class, ['id' => 1, 'apiToken' => $cronToken, 'status' => 'active']);
    $cronAdmin = \Tests\Helpers\createEntity(Admin::class, ['id' => 1, 'systemName' => 'cron', 'status' => 'active', 'apiToken' => $cronToken]);

    $adminRepository = Mockery::mock(\Box\Mod\Staff\Repository\AdminRepository::class)->shouldIgnoreMissing();
    $adminRepository->shouldReceive('findBy')->once()->with(['apiToken' => $cronToken, 'status' => 'active'])->andReturn([$entityAdmin]);
    $emMock = Mockery::mock(\Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Admin::class)->andReturn($adminRepository);

    $sessionMock = Mockery::mock(FOSSBilling\Session::class)->shouldIgnoreMissing();

    $di = new Pimple\Container();
    $di['em'] = $emMock;
    $di['session'] = $sessionMock;
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

    $clientRepository = Mockery::mock(\Box\Mod\Client\Repository\ClientRepository::class)->shouldIgnoreMissing();
    $clientRepository->shouldReceive('findOneBy')->once()->with(['apiToken' => $apiToken, 'status' => 'active'])->andReturn(null);
    $emMock = Mockery::mock(\Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(\Box\Mod\Client\Entity\Client::class)->andReturn($clientRepository);

    $sessionMock = Mockery::mock(FOSSBilling\Session::class)->shouldIgnoreMissing();

    $di = new Pimple\Container();
    $di['em'] = $emMock;
    $di['session'] = $sessionMock;
    $di['request'] = Request::create('/', 'GET', [], [], [], $_SERVER);
    $controller->setDi($di);

    expect(fn (): mixed => invokeControllerPrivate($controller, '_tryTokenLogin', ['client']))
        ->toThrow(InformationException::class);
});

test('try token login requires active non-cron admin token', function (): void {
    $controller = new Client();
    $apiToken = 'inactive-admin-token';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:' . $apiToken);

    $adminRepository = Mockery::mock(\Box\Mod\Staff\Repository\AdminRepository::class)->shouldIgnoreMissing();
    $adminRepository->shouldReceive('findBy')->once()->with(['apiToken' => $apiToken, 'status' => 'active'])->andReturn([]);
    $emMock = Mockery::mock(\Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(Admin::class)->andReturn($adminRepository);

    $sessionMock = Mockery::mock(FOSSBilling\Session::class)->shouldIgnoreMissing();

    $di = new Pimple\Container();
    $di['em'] = $emMock;
    $di['session'] = $sessionMock;
    $di['request'] = Request::create('/', 'GET', [], [], [], $_SERVER);
    $controller->setDi($di);

    expect(fn (): mixed => invokeControllerPrivate($controller, '_tryTokenLogin', ['admin']))
        ->toThrow(InformationException::class);
});
