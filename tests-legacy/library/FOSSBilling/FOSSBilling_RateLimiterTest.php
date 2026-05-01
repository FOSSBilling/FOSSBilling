<?php

declare(strict_types=1);

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

final class FOSSBilling_RateLimiterTest extends BBTestCase
{
    public function testConsumeAllowsUntilLimitIsReached(): void
    {
        $limiter = $this->createRateLimiter(requestIp: '1.1.1.1');
        $subject = 'subject-' . uniqid('', true);

        $first = $limiter->consume('api_guest', $subject, 100);
        $second = $limiter->consume('api_guest', $subject);

        $this->assertFalse($first->isLimited());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_ALLOWED, $first->getReason());
        $this->assertTrue($second->isLimited());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_LIMITED, $second->getReason());
    }

    public function testPasswordResetPolicyReportsLimitedResult(): void
    {
        $limiter = $this->createRateLimiter(requestIp: '1.1.1.1');
        $subject = 'subject-' . uniqid('', true);

        $limiter->consume('client_password_reset_email', $subject, 3);
        $second = $limiter->consume('client_password_reset_email', $subject);

        $this->assertTrue($second->isLimited());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_LIMITED, $second->getReason());
    }

    public function testConsumeOrThrowReturnsAllowedResult(): void
    {
        $limiter = $this->createRateLimiter(requestIp: '1.1.1.1');

        $result = $limiter->consumeOrThrow('api_guest', 'subject-' . uniqid('', true));

        $this->assertFalse($result->isLimited());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_ALLOWED, $result->getReason());
    }

    public function testConsumeOrThrowRaisesRateLimitException(): void
    {
        $limiter = $this->createRateLimiter(requestIp: '1.1.1.1');
        $subject = 'subject-' . uniqid('', true);
        $limiter->consume('client_password_reset_email', $subject, 3);

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->expectExceptionCode(429);
        $this->expectExceptionMessage('Rate limit exceeded. Please try again later.');

        $limiter->consumeOrThrow('client_password_reset_email', $subject);
    }

    public function testUnknownPolicyThrowsException(): void
    {
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Rate limiter policy unknown_policy is not defined or invalid');

        $limiter = $this->createRateLimiter(requestIp: '1.1.1.1');
        $limiter->consume('unknown_policy', 'subject');
    }

    public function testCidrWhitelistBypassesLimiter(): void
    {
        $limiter = $this->createRateLimiter(requestIp: '10.0.0.5', whitelist: ['10.0.0.0/8']);

        $result = $limiter->consume('api_guest', '10.0.0.5');

        $this->assertFalse($result->isLimited());
        $this->assertTrue($result->isBypassed());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_WHITELISTED, $result->getReason());
    }

    public function testWhitelistUsesRequestIpForAuthenticatedSubjects(): void
    {
        $limiter = $this->createRateLimiter(requestIp: '10.0.0.5', whitelist: ['10.0.0.0/8']);

        $result = $limiter->consume('api_authenticated', 'client:123');

        $this->assertFalse($result->isLimited());
        $this->assertTrue($result->isBypassed());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_WHITELISTED, $result->getReason());
    }

    public function testNonWhitelistedRequestIpLimitsAuthenticatedSubject(): void
    {
        $limiter = $this->createRateLimiter(requestIp: '1.1.1.1', whitelist: ['10.0.0.0/8']);

        $limiter->consume('client_email_verification_resend_account', 'client:123', 3);
        $second = $limiter->consume('client_email_verification_resend_account', 'client:123');

        $this->assertTrue($second->isLimited());
        $this->assertFalse($second->isBypassed());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_LIMITED, $second->getReason());
    }

    private function createRateLimiter(string $requestIp, array $whitelist = []): FOSSBilling\Security\RateLimiter
    {
        $di = new Pimple\Container();
        $di['rate_limit_cache'] = new ArrayAdapter();
        $di['request'] = $this->createRequest($requestIp);

        $limiter = new class($whitelist) extends FOSSBilling\Security\RateLimiter {
            public function __construct(private readonly array $whitelist)
            {
            }

            protected function getConfig(): array
            {
                $config = self::getDefaultConfig();
                $config['whitelist_ips'] = $this->whitelist;

                return $config;
            }
        };
        $limiter->setDi($di);

        return $limiter;
    }

    private function createRequest(string $ip): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getClientIp')
            ->willReturn($ip);

        return $request;
    }
}
