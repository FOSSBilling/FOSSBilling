<?php

declare(strict_types=1);

namespace Box\Mod\Api;

use Box\Mod\Api\Controller\Client;
use FOSSBilling\InformationException;
use PHPUnit\Framework\Attributes\Group;

final class CronStaffServiceDouble
{
    public function __construct(private \Model_Admin $cronAdmin)
    {
    }

    public function getCronAdmin(): \Model_Admin
    {
        return $this->cronAdmin;
    }
}

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

    public function testShouldPreferSessionAuthForApiKeyManagementMethods(): void
    {
        $controller = new Client();

        $this->assertTrue($this->invokePrivate($controller, 'shouldPreferSessionAuth', ['profile_api_key_get']));
        $this->assertTrue($this->invokePrivate($controller, 'shouldPreferSessionAuth', ['profile_api_key_reset']));
        $this->assertTrue($this->invokePrivate($controller, 'shouldPreferSessionAuth', ['profile_generate_api_key']));
        $this->assertFalse($this->invokePrivate($controller, 'shouldPreferSessionAuth', ['profile_get']));
    }

    public function testHasAuthenticatedSessionReturnsFalseWhenSessionLookupThrows(): void
    {
        $controller = new Client();
        $di = $this->getDi();
        $di['is_client_logged'] = function (): void {
            throw new \Exception('Session lookup failed');
        };

        $controller->setDi($di);

        $this->assertFalse($this->invokePrivate($controller, 'hasAuthenticatedSession', ['client']));
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

        $dbAdmin = $this->buildAdminModel(1, $cronToken, \Model_Admin::ROLE_ADMIN);
        $cronAdmin = $this->buildAdminModel(1, $cronToken, \Model_Admin::ROLE_CRON);

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->once())
            ->method('findOne')
            ->with('Admin', 'api_token = ? AND status = ? AND role != ?', [$cronToken, \Model_Admin::STATUS_ACTIVE, \Model_Admin::ROLE_CRON])
            ->willReturn($dbAdmin);

        $staffService = new CronStaffServiceDouble($cronAdmin);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): ?object => $name === 'staff' ? $staffService : null);
        $controller->setDi($di);

        $this->expectException(InformationException::class);
        $this->expectExceptionCode(205);
        $this->invokePrivate($controller, '_tryTokenLogin', ['admin']);
    }

    public function testTryTokenLoginRequiresActiveClientToken(): void
    {
        $controller = new Client();
        $apiToken = 'inactive-client-token';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('client:' . $apiToken);

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->once())
            ->method('findOne')
            ->with('Client', 'api_token = ? AND status = ?', [$apiToken, \Model_Client::ACTIVE])
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $controller->setDi($di);

        $this->expectException(InformationException::class);
        $this->expectExceptionCode(204);
        $this->invokePrivate($controller, '_tryTokenLogin', ['client']);
    }

    public function testTryTokenLoginRequiresActiveNonCronAdminToken(): void
    {
        $controller = new Client();
        $apiToken = 'inactive-admin-token';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:' . $apiToken);

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->once())
            ->method('findOne')
            ->with('Admin', 'api_token = ? AND status = ? AND role != ?', [$apiToken, \Model_Admin::STATUS_ACTIVE, \Model_Admin::ROLE_CRON])
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $controller->setDi($di);

        $this->expectException(InformationException::class);
        $this->expectExceptionCode(205);
        $this->invokePrivate($controller, '_tryTokenLogin', ['admin']);
    }

    private function buildAdminModel(int $id, string $apiToken, string $role): \Model_Admin
    {
        $admin = new \Model_Admin();
        $bean = new \stdClass();
        $bean->id = $id;
        $bean->api_token = $apiToken;
        $bean->role = $role;
        $bean->status = \Model_Admin::STATUS_ACTIVE;

        $property = new \ReflectionProperty(\RedBeanPHP\SimpleModel::class, 'bean');
        $property->setValue($admin, $bean);

        return $admin;
    }

    private function invokePrivate(object $instance, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($instance);
        $reflectionMethod = $reflection->getMethod($method);

        return $reflectionMethod->invokeArgs($instance, $args);
    }
}
