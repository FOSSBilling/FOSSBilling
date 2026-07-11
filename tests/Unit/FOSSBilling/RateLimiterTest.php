<?php

declare(strict_types=1);

use FOSSBilling\Security\RateLimitResult;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

function createRateLimiter(string $requestIp, array $whitelist = []): FOSSBilling\Security\RateLimiter
{
    $di = new Pimple\Container();
    $di['rate_limit_cache'] = new ArrayAdapter();
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('getClientIp')->andReturn($requestIp);
    $di['request'] = $request;

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

test('consume allows until limit is reached', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');
    $subject = 'subject-' . uniqid('', true);

    $limit = $limiter->getStatus('api_guest', $subject)->getLimit();
    $lastAllowed = null;

    for ($i = 0; $i < $limit; $i++) {
        $lastAllowed = $limiter->consume('api_guest', $subject);
    }

    $limited = $limiter->consume('api_guest', $subject);

    expect($lastAllowed)->not->toBeNull();
    expect($lastAllowed->isLimited())->toBeFalse();
    expect($lastAllowed->getReason())->toBe(RateLimitResult::REASON_ALLOWED);
    expect($limited->isLimited())->toBeTrue();
    expect($limited->getReason())->toBe(RateLimitResult::REASON_LIMITED);
});

test('password reset policy reports limited result', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');
    $subject = 'subject-' . uniqid('', true);

    $limiter->consume('client_password_reset_email', $subject, 3);
    $second = $limiter->consume('client_password_reset_email', $subject);

    expect($second->isLimited())->toBeTrue();
    expect($second->getReason())->toBe(RateLimitResult::REASON_LIMITED);
});

test('consume or throw returns allowed result', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');

    $result = $limiter->consumeOrThrow('api_guest', 'subject-' . uniqid('', true));

    expect($result->isLimited())->toBeFalse();
    expect($result->getReason())->toBe(RateLimitResult::REASON_ALLOWED);
});

test('consume or throw raises rate limit exception', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');
    $subject = 'subject-' . uniqid('', true);
    $limiter->consume('client_password_reset_email', $subject, 3);

    expect(fn (): RateLimitResult => $limiter->consumeOrThrow('client_password_reset_email', $subject))
        ->toThrow(
            FOSSBilling\Security\RateLimitException::class,
            function (FOSSBilling\Security\RateLimitException $exception): void {
                expect($exception->getCode())->toBe(429);
                expect($exception->getMessage())->toBe('Rate limit exceeded. Please try again later.');
                expect($exception->getRateLimitResult()->getPolicy())->toBe('client_password_reset_email');
                expect($exception->hasRetryAfter())->toBeTrue();
                expect($exception->getRetryAfterSeconds())->toBeGreaterThan(0);
                expect($exception->getRetryAfterSeconds())->toBeLessThanOrEqual(3600);
            }
        );
});

test('unknown policy throws exception', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');

    expect(fn (): RateLimitResult => $limiter->consume('unknown_policy', 'subject'))
        ->toThrow(FOSSBilling\Exception::class, 'Rate limiter policy unknown_policy is not defined or invalid');
});

test('CIDR whitelist bypasses limiter', function (): void {
    $limiter = createRateLimiter(requestIp: '10.0.0.5', whitelist: ['10.0.0.0/8']);

    $result = $limiter->consume('api_guest', '10.0.0.5');

    expect($result->isLimited())->toBeFalse();
    expect($result->isBypassed())->toBeTrue();
    expect($result->getReason())->toBe(RateLimitResult::REASON_WHITELISTED);
});

test('whitelist uses request IP for authenticated subjects', function (): void {
    $limiter = createRateLimiter(requestIp: '10.0.0.5', whitelist: ['10.0.0.0/8']);

    $result = $limiter->consume('api_authenticated_account', 'client:123');

    expect($result->isLimited())->toBeFalse();
    expect($result->isBypassed())->toBeTrue();
    expect($result->getReason())->toBe(RateLimitResult::REASON_WHITELISTED);
});

test('non-whitelisted request IP limits authenticated subject', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1', whitelist: ['10.0.0.0/8']);

    $limiter->consume('client_email_verification_resend_account', 'client:123', 3);
    $second = $limiter->consume('client_email_verification_resend_account', 'client:123');

    expect($second->isLimited())->toBeTrue();
    expect($second->isBypassed())->toBeFalse();
    expect($second->getReason())->toBe(RateLimitResult::REASON_LIMITED);
});
