<?php

declare(strict_types=1);

namespace Box\Mod\Staff\Controller;

use FOSSBilling\Security\RateLimitResult;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    private ?\Pimple\Container $di = null;
    private ?\ArrayObject $rateLimitCalls = null;

    protected function setUp(): void
    {
        parent::setUp();

        http_response_code(200);
        $this->di = $this->getDi();
    }

    protected function tearDown(): void
    {
        http_response_code(200);

        parent::tearDown();
    }

    public function testPasswordResetConfirmConsumesPageRateLimit(): void
    {
        $controller = $this->createController(limited: false);

        $response = $controller->get_updatepassword($this->createApp(), 'staff-reset-hash');

        $this->assertSame('rendered:mod_staff_password_update', $response);
        $this->assertSame([['staff_password_reset_confirm_ip', '127.0.0.1', 1]], $this->rateLimitCalls?->getArrayCopy());
    }

    private function createController(bool $limited): Admin
    {
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $this->rateLimitCalls = new \ArrayObject();

        $rateLimiter = new class($this->rateLimitCalls, $limited) {
            public function __construct(private \ArrayObject $calls, private bool $limited)
            {
            }

            public function consume(string $policy, string $subject, int $tokens = 1): RateLimitResult
            {
                $this->calls[] = [$policy, $subject, $tokens];

                return new RateLimitResult($policy, $this->limited, 20, $this->limited ? 0 : 19);
            }
        };

        $this->di['request'] = $request;
        $this->di['rate_limiter'] = $rateLimiter;
        $this->di['events_manager'] = new class {
            public function fire(array $event): void
            {
            }
        };
        $this->di['mod'] = $this->di->protect(fn (string $name): object => new class {
            public function getConfig(): array
            {
                return ['public' => ['reset_pw' => '1']];
            }
        });
        $this->di['db'] = new class {
            public function findOne(string $type, string $sql, array $bindings): \Model_AdminPasswordReset
            {
                $reset = new \Model_AdminPasswordReset();
                $bean = new \stdClass();
                $bean->admin_id = 1;
                $bean->hash = 'staff-reset-hash';
                $bean->created_at = date('Y-m-d H:i:s');

                $property = new \ReflectionProperty(\RedBeanPHP\SimpleModel::class, 'bean');
                $property->setValue($reset, $bean);

                return $reset;
            }

            public function getExistingModelById(string $type, int|string $id, string $message): \Model_Admin
            {
                $admin = new \Model_Admin();
                $bean = new \stdClass();
                $bean->email = 'admin@example.com';

                $property = new \ReflectionProperty(\RedBeanPHP\SimpleModel::class, 'bean');
                $property->setValue($admin, $bean);

                return $admin;
            }
        };

        $controller = new Admin();
        $controller->setDi($this->di);

        return $controller;
    }

    private function createApp(): \Box_App
    {
        return new class extends \Box_App {
            public function render($fileName, $variableArray = []): string
            {
                return 'rendered:' . $fileName;
            }
        };
    }
}
