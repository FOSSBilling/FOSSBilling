<?php

declare(strict_types=1);

use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class FOSSBilling_RateLimiterTest extends BBTestCase
{
    public function testConsumeAllowsUntilLimitIsReached(): void
    {
        $limiter = $this->createRateLimiter();
        $subject = 'subject-' . uniqid('', true);

        $first = $limiter->consume('api_guest', $subject, 300);
        $second = $limiter->consume('api_guest', $subject);

        $this->assertTrue($first->isAccepted());
        $this->assertFalse($first->isLimited());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_ALLOWED, $first->getReason());
        $this->assertFalse($second->isAccepted());
        $this->assertTrue($second->isLimited());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_LIMITED, $second->getReason());
    }

    public function testPasswordResetPolicyReportsLimitedResult(): void
    {
        $limiter = $this->createRateLimiter();
        $subject = 'subject-' . uniqid('', true);

        $limiter->consume('client_password_reset_email', $subject, 3);
        $second = $limiter->consume('client_password_reset_email', $subject);

        $this->assertFalse($second->isAccepted());
        $this->assertTrue($second->isLimited());
        $this->assertSame(FOSSBilling\Security\RateLimitResult::REASON_LIMITED, $second->getReason());
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
