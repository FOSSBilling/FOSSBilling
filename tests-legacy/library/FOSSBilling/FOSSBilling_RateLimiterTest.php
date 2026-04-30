<?php

declare(strict_types=1);

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

final class FOSSBilling_RateLimiterTest extends BBTestCase
{
    public function testConsumeAllowsUntilLimitIsReached(): void
    {
        $limiter = $this->createRateLimiter();
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
        $limiter = $this->createRateLimiter();
        $subject = 'subject-' . uniqid('', true);

        $limiter->consume('client_password_reset_email', $subject, 3);
        $second = $limiter->consume('client_password_reset_email', $subject);

        $this->assertTrue($second->isLimited());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_LIMITED, $second->getReason());
    }

    public function testConsumeOrThrowReturnsAllowedResult(): void
    {
        $limiter = $this->createRateLimiter();

        $result = $limiter->consumeOrThrow('api_guest', 'subject-' . uniqid('', true));

        $this->assertFalse($result->isLimited());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_ALLOWED, $result->getReason());
    }

    public function testConsumeOrThrowRaisesRateLimitException(): void
    {
        $limiter = $this->createRateLimiter();
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

        $limiter = $this->createRateLimiter();
        $limiter->consume('unknown_policy', 'subject');
    }

    public function testCidrWhitelistBypassesLimiter(): void
    {
        $di = new Pimple\Container();
        $di['rate_limit_cache'] = new Symfony\Component\Cache\Adapter\ArrayAdapter();

        $limiter = new class extends FOSSBilling\Security\RateLimiter {
            protected function getConfig(): array
            {
                $config = self::getDefaultConfig();
                $config['whitelist_ips'] = ['10.0.0.0/8'];
                return $config;
            }
        };
        $limiter->setDi($di);

        $result = $limiter->consume('api_guest', '10.0.0.5');

        $this->assertFalse($result->isLimited());
        $this->assertTrue($result->isBypassed());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_WHITELISTED, $result->getReason());
    }

    public function testWhitelistUsesRequestIpForAuthenticatedSubjects(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getClientIp')
            ->willReturn('10.0.0.5');

        $di = new Pimple\Container();
        $di['rate_limit_cache'] = new ArrayAdapter();
        $di['request'] = $request;

        $limiter = new class extends FOSSBilling\Security\RateLimiter {
            protected function getConfig(): array
            {
                $config = self::getDefaultConfig();
                $config['whitelist_ips'] = ['10.0.0.0/8'];

                return $config;
            }
        };
        $limiter->setDi($di);

        $result = $limiter->consume('api_authenticated', 'client:123');

        $this->assertFalse($result->isLimited());
        $this->assertTrue($result->isBypassed());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_WHITELISTED, $result->getReason());
    }

    private function createRateLimiter(): FOSSBilling\Security\RateLimiter
    {
        $di = new Pimple\Container();
        $di['rate_limit_cache'] = new ArrayAdapter();

        $limiter = new class extends FOSSBilling\Security\RateLimiter {
            protected function getConfig(): array
            {
                return self::getDefaultConfig();
            }
        };
        $limiter->setDi($di);

        return $limiter;
    }
}
