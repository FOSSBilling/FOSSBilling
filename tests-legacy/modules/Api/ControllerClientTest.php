<?php

declare(strict_types=1);

namespace Box\Mod\Api;

use Box\Mod\Api\Controller\Client;
use FOSSBilling\InformationException;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ControllerClientTest extends \BBTestCase
{
    private ?array $serverBackup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }

    public function testRegisterAllowedRouteRoles(): void
    {
        $controller = new Client();

        $roles = $this->invokePrivate($controller, 'registerAllowedRouteRoles');
        $this->assertIsArray($roles);
        $this->assertArrayHasKey('role', $roles);
        $this->assertSame('guest|client|admin', $roles['role']);
    }

    public function testIsRoleAllowedRejectsSystemRole(): void
    {
        $controller = new Client();

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(701);
        $this->invokePrivate($controller, 'isRoleAllowed', ['system']);
    }

    public function testShouldUseTokenLoginIgnoresNonApiBasicAuthUsernames(): void
    {
        $controller = new Client();

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('upstream:token');
        $result = $this->invokePrivate($controller, 'shouldUseTokenLogin', ['admin']);

        $this->assertFalse($result);
    }

    public function testShouldUseTokenLoginRejectsRouteRoleMismatch(): void
    {
        $controller = new Client();

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('client:token');

        $this->expectException(InformationException::class);
        $this->expectExceptionCode(203);
        $this->invokePrivate($controller, 'shouldUseTokenLogin', ['admin']);
    }

    public function testRequireSessionAuthNormalizesGenericAuthExceptions(): void
    {
        $controller = new Client();
        $di = $this->getDi();
        $di['is_admin_logged'] = function (): void {
            throw new \Exception('Not logged in');
        };

        $controller->setDi($di);

        $this->expectException(InformationException::class);
        $this->expectExceptionCode(201);
        $this->invokePrivate($controller, 'requireSessionAuth', ['admin']);
    }

    public function testGetAuthRejectsInvalidBasicPayload(): void
    {
        $controller = new Client();
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic invalid###';

        $this->expectException(InformationException::class);
        $this->expectExceptionCode(201);
        $this->invokePrivate($controller, 'getAuth');
    }

    public function testTryTokenLoginRejectsCronAdminToken(): void
    {
        $controller = new Client();
        $cronToken = 'cron-api-token';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:' . $cronToken);

        $cronAdmin = $this->buildAdminModel(1, $cronToken, \Model_Admin::ROLE_CRON);

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->once())
            ->method('findOne')
            ->willReturn($cronAdmin);

        $staffService = new readonly class($cronAdmin) {
            public function __construct(private \Model_Admin $cronAdmin)
            {
            }

            public function getCronAdmin(): \Model_Admin
            {
                return $this->cronAdmin;
            }
        };

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): ?object => $name === 'staff' ? $staffService : null);
        $controller->setDi($di);

        $this->expectException(InformationException::class);
        $this->expectExceptionCode(205);
        $this->invokePrivate($controller, '_tryTokenLogin', ['admin']);
    }

    public function testTryTokenLoginClientQueriesOnlyActiveClients(): void
    {
        $controller = new Client();
        $token = 'client-api-token';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('client:' . $token);

        $client = $this->buildClientModel(11, $token);

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->once())
            ->method('findOne')
            ->with('Client', 'api_token = ? AND status = ?', [$token, \Model_Client::ACTIVE])
            ->willReturn($client);

        $sessionMock = $this->createMock(\FOSSBilling\Session::class);
        $sessionMock->expects($this->once())
            ->method('set')
            ->with('client_id', 11);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['session'] = $sessionMock;
        $controller->setDi($di);

        $this->invokePrivate($controller, '_tryTokenLogin', ['client']);
    }

    public function testTryTokenLoginAdminQueriesOnlyActiveAdmins(): void
    {
        $controller = new Client();
        $token = 'admin-api-token';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:' . $token);

        $admin = $this->buildAdminModel(12, $token, \Model_Admin::ROLE_ADMIN);
        $cronAdmin = $this->buildAdminModel(1, 'cron-token', \Model_Admin::ROLE_CRON);

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->once())
            ->method('findOne')
            ->with('Admin', 'api_token = ? AND status = ?', [$token, \Model_Admin::STATUS_ACTIVE])
            ->willReturn($admin);

        $sessionMock = $this->createMock(\FOSSBilling\Session::class);
        $sessionMock->expects($this->once())
            ->method('set')
            ->with('admin', [
                'id' => 12,
                'email' => '',
                'name' => '',
                'role' => \Model_Admin::ROLE_ADMIN,
            ]);

        $staffService = new readonly class($cronAdmin) {
            public function __construct(private \Model_Admin $cronAdmin)
            {
            }

            public function getCronAdmin(): \Model_Admin
            {
                return $this->cronAdmin;
            }
        };

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['session'] = $sessionMock;
        $di['mod_service'] = $di->protect(fn ($name): ?object => $name === 'staff' ? $staffService : null);
        $controller->setDi($di);

        $this->invokePrivate($controller, '_tryTokenLogin', ['admin']);
    }

    private function buildAdminModel(int $id, string $apiToken, string $role): \Model_Admin
    {
        $admin = new \Model_Admin();
        $bean = new \stdClass();
        $bean->id = $id;
        $bean->api_token = $apiToken;
        $bean->role = $role;
        $bean->email = '';
        $bean->name = '';

        $property = new \ReflectionProperty(\RedBeanPHP\SimpleModel::class, 'bean');
        $property->setValue($admin, $bean);

        return $admin;
    }

    private function buildClientModel(int $id, string $apiToken): \Model_Client
    {
        $client = new \Model_Client();
        $bean = new \stdClass();
        $bean->id = $id;
        $bean->api_token = $apiToken;

        $property = new \ReflectionProperty(\RedBeanPHP\SimpleModel::class, 'bean');
        $property->setValue($client, $bean);

        return $client;
    }

    private function invokePrivate(object $instance, string $method, array $args = [])
    {
        $reflection = new \ReflectionClass($instance);
        $reflectionMethod = $reflection->getMethod($method);

        return $reflectionMethod->invokeArgs($instance, $args);
    }
}
