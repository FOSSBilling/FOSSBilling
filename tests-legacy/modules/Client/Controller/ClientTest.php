<?php

declare(strict_types=1);

namespace Box\Mod\Client\Controller;

use FOSSBilling\Security\RateLimitResult;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

#[Group('Core')]
final class ClientTest extends \BBTestCase
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

        $response = $controller->get_reset_password_confirm($this->createApp(), 'password-reset-hash');

        $this->assertSame('rendered:mod_client_set_new_password', $response);
        $this->assertSame([['client_password_reset_confirm_ip', '127.0.0.1', 1]], $this->rateLimitCalls?->getArrayCopy());
    }

    public function testEmailConfirmConsumesPageRateLimit(): void
    {
        $controller = $this->createController(limited: false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('redirect:/');

        try {
            $controller->get_client_confirmation($this->createApp(), 'email-confirm-hash');
        } finally {
            $this->assertSame([['client_email_confirm_ip', '127.0.0.1', 1]], $this->rateLimitCalls?->getArrayCopy());
        }
    }

    private function createController(bool $limited): Client
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
        $this->di['mod_service'] = $this->di->protect(fn (string $name): object => match (strtolower($name)) {
            'client' => new class {
                public function password_reset_valid(array $data): bool
                {
                    return true;
                }

                public function approveClientEmailByHash(string $hash): void
                {
                }
            },
            'system' => new class {
                public function setPendingMessage(string $message): void
                {
                }
            },
            default => new \stdClass(),
        });

        $controller = new Client();
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

            public function redirect($path): never
            {
                throw new \RuntimeException('redirect:' . $path);
            }
        };
    }
}
